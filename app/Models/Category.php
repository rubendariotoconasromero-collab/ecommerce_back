<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Category extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['parent_id', 'name', 'slug', 'description', 'image_url', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relación recursiva: Pertenece a una categoría padre
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Relación recursiva: Tiene muchas categorías hijas (subcategorías)
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Relación con Productos (1 a N)
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        $value = $this->attributes['image_url'] ?? null;
        if (!$value) {
            return null;
        }
        // Compatibilidad: si ya es una URL completa (registros antiguos), devolverla tal cual
        if (str_starts_with($value, 'http')) {
            return $value;
        }
        return asset('storage/' . $value);
    }
}