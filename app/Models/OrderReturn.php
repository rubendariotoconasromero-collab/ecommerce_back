<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OrderReturn extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'order_returns';

    // No maneja timestamps tradicionales
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'return_type',
        'status',
        'reason',
        'refund_amount',
        'resolved_at',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'request_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    // RELACIÓN: La devolución pertenece a un pedido
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    // RELACIÓN: La devolución opcionalmente pertenece a un item de detalle específico
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
