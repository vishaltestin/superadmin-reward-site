<?php
namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Payment;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelStalePendingOrders extends Command
{
    protected $signature   = 'orders:cancel-stale {--hours=24 : Age in hours before a pending order is considered abandoned}';
    protected $description = 'Cancels abandoned pending orders and refunds escrowed wallet points.';

    public function handle()
    {
        $hours  = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours);

        $staleOrders = Order::where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('payments', fn($q) => $q->where('status', Payment::STATUS_PAID))
            ->with('user.wallet')
            ->get();

        if ($staleOrders->isEmpty()) {
            $this->info('No stale pending orders found.');
            return self::SUCCESS;
        }

        $cancelled = 0;
        $refunded  = 0;

        foreach ($staleOrders as $staleOrder) {
            try {
                DB::transaction(function () use ($staleOrder, &$cancelled, &$refunded) {
                    $order = Order::whereKey($staleOrder->id)->lockForUpdate()->first();

                    if (! $order || $order->status !== 'pending') {
                        return;
                    }

                    // Re-check under lock: a captured payment must never be cancelled here.
                    $hasPaidPayment = $order->payments()
                        ->where('status', Payment::STATUS_PAID)
                        ->exists();

                    if ($hasPaidPayment) {
                        return;
                    }

                    $order->payments()
                        ->where('status', Payment::STATUS_CREATED)
                        ->update(['status' => Payment::STATUS_CANCELLED]);

                    if ($order->points_used > 0 && $order->user?->wallet) {
                        $order->user->wallet->credit(
                            amount: $order->points_used,
                            description: "Points refunded — abandoned order {$order->order_number} cancelled",
                            fiatPaid: 0
                        );
                        $refunded += $order->points_used;
                    }

                    $order->update([
                        'status'                    => 'cancelled',
                        'payment_gateway_reference' => null,
                    ]);

                    $cancelled++;
                });
            } catch (Exception $e) {
                Log::error("Failed to cancel stale order {$staleOrder->order_number}: " . $e->getMessage());
                $this->error("Failed to cancel order {$staleOrder->order_number}");
            }
        }

        $this->info("Cancelled {$cancelled} stale orders; refunded {$refunded} escrowed points.");

        return self::SUCCESS;
    }
}
