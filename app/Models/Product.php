<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

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
        'is_featured',
    ];


    // Casteo automático de tipos de datos
    protected $casts = [
        'base_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'production_lead_time_days' => 'integer',
        'attributes' => 'array', // Transforma el JSON a Array de PHP automáticamente
        'is_active' => 'boolean',
    ];

    // Relación: Un producto pertenece a una categoría
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Añadir esto en App\Models\Product.php
    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order', 'asc');
    }
}