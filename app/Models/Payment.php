<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    public const STATUS_CREATED   = 'created';
    public const STATUS_PAID      = 'paid';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_REFUNDED  = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'payable_type', 'payable_id', 'provider',
        'provider_order_id', 'provider_payment_id',
        'amount_paise', 'currency', 'status', 'meta',
        'paid_at', 'failed_at', 'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'meta'         => 'array',
            'paid_at'      => 'datetime',
            'failed_at'    => 'datetime',
            'refunded_at'  => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
