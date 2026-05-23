<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Order extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'customer_id',
        'user_id',
        'total_amount',
        'status',
        'expected_delivery_date',
        'shipping_address',
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'expected_delivery_date' => 'date',
    ];

    // RELACIÓN: El pedido pertenece a un cliente comercial
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    // RELACIÓN: El pedido opcionalmente asocia el usuario que lo creó
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // RELACIÓN: Un pedido tiene muchos items detallados
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    // RELACIÓN: Un pedido tiene muchos pagos registrados
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    // RELACIÓN: Un pedido puede tener devoluciones
    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class, 'order_id');
    }

    // RELACIÓN: Un pedido tiene historial de gestiones de personal
    public function handlers(): HasMany
    {
        return $this->hasMany(OrderHandler::class, 'order_id');
    }

    // RELACIÓN: Un pedido puede tener envíos
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'order_id');
    }
}
