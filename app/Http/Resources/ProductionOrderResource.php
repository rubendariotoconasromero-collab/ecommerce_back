<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'order_item_id'      => $this->order_item_id,
            'assigned_worker_id' => $this->assigned_worker_id,
            'status'             => $this->status,
            'internal_notes'     => $this->internal_notes,
            'started_at'         => $this->started_at?->toIso8601String(),
            'completed_at'       => $this->completed_at?->toIso8601String(),
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),

            // Flags de acción para el frontend
            'allowed_transitions' => $this->allowed_transitions,
            'can_start'           => $this->can_start,
            'can_complete'        => $this->can_complete,
            'can_cancel'          => $this->can_cancel,
            'is_active'           => $this->is_active,

            'worker' => $this->whenLoaded('worker', fn () => $this->worker
                ? ['id' => $this->worker->id, 'name' => $this->worker->name]
                : null
            ),

            'order_item' => $this->whenLoaded('orderItem', fn () => [
                'id'                  => $this->orderItem->id,
                'order_id'            => $this->orderItem->order_id,
                'product_name'        => $this->orderItem->product_name,
                'quantity'            => $this->orderItem->quantity,
                'customization_notes' => $this->orderItem->customization_notes,
                'reference_image_url' => $this->orderItem->reference_image_path
                    ? asset('storage/' . $this->orderItem->reference_image_path)
                    : null,
            ]),
        ];
    }
}
