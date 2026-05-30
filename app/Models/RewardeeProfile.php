<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardeeProfile extends Model
{
    protected $fillable = [
        'user_id', 'company_id', 'vertical_id', 'vertical_data',
    ];

    protected $casts = [
        'vertical_data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vertical()
    {
        return $this->belongsTo(Vertical::class);
    }
}
