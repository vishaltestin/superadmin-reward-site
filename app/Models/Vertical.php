<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Vertical extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($vertical) {
            if (empty($vertical->slug)) {
                $vertical->slug = Str::slug($vertical->name);
            }
        });
    }
}