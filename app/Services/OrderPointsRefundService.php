<?php
namespace App\Services;

use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderPointsRefundService
{
    /**
     * Return a failed/cancelled order's wallet points exactly once.
     *
     * New orders are linked to their debit transaction, so Wallet can restore
     * the original source lots. For orders created before that linkage existed,
     * preserve the old balance-refund behavior but mark the credit unattributed.
     */
    public function refund(Order $order, string $description): float
    {
        return DB::transaction(function () use ($order, $description) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingRefund = DB::table('wallet_order_refunds')
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->first();

            if ($existingRefund) {
                return 0.0;
            }

            $pointsUsed = round((float) $lockedOrder->points_used, 2);
            $user       = $lockedOrder->user()->first();
            $wallet     = $user?->wallet()->lockForUpdate()->first();

            if (! $wallet) {
                throw new RuntimeException("Cannot refund points for order {$lockedOrder->id}: rewardee wallet was not found.");
            }

            $debit        = null;
            $refundAmount = 0.0;

            if ($pointsUsed > 0) {
                $debit = Transaction::query()
                    ->where('wallet_id', $wallet->id)
                    ->where('type', 'debit')
                    ->where('reference_type', Order::class)
                    ->where('reference_id', $lockedOrder->id)
                    ->lockForUpdate()
                    ->first();

                if ($debit) {
                    // The debit amount is authoritative for the balance actually
                    // removed; refundDebit restores each original source lot.
                    $refundAmount = round((float) $debit->amount, 2);
                    $wallet->refundDebit($debit, $description);
                } else {
                    // Pre-migration checkout debits were made before Order::create
                    // and have no order reference. Restore the order's known
                    // points_used value, but never claim campaign attribution.
                    $refundAmount = $pointsUsed;
                    $wallet->transactions()->create([
                        'type'                         => 'credit',
                        'amount'                       => $refundAmount,
                        'remaining_amount'             => $refundAmount,
                        'fiat_paid'                    => 0,
                        'expires_at'                   => null,
                        'description'                  => $description . ' (legacy order; source unavailable)',
                        'reference_type'               => null,
                        'reference_id'                 => null,
                        'allocation_tracking_complete' => false,
                    ]);
                    $wallet->increment('balance', $refundAmount);
                }
            }

            DB::table('wallet_order_refunds')->insert([
                'order_id'             => $lockedOrder->id,
                'debit_transaction_id' => $debit?->id,
                'amount'               => $refundAmount,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);

            return $refundAmount;
        });
    }
}
