<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'order_id'        => $this->order_id,
            'tracking_number' => $this->tracking_number,
            'courier_name'    => $this->courier_name,
            'handler_id'      => $this->handler_id,
            'status'          => $this->status,
            'failure_reason'  => $this->failure_reason,
            'shipped_at'      => $this->shipped_at?->toIso8601String(),
            'delivered_at'    => $this->delivered_at?->toIso8601String(),
            'created_at'      => $this->created_at?->toIso8601String(),
            'updated_at'      => $this->updated_at?->toIso8601String(),

            // Flags de acción para el frontend
            'allowed_transitions' => $this->allowed_transitions,
            'is_active'           => $this->is_active,
            'is_delivered'        => $this->is_delivered,

            'handler' => $this->whenLoaded('handler', fn () => $this->handler
                ? [
                    'id'   => $this->handler->id,
                    'name' => $this->handler->name,
                ]
                : null
            ),

            'order' => $this->whenLoaded('order', fn () => [
                'id'           => $this->order->id,
                'status'       => $this->order->status,
                'total_amount' => $this->order->total_amount,
                'shipping_address' => $this->order->shipping_address,
            ]),
        ];
    }
}
