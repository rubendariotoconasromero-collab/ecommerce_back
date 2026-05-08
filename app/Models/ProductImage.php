<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'image_path',
        'is_primary',
        'sort_order'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Atributos virtuales para obtener la URL completa de la imagen en el Frontend
    protected $appends = ['url', 'image_url'];

    public function getUrlAttribute()
    {
        if (str_starts_with($this->image_path, 'http')) {
            return $this->image_path;
        }
        return asset('storage/' . $this->image_path);
    }

    public function getImageUrlAttribute()
    {
        return $this->getUrlAttribute();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}