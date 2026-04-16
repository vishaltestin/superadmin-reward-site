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
}