<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAdjustmentBatch extends Model
{
    use HasUuids;

    protected $fillable = [
        'batch_number',
        'adjustment_type',
        'notes',
        'created_by',
        'confirmed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    const TYPES = ['entry', 'exit', 'initial_stock', 'correction'];

    const TYPE_LABELS = [
        'entry'         => 'Ingreso de Mercadería',
        'exit'          => 'Salida de Mercadería',
        'initial_stock' => 'Carga de Stock Inicial',
        'correction'    => 'Corrección / Auditoría',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentLine::class, 'batch_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->adjustment_type] ?? $this->adjustment_type;
    }
}
