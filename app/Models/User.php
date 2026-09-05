<?php

namespace App\Models;

use App\Models\Level;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;


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
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

}