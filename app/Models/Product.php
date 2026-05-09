<?php

namespace App\Models;

use App\Helpers\SlugHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', // Don't forget to add this back to fillable!
        'type',
        'name',
        'slug',
        'sku',
        'brand_id',       // <-- Replaced 'brand'
        'warranty_info',  // <-- Added
        'brand',
        'mrp',
        'selling_price',
        'gst_percentage', // <-- Added
        'short_description',
        'long_description',
        'key_features',
        'terms_and_conditions',
        'main_image',
        'gallery_images',
        'video_url',
        'specifications',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'is_active',
        'sort_order',
        'type_data',
    ];

    protected function casts(): array
    {
        return [
            'mrp' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'gst_percentage' => 'decimal:2', // <-- Added
            'key_features' => 'array',
            'gallery_images' => 'array',
            'tags' => 'array', 
            'specifications' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'type_data' => 'array',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = SlugHelper::generateUniqueSlug(self::class, $product->name);
            }
        });
    }

    // 1. The Main Breadcrumb Category
    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
    
    // 2. The Tagged/Bonus Categories
    public function secondaryCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }
    
    public function variants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function customCompanies(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot([
                'is_excluded', 
                'override_name', 
                'override_image', 
                'override_mrp', 
                'override_selling_price'
            ])
            ->withTimestamps();
    }

    public function tierPrices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductTierPrice::class)->orderBy('min_quantity', 'asc');
    }
    public function brand(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function voucherCodes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VoucherCode::class);
    }
}