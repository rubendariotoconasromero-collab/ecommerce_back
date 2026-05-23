<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Inventory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'inventory';

    // La tabla física solo tiene updated_at, no created_at
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'qty_available',
        'qty_reserved',
        'qty_in_production',
        'reorder_point',
    ];

    protected $casts = [
        'qty_available' => 'integer',
        'qty_reserved' => 'integer',
        'qty_in_production' => 'integer',
        'reorder_point' => 'integer',
    ];

    // RELACIÓN: La ficha de inventario pertenece a un producto
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
