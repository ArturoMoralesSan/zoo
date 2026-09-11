<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Str;

#[Hidden([
    'password',
    'two_factor_secret',
    'two_factor_recovery_codes',
    'remember_token',
])]
class User extends Authenticatable implements PasskeyUser
{
    use HasFactory,
        Notifiable,
        PasskeyAuthenticatable,
        TwoFactorAuthenticatable,
        HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'level_id',
        'points',
        'qr_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'points' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            do {
                $token = Str::random(64);
            } while (User::where('qr_token', $token)->exists());

            $user->qr_token = $token;
        });
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function pointMovements(): HasMany
    {
        return $this->hasMany(PointMovement::class);
    }

    public function rewardRedemptions(): HasMany
    {
        return $this->hasMany(
            RewardRedemption::class
        );
    }
}