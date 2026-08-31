<?php
namespace App\Http\Controllers\Api\Storefront;

use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\CampaignEntitlement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OrderFulfilmentService;
use App\Services\PricingService;
use App\Services\RazorpayPaymentService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StorefrontCheckoutController extends Controller
{
    private RazorpayPaymentService $razorpay;

    public function __construct()
    {
        $this->razorpay = app(RazorpayPaymentService::class);
    }

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

        $allowedCategoryIds = $company->activeCategoryIds();
        $hiddenCategoryIds  = $company->hidden_category_ids ?? [];
        $hiddenProductIds   = $company->hidden_product_ids ?? [];

        try {
            [$order, $pointsToBurn, $remainingFiatToPay] = DB::transaction(function () use ($validated, $user, $company, $multiplier, $allowedCategoryIds, $hiddenCategoryIds, $hiddenProductIds) {

                $totalRupeeCost = 0;
                $totalGstAmount = 0;
                $orderItemsData = [];

                foreach ($validated['items'] as $item) {
                    $product = Product::with([
                        'customCompanies' => fn($q) => $q->where('company_id', $company->id),
                    ])
                        ->where('id', $item['product_id'])
                        ->where('is_active', true)
                        ->whereIn('category_id', $allowedCategoryIds)
                        ->whereNotIn('category_id', $hiddenCategoryIds)
                        ->whereNotIn('id', $hiddenProductIds)
                        ->whereDoesntHave('customCompanies', function ($q) use ($company) {
                            $q->where('company_id', $company->id)->where('is_excluded', true);
                        })
                        ->lockForUpdate()
                        ->first();

                    if (! $product) {
                        throw new Exception('One or more items in your cart are not available in this store.');
                    }

                    $productPivot  = $product->customCompanies->first()?->pivot;
                    $productName   = $productPivot?->override_name ?? $product->name;
                    $gstPercentage = $product->gst_percentage ?? 0.00;

                    $variant = null;

                    if (! empty($item['variant_id'])) {
                        $variant = ProductVariant::with([
                            'companyOverrides' => fn($q) => $q->where('company_id', $company->id),
                        ])->lockForUpdate()->findOrFail($item['variant_id']);

                        if ((int) $variant->product_id !== (int) $product->id) {
                            throw new Exception('Invalid product variant selected.');
                        }

                        if ($variant->stock_quantity < $item['quantity']) {
                            throw new Exception("Stock exhausted for variant: {$variant->name}");
                        }

                        $productName = $productName . ' - ' . $variant->name;
                    }

                    $unitRupeePrice = PricingService::unitPrice(
                        $product,
                        $variant,
                        $company,
                        (int) $item['quantity']
                    );

                    $lineTotal     = $unitRupeePrice * $item['quantity'];
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

                $couponDiscountFiat = 0;
                $appliedEntitlement = null;

                if (! empty($validated['applied_coupon'])) {
                    $appliedEntitlement = CampaignEntitlement::with('campaign')
                        ->where('claim_code', $validated['applied_coupon'])
                        ->where('issued_to_user_id', $user->id)
                        ->where('is_claimed', false)
                        ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                        ->lockForUpdate()
                        ->first();

                    if (! $appliedEntitlement) {
                        throw new Exception("Invalid, expired, or already claimed coupon code.");
                    }

                    $couponConfig = $appliedEntitlement->campaign->config_json ?? [];

                    if (! empty($couponConfig['catalog_selection']) && is_array($couponConfig['catalog_selection'])) {
                        $cartProductIds    = array_column($validated['items'], 'product_id');
                        $unauthorizedItems = array_diff($cartProductIds, $couponConfig['catalog_selection']);

                        if (! empty($unauthorizedItems)) {
                            throw new Exception('Your cart contains items that are not eligible for this promo code.');
                        }
                    }

                    $couponDiscountFiat = $appliedEntitlement->reward_value;
                }

                $totalAfterCoupon = max(0, $totalRupeeCost - $couponDiscountFiat);

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
                        description: "Points escrowed for Order checkout",
                        fiatPaid: 0
                    );
                }

                $initialStatus = 'pending';

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

                if ($remainingFiatToPay <= 0) {
                    OrderFulfilmentService::fulfilCartOrder($order);
                }

                return [$order, $pointsToBurn, $remainingFiatToPay];
            });

        } catch (Exception $e) {
            return response()->json(['message' => 'Checkout failed.', 'error' => $e->getMessage()], 422);
        }

        $razorpayOrderId = null;
        $razorpayAmount  = null;

        if ($remainingFiatToPay > 0) {
            $amountInPaise = (int) round($remainingFiatToPay * 100);

            try {
                $payment = $this->razorpay->createOrderFor(
                    $order,
                    $amountInPaise,
                    $order->order_number,
                    ['kind' => 'cart']
                );
            } catch (PaymentException $e) {
                DB::transaction(function () use ($order, $user) {
                    if ($order->points_used > 0) {
                        $user->wallet->credit(
                            amount: $order->points_used,
                            description: "Points refunded — payment gateway unavailable for order {$order->order_number}",
                            fiatPaid: 0
                        );
                    }
                    $order->update(['status' => 'cancelled']);
                });

                return response()->json([
                    'message' => 'Payment gateway is unavailable. Please try again in a moment.',
                    'error'   => $e->getMessage(),
                ], 503);
            }

            $razorpayOrderId = $payment->provider_order_id;
            $razorpayAmount  = $amountInPaise;

            $order->update(['payment_gateway_reference' => $razorpayOrderId]);
        }

        return response()->json([
            'message'           => 'Checkout initialized.',
            'order_number'      => $order->order_number,
            'fiat_amount_due'   => $remainingFiatToPay,
            'points_burned'     => $pointsToBurn,
            'gateway_reference' => $razorpayOrderId,
            'status'            => $order->status,
            'razorpay_order_id' => $razorpayOrderId,
            'razorpay_amount'   => $razorpayAmount,
            'razorpay_currency' => 'INR',
            'razorpay_key_id'   => $remainingFiatToPay > 0 ? config('services.razorpay.key_id') : null,
            'company_name'      => $order->company->name ?? 'Store',
            'user_name'         => $order->user->name ?? '',
            'user_email'        => $order->user->email ?? '',
        ], 201);
    }

    public function verifyPayment(Request $request, $slug)
    {
        $user = $request->user();

        $validated = $request->validate([
            'order_number'        => 'required|exists:orders,order_number',
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

            if (($payment->meta['kind'] ?? null) !== 'cart' || ! $order instanceof Order) {
                return response()->json(['message' => 'This payment is not a checkout payment.'], 400);
            }

            if ($order->user_id !== $user->id || $order->order_number !== $validated['order_number']) {
                return response()->json(['message' => 'This payment belongs to a different order.'], 403);
            }

            try {
                $this->razorpay->fulfilOnce($payment, function () use ($payment, $order, $validated) {
                    OrderFulfilmentService::fulfilCartOrder($order, $validated['razorpay_payment_id']);
                });
            } catch (PaymentException $e) {
                $this->razorpay->refundCaptured($payment, 'cart_fulfilment_failed: ' . $e->getMessage());

                if ($order->points_used > 0) {
                    $user->wallet->credit(
                        amount: $order->points_used,
                        description: "Points refunded — order {$order->order_number} could not be fulfilled"
                    );
                }
                $order->update(['status' => 'cancelled']);

                return response()->json([
                    'message'      => 'We could not complete your order (' . $e->getMessage() . ') Your payment is being refunded.',
                    'order_number' => $order->order_number,
                ], 409);
            }

            return response()->json([
                'message'      => 'Payment verified successfully.',
                'order_number' => $order->order_number,
            ]);
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }
    }

    public function cancelOrder(Request $request, $slug)
    {
        $validated = $request->validate([
            'order_number' => 'required|exists:orders,order_number',
        ]);

        $order = Order::with('items')
            ->where('order_number', $validated['order_number'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($order->status !== 'pending') {
            return response()->json([
                'message'      => 'Order cannot be cancelled.',
                'order_number' => $order->order_number,
                'status'       => $order->status,
            ], 409);
        }

        DB::transaction(function () use ($order, $request) {
            $order->payments()
                ->where('status', \App\Models\Payment::STATUS_CREATED)
                ->update(['status' => \App\Models\Payment::STATUS_CANCELLED]);

            if ($order->points_used > 0) {
                $request->user()->wallet->credit(
                    amount: $order->points_used,
                    description: "Points refunded — Order {$order->order_number} cancelled",
                    reference: null,
                    expiresAt: null,
                    fiatPaid: 0
                );
            }

            $order->update([
                'status'                    => 'cancelled',
                'payment_gateway_reference' => null,
            ]);
        });

        return response()->json([
            'message'         => 'Order cancelled and points refunded.',
            'order_number'    => $order->order_number,
            'points_refunded' => $order->points_used,
        ]);
    }
}
