<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Menu extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'order',
        'route',
        'is_submenu',
    ];

    protected $casts = [
        'is_submenu' => 'boolean',
    ];

    public function links(): HasMany
    {
        return $this->hasMany(Link::class)
            ->orderBy('order');
    }
}