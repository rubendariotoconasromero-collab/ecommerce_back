<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Inventory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'inventory';

    // Solo updated_at existe en la tabla; MySQL lo gestiona automáticamente
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'qty_available',
        'qty_reserved',
        'qty_in_production',
        'reorder_point',
    ];

    protected $casts = [
        'qty_available'     => 'integer',
        'qty_reserved'      => 'integer',
        'qty_in_production' => 'integer',
        'reorder_point'     => 'integer',
        'updated_at'        => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relaciones
    // -------------------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // -------------------------------------------------------------------------
    // Atributos computados (no persistidos)
    // -------------------------------------------------------------------------

    public function getQtySellableAttribute(): int
    {
        return max(0, $this->qty_available - $this->qty_reserved);
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->qty_sellable <= $this->reorder_point;
    }

    // -------------------------------------------------------------------------
    // Scopes de consulta
    // -------------------------------------------------------------------------

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereRaw(
            '(qty_available - qty_reserved) <= reorder_point'
        );
    }

    public function scopeForCategory(Builder $query, string $categoryId): Builder
    {
        return $query->whereHas(
            'product',
            fn ($q) => $q->where('category_id', $categoryId)
        );
    }
}
