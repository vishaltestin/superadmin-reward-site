<?php

namespace App\Traits;

use App\Models\Wallet;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasWallet
{
    /**
     * Get the entity's wallet.
     */
    public function wallet(): MorphOne
    {
        return $this->morphOne(Wallet::class, 'walletable');
    }

    /**
     * Helper method to get balance instantly without querying the relationship deeply.
     */
    public function getBalanceAttribute(): float
    {
        return $this->wallet ? $this->wallet->balance : 0.00;
    }
}