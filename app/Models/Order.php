<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'user_id',
        'order_number',
        'total_amount',
        'gst_total',
        'points_used',
        'fiat_paid',
        'payment_gateway_reference',
        'status',

        'shipping_name', 
        'shipping_mobile', 
        'shipping_address_line_1', 
        'shipping_address_line_2', 
        'shipping_city', 
        'shipping_state', 
        'shipping_pincode',
        'billing_address_snapshot',
        'logistics_provider', 
        'tracking_number'
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'gst_total' => 'decimal:2',
            'fiat_paid' => 'decimal:2',
            'points_used' => 'integer',
            'billing_address_snapshot' => 'array',
        ];
    }

    // Auto-generate a clean Order Number before saving
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = 'ORD-' . strtoupper(uniqid());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}