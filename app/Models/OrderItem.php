<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class OrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'order_items';

    // No maneja updated_at estándar
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_cost',
        'unit_price',
        'subtotal',
        'line_profit',
        'customization_notes',
        'reference_image_path',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'line_profit' => 'decimal:2',
    ];

    // RELACIÓN: El item de detalle pertenece a un pedido
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    // RELACIÓN: El item se vincula al producto catalogado
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // RELACIÓN: Un item puede generar órdenes de producción individuales
    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'order_item_id');
    }
}
