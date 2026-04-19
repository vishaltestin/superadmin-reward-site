<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasWallet; 

class Company extends Model
{
    use SoftDeletes, HasWallet;

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
        'point_multiplier',
        'is_active',
        'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_approved' => 'boolean',
            'point_multiplier' => 'float', // <-- Added here
        ];
    }
    protected static function booted()
    {
        // Listen for updates to the company record
        static::updated(function ($company) {
            // If the 'is_approved' column was just changed, AND it is now true
            if ($company->wasChanged('is_approved') && $company->is_approved) {
                // Create the wallet safely (firstOrCreate prevents accidental duplicates)
                $company->wallet()->firstOrCreate([], ['balance' => 0.00]);
            }
        });
    }
    
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function verticals()
    {
        return $this->belongsToMany(Vertical::class);
    }

    public function customProducts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot([
                'is_excluded', 
                'override_name', 
                'override_image', 
                'override_mrp', 
                'override_selling_price'
            ])
            ->withTimestamps();
    }


    /**
     * Get all orders placed by employees of this company.
     */
    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }
}