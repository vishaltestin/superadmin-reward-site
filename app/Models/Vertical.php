<?php

namespace App\Models;
use App\Helpers\SlugHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vertical extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'description', 'is_active'];

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

    public function companies()
    {
        return $this->belongsToMany(Company::class);
    }
    /**
     * Get the events associated with the vertical.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}