<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTierPrice extends Model
{
    protected $fillable = [
        'product_id',
        'min_quantity',
        'selling_price',
    ];

    protected function casts(): array
    {
        return [
            'min_quantity' => 'integer',
            'selling_price' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}