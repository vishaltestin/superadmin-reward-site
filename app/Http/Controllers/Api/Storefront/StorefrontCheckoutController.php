<?php
namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CampaignEntitlement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorefrontCheckoutController extends Controller
{
    /**
     * Phase 1: Initialize Checkout (Points Escrow, Inclusive Tax, Save Items & Gateway Intent)
     */
    public function checkout(Request $request, $slug)
    {
        $user       = $request->user();
        $company    = $user->company;
        $multiplier = (float) ($company->point_multiplier ?? 1.00);

        $validated = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'points_to_burn'     => 'required|integer|min:0',
            'applied_coupon'     => 'nullable|string',
            'shipping_address'   => 'nullable|array',
            'billing_address'    => 'nullable|array',
        ]);

        try {
            return DB::transaction(function () use ($validated, $user, $company, $multiplier) {

                $totalRupeeCost = 0;
                $totalGstAmount = 0;
                $orderItemsData = [];

                // ==========================================
                // 1. CALCULATE CART VALUE & INCLUSIVE TAXES
                // ==========================================
                foreach ($validated['items'] as $item) {
                    $product = Product::with(['tierPrices', 'customCompanies' => function ($q) use ($company) {
                        $q->where('company_id', $company->id);
                    }])->where('is_active', true)->lockForUpdate()->findOrFail($item['product_id']);

                    $productPivot = $product->customCompanies->first()?->pivot;

                    $unitRupeePrice = $productPivot?->override_selling_price ?? $product->selling_price;
                    $productName    = $productPivot?->override_name ?? $product->name;
                    $gstPercentage  = $product->gst_percentage ?? 0.00;

                    if (! empty($item['variant_id'])) {
                        $variant = ProductVariant::with(['companyOverrides' => function ($q) use ($company) {
                            $q->where('company_id', $company->id);
                        }])->lockForUpdate()->findOrFail($item['variant_id']);

                        if ($variant->stock_quantity < $item['quantity']) {
                            throw new Exception("Stock exhausted for variant: {$variant->name}");
                        }

                        $variantPivot   = $variant->companyOverrides->first()?->pivot;
                        $unitRupeePrice = $variantPivot?->override_selling_price ?? $variant->selling_price ?? $unitRupeePrice;
                        $productName    = $productName . ' - ' . $variant->name;
                    }

                    $applicableTiers = $product->tierPrices
                        ->where('min_quantity', '<=', $item['quantity'])
                        ->filter(function ($t) use ($item) {
                            return is_null($t->product_variant_id) || $t->product_variant_id == $item['variant_id'];
                        })->sortByDesc('min_quantity');

                    if ($applicableTiers->isNotEmpty()) {
                        $unitRupeePrice = $applicableTiers->first()->selling_price;
                    }

                    // --- THE FIX: TAX INCLUSIVE MATH ---
                    // 1. Calculate the raw total (exactly matching frontend)
                    $lineTotal = $unitRupeePrice * $item['quantity'];

                    // 2. Extract the GST component inside that total for invoice tracking
                    // Formula: GST = Total - (Total / (1 + Rate))
                    $lineGstAmount = $lineTotal - ($lineTotal / (1 + ($gstPercentage / 100)));

                    $totalRupeeCost += $lineTotal;
                    $totalGstAmount += $lineGstAmount;

                    $orderItemsData[] = [
                        'product_id'          => $product->id,
                        'product_variant_id'  => $item['variant_id'] ?? null,
                        'product_name'        => $productName,
                        'quantity'            => $item['quantity'],
                        'unit_price'          => $unitRupeePrice,
                        'unit_gst_percentage' => $gstPercentage,
                        'total_price'         => $lineTotal,
                        'delivery_status'     => $product->type === 'physical' ? 'pending' : 'instant',
                    ];
                }

                // ==========================================
                // 2. VALIDATE COUPONS
                // ==========================================
                $couponDiscountFiat = 0;
                $appliedEntitlement = null;

                if (! empty($validated['applied_coupon'])) {
                    $appliedEntitlement = CampaignEntitlement::where('claim_code', $validated['applied_coupon'])
                        ->where('issued_to_user_id', $user->id)
                        ->where('is_claimed', false)
                        ->where(function ($q) {
                            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                        })
                        ->lockForUpdate() // Prevents double-claiming in 2 browser tabs
                        ->first();

                    if (! $appliedEntitlement) {
                        throw new Exception("Invalid, expired, or already claimed coupon code.");
                    }

                    $couponDiscountFiat = $appliedEntitlement->reward_value;
                }

                $totalAfterCoupon = max(0, $totalRupeeCost - $couponDiscountFiat);

                // ==========================================
                // 3. VALIDATE & ESCROW POINTS
                // ==========================================
                $pointsToBurn = $validated['points_to_burn'];
                if ($user->wallet->balance < $pointsToBurn) {
                    throw new Exception("Insufficient points balance.");
                }

                $pointsRupeeValue = $pointsToBurn / $multiplier;
                if ($pointsRupeeValue > $totalAfterCoupon) {
                    throw new Exception("Cannot burn more points than the order value.");
                }

                $remainingFiatToPay = $totalAfterCoupon - $pointsRupeeValue;

                if ($pointsToBurn > 0) {
                    $user->wallet->debit(
                        amount: $pointsToBurn,
                        description: "Points held for Checkout",
                        fiatPaid: 0
                    );
                }

                // ==========================================
                // 4. CREATE ORDER
                // ==========================================
                $initialStatus = $remainingFiatToPay <= 0 ? 'paid' : 'pending';

                $order = Order::create([
                    'company_id'               => $company->id,
                    'user_id'                  => $user->id,
                    'total_amount'             => $totalRupeeCost,
                    'gst_total'                => $totalGstAmount,
                    'points_used'              => $pointsToBurn,
                    'coupon_code'              => $validated['applied_coupon'] ?? null,
                    'discount_amount'          => $couponDiscountFiat,
                    'fiat_paid'                => $remainingFiatToPay,
                    'status'                   => $initialStatus,
                    'shipping_name'            => $validated['shipping_address']['name'] ?? null,
                    'shipping_mobile'          => $validated['shipping_address']['mobile'] ?? null,
                    'shipping_address_line_1'  => $validated['shipping_address']['line1'] ?? null,
                    'shipping_city'            => $validated['shipping_address']['city'] ?? null,
                    'shipping_state'           => $validated['shipping_address']['state'] ?? null,
                    'shipping_pincode'         => $validated['shipping_address']['pincode'] ?? null,
                    'billing_address_snapshot' => $validated['billing_address'] ?? null,
                ]);

                foreach ($orderItemsData as $itemData) {
                    $order->items()->create($itemData);
                }

                // ==========================================
                // 5. POST-ORDER
                // ==========================================
                $gatewayReferenceId = null;

                if ($remainingFiatToPay > 0) {
                    $gatewayReferenceId = 'MOCK_GATEWAY_ID_' . uniqid();
                    $order->update(['payment_gateway_reference' => $gatewayReferenceId]);
                } else {
                    if ($appliedEntitlement) {
                        $appliedEntitlement->update(['is_claimed' => true, 'claimed_at' => now()]);
                        $appliedEntitlement->campaign->decrement('budget_locked', $appliedEntitlement->reward_value);
                    }
                    foreach ($orderItemsData as $item) {
                        if ($item['product_variant_id']) {
                            ProductVariant::where('id', $item['product_variant_id'])
                                ->decrement('stock_quantity', $item['quantity']);
                        }
                    }
                }

                return response()->json([
                    'message'           => 'Checkout initialized.',
                    'order_number'      => $order->order_number,
                    'fiat_amount_due'   => $remainingFiatToPay,
                    'points_burned'     => $pointsToBurn,
                    'gateway_reference' => $gatewayReferenceId,
                    'status'            => $initialStatus,
                ], 201);
            });

        } catch (Exception $e) {
            return response()->json(['message' => 'Checkout failed.', 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Phase 2: Verify Payment
     */
    public function verifyPayment(Request $request, $slug)
    {
        $validated = $request->validate([
            'order_number' => 'required|exists:orders,order_number',
            'payment_id'   => 'required|string',
        ]);

        $order = Order::where('order_number', $validated['order_number'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Order is already processed.'], 400);
        }

        DB::transaction(function () use ($order, $validated) {
            $order->update([
                'status'                    => 'processing',
                'payment_gateway_reference' => $validated['payment_id'],
            ]);

            if ($order->coupon_code) {
                $entitlement = CampaignEntitlement::where('claim_code', $order->coupon_code)
                    ->where('issued_to_user_id', $order->user_id)
                    ->where('is_claimed', false)
                    ->first();

                if ($entitlement) {
                    $entitlement->update([
                        'is_claimed' => true,
                        'claimed_at' => now(),
                    ]);

                    $entitlement->campaign->decrement('budget_locked', $entitlement->reward_value);
                }
            }

            foreach ($order->items as $item) {
                if ($item->product_variant_id) {
                    ProductVariant::where('id', $item->product_variant_id)
                        ->decrement('stock_quantity', $item->quantity);
                }
            }
        });

        return response()->json([
            'message'      => 'Payment verified successfully.',
            'order_number' => $order->order_number,
        ]);
    }
}
