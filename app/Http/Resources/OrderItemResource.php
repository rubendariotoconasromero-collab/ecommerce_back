<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'product_id'            => $this->product_id,
            'product_name'          => $this->product_name,
            'quantity'              => $this->quantity,
            'unit_cost'             => (float) $this->unit_cost,
            'unit_price'            => (float) $this->unit_price,
            'subtotal'              => (float) $this->subtotal,
            'line_profit'           => (float) $this->line_profit,
            'customization_notes'   => $this->customization_notes,
            'reference_image_path'  => $this->reference_image_path,
            'reference_image_url'   => $this->reference_image_path
                ? asset('storage/' . $this->reference_image_path)
                : null,
            'created_at'            => $this->created_at,

            // Relación con el producto actual del catálogo (solo cuando se carga)
            'product' => $this->whenLoaded('product', fn () => [
                'id'         => $this->product->id,
                'sku'        => $this->product->sku,
                'is_active'  => $this->product->is_active,
                'sale_price' => (float) $this->product->sale_price,
            ]),
        ];
    }
}
