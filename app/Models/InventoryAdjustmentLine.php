<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustmentLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'product_id',
        'product_name',
        'product_sku',
        'previous_qty',
        'qty_delta',
        'new_qty',
        'previous_cost_price',
        'new_cost_price',
        'previous_sale_price',
        'new_sale_price',
        'line_notes',
    ];

    protected $casts = [
        'previous_qty'         => 'integer',
        'qty_delta'            => 'integer',
        'new_qty'              => 'integer',
        'previous_cost_price'  => 'decimal:2',
        'new_cost_price'       => 'decimal:2',
        'previous_sale_price'  => 'decimal:2',
        'new_sale_price'       => 'decimal:2',
        'created_at'           => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustmentBatch::class, 'batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
