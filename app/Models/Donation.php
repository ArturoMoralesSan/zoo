<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'seller_id',
        'payment_method_id',
        'amount',
        'reference',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Usuario que realiza la donación
    |--------------------------------------------------------------------------
    */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Vendedor que registra la donación
    |--------------------------------------------------------------------------
    */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'seller_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Método de pago
    |--------------------------------------------------------------------------
    */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(
            PaymentMethod::class
        );
    }
}