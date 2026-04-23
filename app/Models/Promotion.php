<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = [
        'internal_name',
        'target_type',
        'target_data',
        'format',
        'format_data',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'target_data' => 'array',
        'format_data' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];
}