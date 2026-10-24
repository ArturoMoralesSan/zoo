<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CardQrCode extends Model
{
    protected $fillable = [
        'card_id',
        'label',
        'is_active',
        'expires_at',
    ];

    /**
     * El token solo se expone de forma explícita (panel de administración).
     */
    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
            'scans_count' => 'integer',
            'last_scanned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CardQrCode $qrCode) {
            $qrCode->token ??= self::generateToken();
        });
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::random(64);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    /**
     * Invalida el QR impreso generando un token nuevo.
     */
    public function regenerateToken(): void
    {
        $this->forceFill([
            'token' => self::generateToken(),
        ])->save();
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    /**
     * Activo y sin vencer.
     */
    public function isUsable(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    #[Scope]
    protected function usable(Builder $query): void
    {
        $query
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()));
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
