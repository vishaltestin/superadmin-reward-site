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
    public function credit(float $amount, string $description = null, Model $reference = null): Transaction
    {
        return DB::transaction(function () use ($amount, $description, $reference) {
            // 1. Create the paper trail
            $transaction = $this->transactions()->create([
                'type' => 'credit',
                'amount' => $amount,
                'description' => $description,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference ? $reference->id : null,
            ]);

            // 2. Safely increase the balance
            $this->increment('balance', $amount);

            return $transaction;
        });
    }

    /**
     * Deduct funds from the wallet safely.
     */
    public function debit(float $amount, string $description = null, Model $reference = null): Transaction
    {
        if ($this->balance < $amount) {
            throw new Exception("Insufficient funds in wallet.");
        }

        return DB::transaction(function () use ($amount, $description, $reference) {
            $transaction = $this->transactions()->create([
                'type' => 'debit',
                'amount' => $amount,
                'description' => $description,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference ? $reference->id : null,
            ]);

            // Safely decrease the balance
            $this->decrement('balance', $amount);

            return $transaction;
        });
    }
}