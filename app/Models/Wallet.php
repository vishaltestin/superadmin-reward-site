<?php

namespace App\Models;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Wallet extends Model
{
    protected $fillable = ['balance', 'is_active'];

    // This connects to either a User or a Company
    public function walletable(): MorphTo
    {
        return $this->morphTo();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

   /**
     * Add funds to the wallet safely.
     */
    public function credit(float $amount, string $description = null, Model $reference = null, \Carbon\Carbon $expiresAt = null, ?float $fiatPaid = null): Transaction
    {
        return DB::transaction(function () use ($amount, $description, $reference, $expiresAt, $fiatPaid) {
            $transaction = $this->transactions()->create([
                'type' => 'credit',
                'amount' => $amount,
                'remaining_amount' => $amount, // Initially, all of it is remaining
                'fiat_paid' => $fiatPaid,
                'expires_at' => $expiresAt,    // Optional expiry date
                'description' => $description,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference ? $reference->id : null,
            ]);

            $this->increment('balance', $amount);

            return $transaction;
        });
    }

    /**
     * Deduct funds using FIFO (Oldest Expiring Points First)
     */
    public function debit(float $amount, string $description = null, Model $reference = null, ?float $fiatPaid = null): Transaction
    {
        return DB::transaction(function () use ($amount, $description, $reference, $fiatPaid) {
            // 1. Lock the wallet row so no concurrent requests can read/write it
            $wallet = self::where('id', $this->id)->lockForUpdate()->first();

            if ($wallet->balance < $amount) {
                throw new \Exception("Insufficient funds in wallet.");
            }

            // 2. Create the Paper Trail (Debit Record)
            $transaction = $wallet->transactions()->create([
                'type' => 'debit',
                'amount' => $amount,
                'fiat_paid' => $fiatPaid,
                'remaining_amount' => 0, // Debits don't have remaining amounts
                'description' => $description,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference ? $reference->id : null,
            ]);

            // 3. FIFO Logic: Find available credits and consume them
            $amountToConsume = $amount;
            
            // Get credits that have money left, haven't expired, ordered by nearest expiry
            $credits = $wallet->transactions()
                ->where('type', 'credit')
                ->where('remaining_amount', '>', 0)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                })
                ->orderByRaw('expires_at IS NULL ASC, expires_at ASC') 
                ->lockForUpdate() // Lock the credit rows too
                ->get();

            foreach ($credits as $credit) {
                if ($amountToConsume <= 0) break; // We got all we need!

                if ($credit->remaining_amount >= $amountToConsume) {
                    $credit->decrement('remaining_amount', $amountToConsume);
                    $amountToConsume = 0;
                } else {
                    $amountToConsume -= $credit->remaining_amount;
                    $credit->update(['remaining_amount' => 0]);
                }
            }

            // 4. Finally, reduce the total locked wallet balance
            $wallet->decrement('balance', $amount);

            return $transaction;
        });
    }
}