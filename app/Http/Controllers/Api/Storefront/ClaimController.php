<?php
namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CampaignEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClaimController extends Controller
{
    // =========================================================================
    // FLOW A: CART PROMO CODES
    // =========================================================================

    /**
     * Validate a promo code entered at Checkout.
     * POST /api/storefront/{slug}/claim/validate-code
     */
    public function validateCode(Request $request, $slug)
    {
        $user = $request->user();

        $validated = $request->validate([
            'code'               => 'required|string',
            'cart_product_ids'   => 'required|array|min:1',
            'cart_product_ids.*' => 'integer|exists:products,id',
        ]);

        // 1. Find the entitlement WITH Strict Tenancy Check!
        $entitlement = CampaignEntitlement::where('claim_code', $validated['code'])
            ->where('issued_to_user_id', $user->id)
            ->whereHas('campaign', function ($query) use ($user) {
                // Ensure the campaign belongs to the user's current company
                $query->where('company_id', $user->company_id);
            })
            ->with('campaign')
            ->first();

        // 2. Base Validation
        if (! $entitlement) {
            return response()->json(['message' => 'Invalid promo code.'], 400);
        }
        if ($entitlement->is_claimed) {
            return response()->json(['message' => 'This promo code has already been used.'], 400);
        }
        if ($entitlement->expires_at && $entitlement->expires_at->isPast()) {
            return response()->json(['message' => 'This promo code has expired.'], 400);
        }

        // 3. Campaign Catalog Restrictions Check
        $campaign = $entitlement->campaign;
        $config   = $campaign->config_json ?? [];

        // Did the admin set a custom catalog in Step 6?
        if (! empty($config['catalog_selection']) && is_array($config['catalog_selection'])) {
            $allowedProductIds = $config['catalog_selection'];
            $cartProductIds    = $validated['cart_product_ids'];

            // Check if any item in the cart is NOT in the allowed list
            $unauthorizedItems = array_diff($cartProductIds, $allowedProductIds);

            if (count($unauthorizedItems) > 0) {
                return response()->json([
                    'message'    => 'Your cart contains items that are not eligible for this promo code. Please remove them or check the allowed catalog.',
                    'error_code' => 'catalog_mismatch',
                ], 422);
            }
        }

        // 4. Success! Return the Fiat value of the coupon to the React app
        return response()->json([
            'message' => 'Promo code applied successfully!',
            'data'    => [
                'code'  => $entitlement->claim_code,
                'value' => $entitlement->reward_value, // Absolute Rupee value
            ],
        ]);
    }

    // =========================================================================
    // FLOW B: MAGIC LINKS (EMAIL REDEMPTION)
    // =========================================================================

    /**
     * 1. Validate the Magic Link and load the Single Page Checkout Data
     * GET /api/storefront/{slug}/claim/validate
     */
    public function validateToken(Request $request)
    {
        $request->validate(['token' => 'required|string']);
        $user        = $request->user();
        $entitlement = CampaignEntitlement::where('claim_token', $request->token)
            ->with(['campaign', 'user:id,first_name,last_name,email'])
            ->first();

        if (! $entitlement) {
            return response()->json(['message' => 'Invalid or malformed reward link.'], 404);
        }
        if ($entitlement->issued_to_user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized. This reward belongs to another employee.'], 403);
        }

        if ($entitlement->is_claimed) {
            return response()->json(['message' => 'This reward has already been claimed.'], 400);
        }

        if ($entitlement->expires_at && $entitlement->expires_at->isPast()) {
            return response()->json(['message' => 'This reward link has expired.'], 400);
        }

        if ($entitlement->campaign->status !== 'active') {
            return response()->json(['message' => 'This campaign is not currently active.'], 400);
        }
        $templateId = $entitlement->campaign->config_json['landing_page_template_id'] ?? null;
        $template   = \App\Models\LandingPageTemplate::find($templateId);

        return response()->json([
            'reward_value'    => $entitlement->reward_value,
            'recipient'       => $entitlement->user,
            'campaign_config' => $entitlement->campaign->config_json,
            'template_schema' => $template ? $template->page_schema : [],
            'template_theme'  => $template ? $template->global_theme_tokens : null,
        ]);
    }

    /**
     * 2. Execute the Claim (The "Pay + Points" Upsell for Magic Links)
     * POST /api/storefront/{slug}/claim/execute
     */
    public function executeClaim(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'token'              => 'required|string',
            'product_variant_id' => 'required|exists:product_variants,id',
            'shipping_address'   => 'required|array',
            'fiat_paid'          => 'required|numeric|min:0',
            'payment_reference'  => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($validated, $user) {
                // 1. Strict Identity Lock
                $entitlement = CampaignEntitlement::where('claim_token', $validated['token'])
                    ->with('campaign')
                    ->lockForUpdate()
                    ->firstOrFail();

                $companyId = $entitlement->campaign->company_id;

                if ($entitlement->issued_to_user_id !== $user->id) {
                    throw new Exception("Unauthorized. This reward belongs to another employee.");
                }

                if ($entitlement->is_claimed) {
                    throw new Exception("Reward already claimed.");
                }

                // 2. Lock Variant & Calculate Overrides
                $variant = ProductVariant::with(['product', 'companyOverrides' => function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                }])->lockForUpdate()->findOrFail($validated['product_variant_id']);

                $product = $variant->product;

                // Get company-specific overrides if they exist
                $variantPivot = $variant->companyOverrides->first()?->pivot;
                $finalName    = ($variantPivot?->override_name ?? $product->name) . ' - ' . $variant->name;
                $finalPrice   = $variantPivot?->override_selling_price ?? $variant->selling_price ?? $product->selling_price;

                // 3. Verify Stock
                if ($variant->stock_quantity < 1) {
                    throw new Exception("This item is currently out of stock.");
                }

                // 4. Mathematical Verification & GST
                $rewardValue     = $entitlement->reward_value;
                $expectedFiatDue = max(0, $finalPrice - $rewardValue);
                $gstPercentage   = $product->gst_percentage ?? 0;

                // Tax-inclusive formula
                $gstAmount = $finalPrice - ($finalPrice / (1 + ($gstPercentage / 100)));

                // SECURITY CHECK: Did the frontend actually send enough money?
                if (round($validated['fiat_paid'], 2) < round($expectedFiatDue, 2)) {
                    throw new Exception("Payment amount does not cover the remaining balance. Expected: ₹{$expectedFiatDue}");
                }

                // 5. Create the Master Order
                $order = Order::create([
                    'company_id'                => $companyId,
                    'user_id'                   => $user->id,
                    'total_amount'              => $finalPrice,
                    'gst_total'                 => $gstAmount,
                    'points_used'               => $rewardValue,
                    'fiat_paid'                 => $expectedFiatDue,
                    'payment_gateway_reference' => $validated['payment_reference'],
                    'status'                    => 'paid',
                    'shipping_name'             => $user->first_name . ' ' . $user->last_name,
                    'shipping_mobile'           => $user->mobile,
                    'shipping_address_line_1'   => $validated['shipping_address']['address_line_1'] ?? null,
                    'shipping_city'             => $validated['shipping_address']['city'] ?? null,
                    'shipping_state'            => $validated['shipping_address']['state'] ?? null,
                    'shipping_pincode'          => $validated['shipping_address']['pincode'] ?? null,
                ]);

                // 6. Create the Order Item
                OrderItem::create([
                    'order_id'            => $order->id,
                    'product_id'          => $product->id,
                    'product_variant_id'  => $variant->id,
                    'product_name'        => $finalName,
                    'quantity'            => 1,
                    'unit_price'          => $finalPrice,
                    'unit_gst_percentage' => $gstPercentage,
                    'total_price'         => $finalPrice,
                    'delivery_status'     => $product->type === 'physical' ? 'pending' : 'instant',
                ]);

                // 7. Inventory & State Management
                $variant->decrement('stock_quantity', 1);

                $entitlement->update([
                    'is_claimed' => true,
                    'claimed_at' => now(),
                ]);

                // Deduct from the Campaign's Locked Budget (Release the Escrow)
                $entitlement->campaign->decrement('budget_locked', $rewardValue);

                return response()->json([
                    'message'      => 'Reward claimed successfully! Your order has been placed.',
                    'order_number' => $order->order_number,
                ]);
            });
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }


    /**
     * 3. Fetch the restricted catalog for the Landing Page
     * GET /api/storefront/{slug}/claim/catalog
     */
    public function catalog(Request $request, $slug)
    {
        $company = \App\Models\Company::where('alias', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $multiplier = (float) ($company->point_multiplier ?? 1.00);

        $allowedIds = $request->query('allowed_ids', []);

        $hiddenCategoryIds = $company->hidden_category_ids ?? [];
        $hiddenProductIds  = $company->hidden_product_ids ?? [];

        $query = \App\Models\Product::where('is_active', true)
            ->whereNotIn('category_id', $hiddenCategoryIds)
            ->whereNotIn('id', $hiddenProductIds);

        if (!empty($allowedIds) && is_array($allowedIds)) {
            $query->whereIn('id', $allowedIds);
        }

        $query->whereDoesntHave('customCompanies', function ($q) use ($company) {
            $q->where('company_id', $company->id)->where('is_excluded', true);
        })
        ->with(['variants' => function ($q) use ($company) {
            $q->where('is_active', true)
              ->with(['companyOverrides' => function ($subQ) use ($company) {
                  $subQ->where('company_id', $company->id);
              }]);
        }, 'customCompanies' => function ($q) use ($company) {
            $q->where('company_id', $company->id);
        }]);

        $products = $query->get();

        $mappedProducts = $products->map(function ($product) use ($multiplier) {
            $productPivot = $product->customCompanies->first()?->pivot;

            $finalName        = $productPivot?->override_name ?? $product->name;
            $finalImage       = $productPivot?->override_image ?? $product->main_image;
            $fiatMrp          = $productPivot?->override_mrp ?? $product->mrp;
            $fiatSellingPrice = $productPivot?->override_selling_price ?? $product->selling_price;

            return [
                'id'             => $product->id,
                'name'           => $finalName,
                'slug'           => $product->slug,
                'main_image_url' => $finalImage ? asset('storage/' . $finalImage) : null,
                'mrp'            => (float) $fiatMrp,
                'selling_price'  => (float) $fiatSellingPrice,
                'has_variants'   => $product->variants->count() > 0,
                
                'variants'       => $product->variants->map(function ($v) use ($productPivot, $product) {
                    $variantPivot = $v->companyOverrides->first()?->pivot;
                    
                    $vPrice = $variantPivot?->override_selling_price ?? $v->selling_price ?? $productPivot?->override_selling_price ?? $product->selling_price;
                    $vMrp   = $variantPivot?->override_mrp ?? $v->mrp ?? $productPivot?->override_mrp ?? $product->mrp;
                    $vImage = $variantPivot?->override_image ?? $v->image ?? $productPivot?->override_image ?? $product->main_image;

                    return [
                        'id'             => $v->id,
                        'name'           => $v->name,
                        'sku'            => $v->sku,
                        'image_url'      => $vImage ? asset('storage/' . $vImage) : null,
                        'mrp'            => (float) $vMrp,
                        'selling_price'  => (float) $vPrice,
                        'stock_quantity' => $v->stock_quantity,
                    ];
                })->values()->toArray(),
            ];
        });

        return response()->json(['data' => $mappedProducts]);
    }
}
