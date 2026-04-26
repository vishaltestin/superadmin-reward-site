<?php
namespace App\Models;

use App\Helpers\SlugHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'sort_order',
        'is_active',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(
            function ($category) {
                if (empty($category->slug)) {
                    $category->slug = SlugHelper
                        ::generateUniqueSlug(self::class, $category->name);
                }
            },
        );
    }

    public function parent ()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class);
    }
    public function primaryProducts()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    // Get all products where this is just a TAGGED/SECONDARY category
    public function secondaryProducts()
    {
        return $this->belongsToMany(Product::class, 'category_product');
    }

    public function getTreeNameAttribute()
    {
        $name = $this->name;
        $parent = $this->parent;

        // Loop through parents until we reach the top level
        while ($parent) {
            $name = $parent->name . ' > ' . $name;
            $parent = $parent->parent;
        }

        return $name;
    }
}