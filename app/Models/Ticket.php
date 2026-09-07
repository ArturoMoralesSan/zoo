<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_order_id',
        'ticket_order_item_id',
        'ticket_type_id',
        'qr_token',
        'status',
        'used_at',
        'validated_by',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            TicketOrder::class,
            'ticket_order_id'
        );
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(
            TicketOrderItem::class,
            'ticket_order_item_id'
        );
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'validated_by'
        );
    }
}
