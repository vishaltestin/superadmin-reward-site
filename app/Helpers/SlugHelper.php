<?php

namespace App\Helpers;

use Illuminate\Support\Str;

class SlugHelper
{
    public static function generateUniqueSlug($model, $value, $column = 'slug')
    {
        $slug = Str::slug($value);
        $original = $slug;
        $count = 1;

        // FIX: Added withTrashed() so it sees soft-deleted records too!
        while ($model::withTrashed()->where($column, $slug)->exists()) {
            $slug = $original . '-' . $count++;
        }

        return $slug;
    }
}