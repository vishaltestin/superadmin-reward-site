<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPageTemplate extends Model
{
    protected $fillable = [
        'event_id',
        'company_id',
        'reward_type',
        'name',
        'title',
        'thumbnail_path',
        'status',
        'global_theme_tokens',
        'seo_meta',
        'page_schema',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     * This is crucial for handling your JSON schema safely.
     */
    protected function casts(): array
    {
        return [
            'is_active'           => 'boolean',
            'global_theme_tokens' => 'array',
            'seo_meta'            => 'array',
            'page_schema'         => 'array',
        ];
    }

    /**
     * The event that triggers this landing page.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The company that owns this specific variation.
     * If null, it is a Super Admin Global Master.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
