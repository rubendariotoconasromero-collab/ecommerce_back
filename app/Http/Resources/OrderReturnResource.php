<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'order_id'      => $this->order_id,
            'order_item_id' => $this->order_item_id,
            'return_type'   => $this->return_type,
            'status'        => $this->status,
            'reason'        => $this->reason,
            'refund_amount' => $this->refund_amount,
            'request_at'    => $this->request_at?->toIso8601String(),
            'resolved_at'   => $this->resolved_at?->toIso8601String(),

            // Flags para renderizado de botones de acción en el frontend
            'allowed_transitions' => $this->allowed_transitions,
            'can_be_approved'     => $this->can_be_approved,
            'can_be_rejected'     => $this->can_be_rejected,
            'can_be_resolved'     => $this->can_be_resolved,

            'order' => $this->whenLoaded('order', fn () => [
                'id'           => $this->order->id,
                'status'       => $this->order->status,
                'total_amount' => $this->order->total_amount,
            ]),

            'order_item' => $this->whenLoaded('orderItem', fn () => $this->orderItem
                ? [
                    'id'           => $this->orderItem->id,
                    'product_name' => $this->orderItem->product_name,
                    'quantity'     => $this->orderItem->quantity,
                    'unit_price'   => $this->orderItem->unit_price,
                    'subtotal'     => $this->orderItem->subtotal,
                ]
                : null
            ),
        ];
    }
}
