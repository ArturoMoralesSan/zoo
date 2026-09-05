<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Level extends Model
{
    protected $fillable = [
        'name',
        'min_points',
        'max_points',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'min_points' => 'integer',
            'max_points' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
