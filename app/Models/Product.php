<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'category_id',
        'sku',
        'name',
        'description',
        'base_price',
        'cost_price',
        'sale_price',
        'production_lead_time_days',
        'attributes',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'production_lead_time_days' => 'integer',
        'attributes' => 'array',
        'is_active' => 'boolean',
    ];

    // Relación: Un producto pertenece a una categoría
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Relación: Un producto tiene muchas imágenes
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('sort_order', 'asc');
    }

    // Relación: Un producto tiene una ficha de inventario
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class, 'product_id');
    }
}