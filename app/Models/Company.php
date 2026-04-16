<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'number_of_employee',
        'gst_no',
        'pan_no',
        'industry',
        'address',
        'alias',
        'logo',
        'points_name',
        'available_funds',
        'is_active',
        'is_approved',
    ];
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }
    public function verticals()
    {
        return $this->belongsToMany(Vertical::class);
    }
}