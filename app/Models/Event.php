<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $fillable = [
        'vertical_id', 
        'parent_id', 
        'title', 
        'icon', 
        'sort_order', 
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function vertical()
    {
        return $this->belongsTo(Vertical::class);
    }
    public function parent()
    {
        return $this->belongsTo(Event::class, 'parent_id');
    }
    public function children()
    {
        return $this->hasMany(Event::class, 'parent_id');
    }
}