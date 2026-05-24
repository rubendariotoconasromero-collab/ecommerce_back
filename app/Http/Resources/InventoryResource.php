<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $qtySellable = max(0, $this->qty_available - $this->qty_reserved);
        $isLowStock  = $qtySellable <= $this->reorder_point;

        return [
            'id'                => $this->id,
            'product_id'        => $this->product_id,
            'qty_available'     => $this->qty_available,
            'qty_reserved'      => $this->qty_reserved,
            'qty_in_production' => $this->qty_in_production,
            'qty_sellable'      => $qtySellable,
            'reorder_point'     => $this->reorder_point,
            'is_low_stock'      => $isLowStock,
            'updated_at'        => $this->updated_at,

            // Advertencia de conflicto: stock físico < reservado
            'stock_conflict' => $this->when(
                $this->qty_available < $this->qty_reserved,
                fn () => [
                    'message'      => 'El stock disponible es menor al reservado. Hay órdenes en riesgo.',
                    'qty_deficit'  => $this->qty_reserved - $this->qty_available,
                ]
            ),

            'product' => $this->whenLoaded('product', fn () => [
                'id'        => $this->product->id,
                'sku'       => $this->product->sku,
                'name'      => $this->product->name,
                'is_active' => $this->product->is_active,
                'category'  => $this->product->relationLoaded('category')
                    ? ['id' => $this->product->category?->id, 'name' => $this->product->category?->name]
                    : null,
            ]),
        ];
    }
}
