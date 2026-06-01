<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'created_by_user_id', 'vertical_id',
        'event_id', 'custom_event_name',
        'name', 'description', 'distribution_type', 'reward_type',
        'budget_locked', 'total_budget', 'total_recipients', 'config_json',
        'status', 'starts_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'budget_locked'    => 'decimal:2',
            'total_budget'     => 'decimal:2',
            'total_recipients' => 'integer',
            'config_json'      => 'array',
            'starts_at'        => 'datetime',
            'expires_at'       => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function vertical(): BelongsTo
    {
        return $this->belongsTo(Vertical::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(CampaignEntitlement::class);
    }
}
