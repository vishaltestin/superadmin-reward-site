<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'fiat_paid',
        'remaining_amount',
        'expires_at',      
        'reference_type',
        'reference_id',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'amount' => 'decimal:2',
            'fiat_paid' => 'decimal:2', 
            'remaining_amount' => 'decimal:2',
        ];
    }

    // The wallet this transaction belongs to
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    // What caused this transaction? (e.g., a specific Reward Campaign)
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}