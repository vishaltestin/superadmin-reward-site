<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
            'mrp'            => 'decimal:2',
            'selling_price'  => 'decimal:2',
            'attributes'     => 'array', 
            'is_active'      => 'boolean',
            'stock_quantity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    public function companyOverrides(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_product_variant')
            ->withPivot(['override_image', 'override_mrp', 'override_selling_price'])
            ->withTimestamps();
    }
}
