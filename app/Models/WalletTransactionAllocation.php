<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransactionAllocation extends Model
{
    protected $fillable = [
        'debit_transaction_id',
        'credit_transaction_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function debitTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'debit_transaction_id');
    }

    public function creditTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'credit_transaction_id');
    }
}
