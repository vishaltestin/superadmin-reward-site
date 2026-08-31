<?php
namespace App\Http\Controllers\Api\Storefront;

use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\CampaignEntitlement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Services\OrderFulfilmentService;
use App\Services\PricingService;
use App\Services\RazorpayPaymentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClaimController extends Controller
{
    private RazorpayPaymentService $razorpay;

    public function __construct()
    {
        $this->razorpay = app(RazorpayPaymentService::class);
    }

    // =========================================================================
    // FLOW A: CART PROMO CODES
    // =========================================================================

    /**
     * Validate a promo code entered at Checkout.
     * POST /api/storefront/{slug}/user/claim/validate-code
     */
    public function validateCode(Request $request, $slug)
    {
        $user = $request->user();

        $validated = $request->validate([
            'code'               => 'required|string',
            'cart_product_ids'   => 'required|array|min:1',
            'cart_product_ids.*' => 'integer|exists:products,id',
        ]);

        $entitlement = CampaignEntitlement::where('claim_code', $validated['code'])
            ->where('issued_to_user_id', $user->id)
            ->whereHas('campaign', function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            })
            ->with('campaign')
            ->first();

        if (! $entitlement) {
            return response()->json(['message' => 'Invalid promo code.'], 400);
        }
        if ($entitlement->is_claimed) {
            return response()->json(['message' => 'This promo code has already been used.'], 400);
        }
        if ($entitlement->expires_at && $entitlement->expires_at->isPast()) {
            return response()->json(['message' => 'This promo code has expired.'], 400);
        }

        $campaign = $entitlement->campaign;
        $config   = $campaign->config_json ?? [];

        if (! empty($config['catalog_selection']) && is_array($config['catalog_selection'])) {
            $unauthorizedItems = array_diff($validated['cart_product_ids'], $config['catalog_selection']);

            if (count($unauthorizedItems) > 0) {
                return response()->json([
                    'message'    => 'Your cart contains items that are not eligible for this promo code. Please remove them or check the allowed catalog.',
                    'error_code' => 'catalog_mismatch',
                ], 422);
            }
        }

        return response()->json([
            'message' => 'Promo code applied successfully!',
            'data'    => [
                'code'  => $entitlement->claim_code,
                'value' => $entitlement->reward_value,
            ],
        ]);
    }

    // =========================================================================
    // FLOW B: MAGIC LINKS (EMAIL REDEMPTION) — REAL PAYMENT FLOW
    // =========================================================================

    /**
     * 1. Validate the Magic Link and load the Single Page Checkout Data
     * GET /api/storefront/{slug}/user/claim/validate
     */
    public function validateToken(Request $request)
    {
        $request->validate(['token' => 'required|string']);
        $user        = $request->user();
        $entitlement = CampaignEntitlement::where('claim_token', $request->token)
            ->with(['campaign', 'user:id,first_name,last_name,email'])
            ->first();

        if (! $entitlement) {
            return response()->json([
                'message'    => 'Invalid or malformed reward link.',
                'error_code' => 'invalid_token',
            ], 404);
        }
        if ($entitlement->issued_to_user_id !== $user->id) {
            return response()->json([
                'message'    => 'This reward belongs to another employee.',
                'error_code' => 'reward_owner_mismatch',
            ], 422);
        }

        if ($entitlement->is_claimed) {
            // Distinct error_code so the storefront can show a friendly
            // "already claimed → view your order" state instead of the
            // generic invalid-link card (it used to be indistinguishable
            // from an expired link).
            return response()->json([
                'message'    => 'This reward has already been claimed.',
                'error_code' => 'already_claimed',
            ], 400);
        }

        if ($entitlement->expires_at && $entitlement->expires_at->isPast()) {
            return response()->json([
                'message'    => 'This reward link has expired.',
                'error_code' => 'expired',
            ], 400);
        }

        if ($entitlement->campaign->status !== 'active') {
            return response()->json([
                'message'    => 'This campaign is not currently active.',
                'error_code' => 'campaign_inactive',
            ], 400);
        }

        $templateId = $entitlement->campaign->config_json['landing_page_template_id'] ?? null;
        $template   = \App\Models\LandingPageTemplate::find($templateId);

        // SAFETY NET: the claim page can only render blocks from the builder
        // dialect (HeroBanner / VideoSection / CallToAction / RewardSelector),
        // and a link claim is impossible without a RewardSelector block. If the
        // campaign has no template attached, or the attached template's
        // page_schema is in a different dialect / has no visible selector, serve
        // the built-in default claim page instead of an empty schema (which
        // rendered as a blank white screen).
        $schema = $template?->page_schema ?? [];
        $theme  = $template?->global_theme_tokens;

        $hasVisibleSelector = collect($schema)->contains(
            fn($block) => ($block['type'] ?? null) === 'RewardSelector'
            && ($block['isVisible'] ?? true)
        );

        if (! $hasVisibleSelector) {
            $schema = $this->defaultClaimSchema();
            $theme  = $theme ?? [
                'primaryColor' => '#0f172a',
                'textColor'    => '#0f172a',
                'fontFamily'   => 'Inter, sans-serif',
            ];
        }

        // Merge tags ({{ first_name }}, {{ company_name }}, ...) inside the
        // template are replaced with the recipient's real data — the same
        // parser the campaign emails use, so landing copy like "Welcome
        // {{ first_name }}" renders personalized instead of literal.
        $mergePayload = [
            'first_name'     => $user->first_name,
            'last_name'      => $user->last_name,
            'email'          => $user->email,
            'company_name'   => $user->company?->name ?? 'Our Company',
            'campaign_name'  => $entitlement->campaign->name,
            'reward_value'   => $entitlement->reward_value,
            'points_awarded' => $entitlement->reward_value,
            'current_date'   => now()->format('F j, Y'),
        ];

        array_walk_recursive($schema, function (&$value) use ($mergePayload): void {
            if (is_string($value)) {
                $value = \App\Services\EmailParserService::parse($value, $mergePayload);
            }
        });

        return response()->json([
            'reward_value'    => $entitlement->reward_value,
            'recipient'       => $entitlement->user,
            'campaign_config' => $entitlement->campaign->config_json,
            'template_schema' => $schema,
            'template_theme'  => $theme,
        ]);
    }

    /**
     * Built-in claim page (builder dialect) used when the campaign's landing
     * template is missing or unusable. Shapes mirror the storefront's
     * types/builder.ts block interfaces exactly.
     */
    private function defaultClaimSchema(): array
    {
        return [
            [
                'id'        => 'default_hero',
                'type'      => 'HeroBanner',
                'isVisible' => true,
                'variant'   => 'centered',
                'content'   => [
                    'heading'    => 'You have a reward waiting!',
                    'subtext'    => "Pick any product from your company's reward catalog below. The value of this reward is applied automatically at checkout.",
                    'buttonText' => 'Choose your reward',
                    'buttonLink' => '#rewards',
                ],
                'styles'    => [
                    'backgroundImage' => '',
                    'overlayOpacity'  => 0.35,
                    'backgroundColor' => '#0f172a',
                    'headingColor'    => '#ffffff',
                    'subtextColor'    => '#cbd5e1',
                    'buttonBgColor'   => '#ffffff',
                    'buttonTextColor' => '#0f172a',
                ],
            ],
            [
                'id'        => 'default_selector',
                'type'      => 'RewardSelector',
                'isVisible' => true,
                'variant'   => 'grid',
                'content'   => [
                    'heading' => 'Choose Your Reward',
                    'subtext' => 'Tap a product to claim it with this reward.',
                ],
                'styles'    => [
                    'backgroundColor'     => '#ffffff',
                    'headingColor'        => '#0f172a',
                    'cardBackgroundColor' => '#f8fafc',
                ],
            ],
            [
                'id'        => 'default_cta',
                'type'      => 'CallToAction',
                'isVisible' => true,
                'variant'   => 'standard',
                'content'   => [
                    'heading'    => 'Questions about your reward?',
                    'buttonText' => 'Contact your HR team',
                    'buttonLink' => '#',
                ],
                'styles'    => [
                    'backgroundColor' => '#f1f5f9',
                    'headingColor'    => '#0f172a',
                    'buttonBgColor'   => '#0f172a',
                    'buttonTextColor' => '#ffffff',
                ],
            ],
        ];
    }

    /**
     * 2a. Start the claim — creates the order. Two outcomes:
     *     - fiatDue == 0 → order fulfilled immediately ("fulfilled": true)
     *     - fiatDue  > 0 → Razorpay order created server-side, nothing fulfilled
     *       until /claim/verify or the webhook confirms captured payment.
     *
     * POST /api/storefront/{slug}/user/claim/execute
     */
    public function executeClaim(Request $request, $slug)
    {
        $user = $request->user();

        $validated = $request->validate([
            'token'                    => 'required|string',
            // Variant products are claimed via product_variant_id; single-SKU
            // products WITHOUT any variants are claimed via product_id alone.
            'product_id'               => 'required|exists:products,id',
            'product_variant_id'       => 'nullable|exists:product_variants,id',
            'shipping_address'         => 'required|array',
            'shipping_address.name'    => 'nullable|string|max:255',
            'shipping_address.mobile'  => 'nullable|string|max:20',
            'shipping_address.line1'   => 'nullable|string|max:255',
            'shipping_address.city'    => 'nullable|string|max:100',
            'shipping_address.state'   => 'nullable|string|max:100',
            'shipping_address.pincode' => 'nullable|string|max:20',
        ]);

        try {
            // Transaction: DATABASE WORK ONLY — no gateway calls inside, so a
            // rollback can never leave an orphan order at Razorpay's side.
            [$order, $entitlementId, $fiatDue] = DB::transaction(function () use ($validated, $user) {

                // 1. Strict identity lock
                $entitlement = CampaignEntitlement::where('claim_token', $validated['token'])
                    ->with('campaign')
                    ->lockForUpdate()
                    ->firstOrFail();

                $companyId = $entitlement->campaign->company_id;

                if ($entitlement->issued_to_user_id !== $user->id) {
                    throw new Exception('Unauthorized. This reward belongs to another employee.');
                }
                if ($entitlement->is_claimed) {
                    throw new Exception('Reward already claimed.');
                }
                if ($entitlement->expires_at && $entitlement->expires_at->isPast()) {
                    throw new Exception('This reward link has expired.');
                }

                // 2. Lock the purchasable entity & calculate overrides.
                //    With a variant: the product is derived from the variant
                //    (ownership implied). Without: the single-SKU product
                //    itself is the purchasable entity.
                /** @var ProductVariant|null $variant */
                $variant = null;

                if (! empty($validated['product_variant_id'])) {
                    $variant = ProductVariant::with([
                        'product',
                        'companyOverrides' => fn($q) => $q->where('company_id', $companyId),
                    ])->lockForUpdate()->findOrFail($validated['product_variant_id']);

                    $product = $variant->product;
                } else {
                    $product = \App\Models\Product::lockForUpdate()->findOrFail($validated['product_id']);
                }

                // 3. Campaign catalog restriction (same rule validateCode enforces for carts)
                $config = $entitlement->campaign->config_json ?? [];
                if (! empty($config['catalog_selection'])
                    && is_array($config['catalog_selection'])
                    && ! in_array($product->id, $config['catalog_selection'])) {
                    throw new Exception('This product is not part of your reward catalog.');
                }

                $variantPivot = $variant?->companyOverrides->first()?->pivot;
                $finalName    = $variant
                    ? (($variantPivot?->override_name ?? $product->name) . ' - ' . $variant->name)
                    : $product->name;

                // Single source of truth: identical to the price the claim
                // catalog displays (company overrides + tier rules included).
                $claimCompany = \App\Models\Company::findOrFail($companyId);
                $finalPrice   = PricingService::unitPrice($product, $variant, $claimCompany, 1);

                // 4. Verify Stock (variant products only — single-SKU products
                //    carry no per-SKU stock counter, same as cart checkout)
                if ($variant && $variant->stock_quantity < 1) {
                    throw new Exception('This item is currently out of stock.');
                }

                // ── Single-active-payment rule ─────────────────────────────────
                // Void any previous UNPAID payment attempts for this reward so at
                // most one active pending payment can exist per magic-link claim
                // (user retried after dismissing the modal, two tabs, …).
                Payment::where('provider', 'razorpay')
                    ->where('status', Payment::STATUS_CREATED)
                    ->where('meta->kind', 'claim')
                    ->where('meta->entitlement_id', $entitlement->id)
                    ->lockForUpdate()
                    ->get()
                    ->each(function (Payment $stale) {
                        $staleOrder = $stale->payable;

                        if ($staleOrder instanceof Order && $staleOrder->status === 'pending') {
                            $staleOrder->update([
                                'status'                    => 'cancelled',
                                'payment_gateway_reference' => null,
                            ]);
                        }

                        $stale->update(['status' => Payment::STATUS_CANCELLED]);
                    });

                // 5. Server-side pricing — the client never tells us what was paid
                $rewardValue   = (float) $entitlement->reward_value;
                $gstPercentage = (float) ($product->gst_percentage ?? 0);
                $fiatDue       = max(0, round(((float) $finalPrice) - $rewardValue, 2));
                $gstAmount     = (float) $finalPrice - ((float) $finalPrice / (1 + ($gstPercentage / 100)));

                // 6. Create the order (address keys match what the modal sends: line1/name/mobile)
                $address = $validated['shipping_address'];

                $order = Order::create([
                    'company_id'              => $companyId,
                    'user_id'                 => $user->id,
                    'total_amount'            => $finalPrice,
                    'gst_total'               => round($gstAmount, 2),
                    'points_used'             => 0, // no wallet points burned on magic-link claims
                    'discount_amount'         => min($rewardValue, (float) $finalPrice),
                    'fiat_paid'               => 0, // set when payment is verified
                                                    // Always created 'pending': for free claims
                                                    // OrderFulfilmentService::fulfilClaimOrder flips it to
                                                    // 'paid' below. Creating it as 'paid' tripped the
                                                    // service's idempotent guard, so the entitlement was
                                                    // never marked claimed and the budget never released.
                    'status'                  => 'pending',
                    'shipping_name'           => $address['name'] ?? trim($user->first_name . ' ' . $user->last_name),
                    'shipping_mobile'         => $address['mobile'] ?? $user->mobile,
                    'shipping_address_line_1' => $address['line1'] ?? $address['address_line_1'] ?? null,
                    'shipping_city'           => $address['city'] ?? null,
                    'shipping_state'          => $address['state'] ?? null,
                    'shipping_pincode'        => $address['pincode'] ?? null,
                ]);

                OrderItem::create([
                    'order_id'            => $order->id,
                    'product_id'          => $product->id,
                    'product_variant_id'  => $variant?->id,
                    'product_name'        => $finalName,
                    'quantity'            => 1,
                    'unit_price'          => $finalPrice,
                    'unit_gst_percentage' => $gstPercentage,
                    'total_price'         => $finalPrice,
                    'delivery_status'     => $product->type === 'physical' ? 'pending' : 'instant',
                ]);

                // 7. Free claim → fulfil right now (same code the verify path
                //    uses). Fiat claims stay 'pending' and get their gateway
                //    order AFTER this transaction commits.
                if ($fiatDue <= 0) {
                    OrderFulfilmentService::fulfilClaimOrder($order, $entitlement->id, 0.0);
                }

                return [$order, $entitlement->id, $fiatDue];
            });

        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // ── Post-commit: create the gateway order (nothing DB-critical below,
        //    so a failure here can never roll back over a live gateway order).
        if ($fiatDue > 0) {
            $amountPaise = (int) round($fiatDue * 100);

            try {
                $payment = $this->razorpay->createOrderFor(
                    $order,
                    $amountPaise,
                    $order->order_number,
                    ['kind' => 'claim', 'entitlement_id' => $entitlementId]
                );
            } catch (PaymentException $e) {
                // Gateway unavailable: nothing was charged, and the just-created
                // pending order is still exclusively ours — undo it cleanly.
                $order->update(['status' => 'cancelled']);

                return response()->json([
                    'message' => 'Payment gateway is unavailable. Please try again in a moment.',
                ], 503);
            }

            $order->update(['payment_gateway_reference' => $payment->provider_order_id]);

            return response()->json([
                'message'           => 'Payment required to complete your claim.',
                'fulfilled'         => false,
                'payment_required'  => true,
                'order_number'      => $order->order_number,
                'razorpay_order_id' => $payment->provider_order_id,
                'razorpay_amount'   => $amountPaise,
                'razorpay_currency' => 'INR',
                'razorpay_key_id'   => config('services.razorpay.key_id'),
                'company_name'      => $order->company->name ?? 'Rewards',
                'user_name'         => trim($user->first_name . ' ' . $user->last_name),
                'user_email'        => $user->email,
            ], 201);
        }

        return response()->json([
            'message'      => 'Reward claimed successfully! Your order has been placed.',
            'fulfilled'    => true,
            'order_number' => $order->order_number,
        ], 201);
    }

    /**
     * 2b. Verify the Razorpay payment for a claim and fulfil it — exactly once.
     * POST /api/storefront/{slug}/user/claim/verify
     */
    public function verifyPayment(Request $request, $slug)
    {
        $user = $request->user();

        $validated = $request->validate([
            'razorpay_order_id'   => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature'  => 'required|string',
        ]);

        try {
            [$payment] = $this->razorpay->verifyAndFetch(
                $validated['razorpay_order_id'],
                $validated['razorpay_payment_id'],
                $validated['razorpay_signature']
            );

            $order = $payment->payable;

            if (($payment->meta['kind'] ?? null) !== 'claim' || ! $order instanceof Order) {
                return response()->json(['message' => 'This payment is not a claim payment.'], 400);
            }

            // The order must belong to the caller — a valid payment for someone
            // else's order must never fulfil from this endpoint.
            if ($order->user_id !== $user->id) {
                return response()->json(['message' => 'This payment belongs to a different order.'], 403);
            }

            $fiatPaidRupees = round($payment->amount_paise / 100, 2);

            try {
                $this->razorpay->fulfilOnce($payment, function () use ($payment, $order, $validated, $fiatPaidRupees) {
                    OrderFulfilmentService::fulfilClaimOrder(
                        $order,
                        (int) ($payment->meta['entitlement_id'] ?? 0),
                        $fiatPaidRupees
                    );
                    $order->update(['payment_gateway_reference' => $validated['razorpay_payment_id']]);
                });
            } catch (PaymentException $e) {
                // Money captured but the claim can't be fulfilled → refund, cancel, be honest.
                $this->razorpay->refundCaptured($payment, 'claim_fulfilment_failed: ' . $e->getMessage());
                $order->update(['status' => 'cancelled']);

                return response()->json([
                    'message'      => 'We could not complete your claim (' . $e->getMessage() . ') Your payment is being refunded.',
                    'order_number' => $order->order_number,
                ], 409);
            }

            return response()->json([
                'message'      => 'Payment verified successfully. Your reward has been claimed!',
                'order_number' => $order->order_number,
            ]);
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }
    }

    /**
     * 3. Fetch the restricted catalog for the Landing Page
     * GET /api/storefront/{slug}/user/claim/catalog
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
            ->whereIn('category_id', $company->activeCategoryIds())
            ->whereNotIn('category_id', $hiddenCategoryIds)
            ->whereNotIn('id', $hiddenProductIds);

        if (! empty($allowedIds) && is_array($allowedIds)) {
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
