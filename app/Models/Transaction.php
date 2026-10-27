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
        'reversal_of_transaction_id',
        'allocation_tracking_complete',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'                   => 'datetime',
            'amount'                       => 'decimal:2',
            'fiat_paid'                    => 'decimal:2',
            'remaining_amount'             => 'decimal:2',
            'allocation_tracking_complete' => 'boolean',
        ];
    }

    public function debitAllocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WalletTransactionAllocation::class, 'debit_transaction_id');
    }

    public function creditAllocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WalletTransactionAllocation::class, 'credit_transaction_id');
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
