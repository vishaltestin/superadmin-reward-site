<?php
namespace App\Console\Commands;

use App\Models\Transaction;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpirePoints extends Command
{
    protected $signature   = 'points:expire';
    protected $description = 'Sweeps the database for expired points and deducts them from user wallets safely.';

    public function handle()
    {
        $this->info('Starting points expiry sweep...');

        $expiredCredits = Transaction::where('type', 'credit')
            ->where('remaining_amount', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        if ($expiredCredits->isEmpty()) {
            $this->info('No expired points found. Clean sheet!');
            return self::SUCCESS;
        }

        $count        = 0;
        $totalExpired = 0;

        foreach ($expiredCredits as $credit) {
            try {
                DB::transaction(function () use ($credit, &$count, &$totalExpired) {
                    $lockedCredit = Transaction::where('id', $credit->id)
                        ->lockForUpdate()
                        ->first();

                    if ($lockedCredit && $lockedCredit->remaining_amount > 0) {
                        $wallet          = $lockedCredit->wallet;
                        $ledgerRemaining = (float) $lockedCredit->remaining_amount;
                        $walletBalance   = (float) $wallet->balance;

                        $amountToExpire = min($ledgerRemaining, max(0.0, $walletBalance));

                        if ($walletBalance < $ledgerRemaining) {
                            Log::critical('Wallet balance below ledger remaining on expiry — clamping.', [
                                'wallet_id'        => $wallet->id,
                                'balance'          => $walletBalance,
                                'ledger_remaining' => $ledgerRemaining,
                                'transaction_id'   => $lockedCredit->id,
                            ]);
                        }

                        $lockedCredit->update(['remaining_amount' => 0]);

                        if ($amountToExpire > 0) {
                            $wallet->transactions()->create([
                                'type'             => 'debit',
                                'amount'           => $amountToExpire,
                                'remaining_amount' => 0,
                                'description'      => 'System Auto-Debit: Points Expired',
                            ]);

                            $wallet->decrement('balance', $amountToExpire);
                        }

                        $count++;
                        $totalExpired += $amountToExpire;
                    }
                });
            } catch (Exception $e) {
                Log::error("Failed to expire points for transaction ID {$credit->id}: " . $e->getMessage());
            }
        }

        $this->info("Successfully expired {$totalExpired} points across {$count} old point allocations.");

        return self::SUCCESS;
    }
}
