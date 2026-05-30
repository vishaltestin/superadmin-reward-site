<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyProductTierPrice extends Model
{
    protected $table = 'company_product_tier_prices';

    protected $fillable = [
        'company_id',
        'product_id',
        'product_variant_id',
        'min_quantity',
        'selling_price',
    ];

    protected function casts(): array
    {
        return [
            'min_quantity'  => 'integer',
            'selling_price' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
