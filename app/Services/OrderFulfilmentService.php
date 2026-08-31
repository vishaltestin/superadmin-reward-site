<?php
namespace App\Services;

use App\Exceptions\PaymentException;
use App\Models\CampaignEntitlement;
use App\Models\Order;
use App\Models\ProductVariant;

/**
 * Business fulfilment for paid orders. Every function must be called inside
 * a database transaction (RazorpayPaymentService::fulfilOnce or the free-order
 * path in the controllers) — it is deliberately re-entrant/idempotent so the
 * client-verify path and the webhook path can safely share it.
 */
class OrderFulfilmentService
{
    /**
     * Magic-link claim order: consume the entitlement, release campaign
     * escrow, take stock, mark the order paid.
     */
    public static function fulfilClaimOrder(Order $order, int $entitlementId, ?float $fiatPaidRupees = null): void
    {
        if (in_array($order->status, ['paid', 'processing', 'shipped', 'completed'])) {
            return; // already fulfilled — idempotent guard
        }

        $entitlement = CampaignEntitlement::whereKey($entitlementId)->lockForUpdate()->first();

        if (! $entitlement) {
            throw new PaymentException('Reward entitlement not found.', 409);
        }

        if ($entitlement->is_claimed) {
            // Another order consumed this reward first (two tabs / retried claim).
            // Payment for THIS order must be refunded by the caller.
            throw new PaymentException('This reward has already been claimed.', 409);
        }

        self::decrementStock($order);

        $entitlement->update([
            'is_claimed' => true,
            'claimed_at' => now(),
        ]);

        $entitlement->campaign->decrement('budget_locked', $entitlement->reward_value);

        // Reward value > product price: the unspent remainder of the reward
        // is deposited into the employee's wallet — the promise the claim
        // checkout UI makes ("left over will be deposited into your wallet
        // for future purchases"). discount_amount is the portion of the
        // reward the order actually consumed, so leftover = reward − used.
        $rewardValue = (float) $entitlement->reward_value;
        $rewardUsed  = min($rewardValue, (float) ($order->discount_amount ?? 0));
        $leftover    = round($rewardValue - $rewardUsed, 2);

        if ($leftover > 0.01) {
            $user = $order->user()->first();
            if ($user) {
                $user->wallet()->firstOrCreate([], ['balance' => 0]);
                $user->wallet->credit(
                    $leftover,
                    'Leftover reward balance from campaign: ' . $entitlement->campaign->name
                );
            }
        }

        $order->update(array_filter([
            'status'    => 'paid',
            'fiat_paid' => $fiatPaidRupees,
        ], fn($value) => $value !== null));
    }

    /**
     * Cart checkout order: apply the coupon entitlement (if any), take stock,
     * mark the order paid. Wallet points were already escrowed at creation.
     */
    public static function fulfilCartOrder(Order $order, ?string $providerPaymentId = null): void
    {
        if (in_array($order->status, ['paid', 'processing', 'shipped', 'completed'])) {
            return; // already fulfilled — idempotent guard
        }

        self::decrementStock($order);

        if ($order->coupon_code) {
            $entitlement = CampaignEntitlement::where('claim_code', $order->coupon_code)
                ->where('issued_to_user_id', $order->user_id)
                ->where('is_claimed', false)
                ->lockForUpdate()
                ->first();

            if (! $entitlement) {
                // Coupon was consumed by another order between creation and payment.
                throw new PaymentException('This promo code has already been used. Your payment will be refunded.', 409);
            }

            $entitlement->update(['is_claimed' => true, 'claimed_at' => now()]);
            $entitlement->campaign->decrement('budget_locked', $entitlement->reward_value);
        }

        $order->update(array_filter([
            'status'                    => 'paid',
            'payment_gateway_reference' => $providerPaymentId,
        ], fn($value) => $value !== null));
    }

    /**
     * Stock decrement guarded at SQL level so concurrent fulfilments can
     * never push stock negative. Throws (→ triggers refund) if insufficient.
     */
    private static function decrementStock(Order $order): void
    {
        foreach ($order->items as $item) {
            if (! $item->product_variant_id) {
                continue;
            }

            $decremented = ProductVariant::whereKey($item->product_variant_id)
                ->where('stock_quantity', '>=', $item->quantity)
                ->decrement('stock_quantity', $item->quantity);

            if (! $decremented) {
                throw new PaymentException("Stock exhausted for {$item->product_name}. Your payment will be refunded.", 409);
            }
        }
    }
}
