<?php
namespace App\Models;

use App\Helpers\VariantAttributeHelper;
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
        'gallery_images',
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
            'gallery_images' => 'array',
            'is_active'      => 'boolean',
            'stock_quantity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Attribute keys are always stored normalized ("size" / "Size" / "SIZE" -> "Size"),
        // no matter which code path saves the variant (admin panel, API, imports...).
        static::saving(function (ProductVariant $variant): void {
            $attributes = $variant->getAttribute('attributes');

            if (is_array($attributes)) {
                $variant->setAttribute('attributes', VariantAttributeHelper::normalizeMap($attributes));
            }
        });
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
