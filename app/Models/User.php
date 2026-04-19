<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\HasWallet;

#[Fillable(['name', 'email', 'password', 'company_id', 'user_type', 'first_name', 'last_name', 'mobile', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasWallet;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
    protected static function booted()
    {
        // Auto-create a wallet when a new user is created
        static::created(function ($user) {
            if ($user->user_type === 'rewardee') {
                $user->wallet()->create(['balance' => 0.00]);
            }
        });

        // Catch users who change roles later!
        static::updated(function ($user) {
            if ($user->wasChanged('user_type') && $user->user_type === 'rewardee') {
                $user->wallet()->firstOrCreate([], ['balance' => 0.00]);
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function rewardeeProfile()
    {
        return $this->hasOne(RewardeeProfile::class);
    }

    public function managedVerticals()
    {
        return $this->belongsToMany(Vertical::class, 'admin_vertical_access', 'user_id', 'vertical_id');
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get all digital voucher codes claimed by this user.
     */
    public function claimedVouchers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VoucherCode::class, 'issued_to_user_id');
    }
}