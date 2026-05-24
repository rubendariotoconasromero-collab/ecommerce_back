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

    // created_at se gestiona por MySQL (useCurrent); no existe updated_at
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
        'quantity'    => 'integer',
        'unit_cost'   => 'decimal:2',
        'unit_price'  => 'decimal:2',
        'subtotal'    => 'decimal:2',
        'line_profit' => 'decimal:2',
        'created_at'  => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'order_item_id');
    }
}
