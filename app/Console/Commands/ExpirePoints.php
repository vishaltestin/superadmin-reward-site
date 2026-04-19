<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePoints extends Command
{
    protected $signature = 'points:expire';
    protected $description = 'Sweeps the database for expired points and deducts them from user wallets.';

    public function handle()
    {
        $this->info('Starting points expiry sweep...');

        // Find all credits that have passed their expiry date but still have a balance
        $expiredCredits = Transaction::where('type', 'credit')
            ->where('remaining_amount', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with('wallet')
            ->get();

        if ($expiredCredits->isEmpty()) {
            $this->info('No expired points found. Clean sheet!');
            return;
        }

        $count = 0;

        foreach ($expiredCredits as $credit) {
            DB::transaction(function () use ($credit, &$count) {
                $wallet = $credit->wallet;
                $amountToExpire = $credit->remaining_amount;

                // 1. Set the credit's remaining amount to 0
                $credit->update(['remaining_amount' => 0]);

                // 2. Create a system debit transaction to explain where the points went
                $wallet->transactions()->create([
                    'type' => 'debit',
                    'amount' => $amountToExpire,
                    'remaining_amount' => 0,
                    'description' => 'System Auto-Debit: Points Expired',
                ]);

                // 3. Deduct from the user's total balance
                $wallet->decrement('balance', $amountToExpire);
                
                $count++;
            });
        }

        $this->info("Successfully expired {$count} old point allocations.");
    }
}

// TODO
// (Note: In production, you would add $schedule->command('points:expire')->daily(); to your routes/console.php).