<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignEntitlement extends Model
{
    protected $fillable = [
        'campaign_id', 'issued_to_user_id', 'reward_value',
        'claim_token', 'claim_code', 'is_claimed',
        'claimed_at', 'expires_at', 'reminded_at'
    ];

    protected function casts(): array
    {
        return [
            'reward_value' => 'decimal:2',
            'is_claimed' => 'boolean',
            'claimed_at' => 'datetime',
            'expires_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_to_user_id');
    }
}