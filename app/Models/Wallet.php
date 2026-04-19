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
        if ($this->balance < $amount) {
            throw new \Exception("Insufficient funds in wallet.");
        }

        return DB::transaction(function () use ($amount, $description, $reference, $fiatPaid) {
            // 1. Create the Paper Trail (Debit Record)
            $transaction = $this->transactions()->create([
                'type' => 'debit',
                'amount' => $amount,
                'fiat_paid' => $fiatPaid,
                'remaining_amount' => 0, // Debits don't have remaining amounts
                'description' => $description,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference ? $reference->id : null,
            ]);

            // 2. FIFO Logic: Find available credits and consume them
            $amountToConsume = $amount;
            
            // Get credits that have money left, haven't expired, ordered by nearest expiry
            $credits = $this->transactions()
                ->where('type', 'credit')
                ->where('remaining_amount', '>', 0)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                })
                // Prioritize expiring points first, then non-expiring points
                ->orderByRaw('expires_at IS NULL ASC, expires_at ASC') 
                ->lockForUpdate() // Prevent double-spending race conditions
                ->get();

            foreach ($credits as $credit) {
                if ($amountToConsume <= 0) break; // We got all we need!

                if ($credit->remaining_amount >= $amountToConsume) {
                    // This credit can cover the rest of the bill
                    $credit->decrement('remaining_amount', $amountToConsume);
                    $amountToConsume = 0;
                } else {
                    // This credit isn't enough, drain it completely and keep looking
                    $amountToConsume -= $credit->remaining_amount;
                    $credit->update(['remaining_amount' => 0]);
                }
            }

            // 3. Finally, reduce the total wallet balance
            $this->decrement('balance', $amount);

            return $transaction;
        });
    }
}