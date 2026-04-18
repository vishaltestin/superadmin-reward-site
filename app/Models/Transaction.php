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
        'reference_type',
        'reference_id',
        'description',
    ];

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