<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventVariable extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'value',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // A variable might belong to a specific event
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}