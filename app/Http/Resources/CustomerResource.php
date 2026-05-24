<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'customer_type' => $this->customer_type,
            'name'          => $this->name,
            'email'         => $this->email,
            'business_name' => $this->business_name,
            'tax_id'        => $this->tax_id,
            'phone'         => $this->phone,
            'is_active'     => $this->is_active,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,

            // Relación opcional con cuenta de acceso
            'user' => $this->whenLoaded('user', fn () => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ]),

            // Agregados de órdenes (cargados con withCount / withSum)
            'orders_count'        => $this->when(
                array_key_exists('orders_count', $this->resource->getAttributes() + ($this->resource->getRelations())),
                $this->orders_count ?? null
            ),
            'active_orders_count' => $this->when(
                isset($this->active_orders_count),
                $this->active_orders_count ?? null
            ),
            'total_spent'         => $this->when(
                isset($this->total_spent),
                fn () => (float) ($this->total_spent ?? 0)
            ),

            // Últimas órdenes (solo en show)
            'recent_orders' => $this->whenLoaded('recentOrders', fn () =>
                $this->recentOrders->map(fn ($order) => [
                    'id'           => $order->id,
                    'status'       => $order->status,
                    'total_amount' => (float) $order->total_amount,
                    'created_at'   => $order->created_at,
                ])
            ),
        ];
    }
}
