<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'image',
        'mrp',
        'selling_price',
        'attributes',
        'stock_quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'mrp' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'attributes' => 'array', // Auto-cast JSON to PHP array
            'is_active' => 'boolean',
            'stock_quantity' => 'integer',
        ];
    }

    // A variant belongs to one parent product
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}