<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Shipment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_id',
        'tracking_number',
        'courier_name',
        'handler_id',
        'status',
        'failure_reason',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // RELACIÓN: El envío pertenece a un pedido
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    // RELACIÓN: El envío fue gestionado por un usuario del personal (repartidor/gestor)
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }
}
