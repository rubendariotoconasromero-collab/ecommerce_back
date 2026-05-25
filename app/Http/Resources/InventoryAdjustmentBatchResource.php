<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryAdjustmentBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'batch_number'    => $this->batch_number,
            'adjustment_type' => $this->adjustment_type,
            'type_label'      => $this->type_label,
            'notes'           => $this->notes,
            'lines_count'     => $this->whenLoaded('lines', fn () => $this->lines->count()),
            'confirmed_at'    => $this->confirmed_at,
            'created_at'      => $this->created_at,

            'creator' => $this->whenLoaded('creator', fn () => [
                'id'   => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),

            'lines' => $this->whenLoaded('lines', fn () =>
                $this->lines->map(fn ($line) => [
                    'id'                   => $line->id,
                    'product_id'           => $line->product_id,
                    'product_name'         => $line->product_name,
                    'product_sku'          => $line->product_sku,
                    'previous_qty'         => $line->previous_qty,
                    'qty_delta'            => $line->qty_delta,
                    'new_qty'              => $line->new_qty,
                    'previous_cost_price'  => $line->previous_cost_price,
                    'new_cost_price'       => $line->new_cost_price,
                    'previous_sale_price'  => $line->previous_sale_price,
                    'new_sale_price'       => $line->new_sale_price,
                    'line_notes'           => $line->line_notes,
                    'created_at'           => $line->created_at,
                ])
            ),
        ];
    }
}
