<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProductionOrder extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'production_orders';

    protected $fillable = [
        'order_item_id',
        'assigned_worker_id',
        'status',
        'started_at',
        'completed_at',
        'internal_notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // RELACIÓN: La orden de producción se asocia a un item de detalle del pedido
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    // RELACIÓN: La orden de producción se asigna a un usuario operario/trabajador
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_worker_id');
    }
}
