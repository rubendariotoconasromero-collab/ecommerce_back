<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Calcular monto pagado: desde relación cargada o desde atributo agregado
        $amountPaid = $this->relationLoaded('payments')
            ? (float) $this->payments->where('status', 'completed')->sum('amount')
            : (float) ($this->amount_paid ?? 0);

        $amountPending = max(0, (float) $this->total_amount - $amountPaid);

        return [
            'id'                      => $this->id,
            'order_number'            => $this->order_number,
            'status'                  => $this->status,
            'total_amount'            => (float) $this->total_amount,
            'amount_paid'             => $amountPaid,
            'amount_pending'          => $amountPending,
            'expected_delivery_date'  => $this->expected_delivery_date?->toDateString(),
            'shipping_address'        => $this->shipping_address,
            'notes'                   => $this->notes,
            'created_at'              => $this->created_at,
            'updated_at'              => $this->updated_at,

            // Transiciones permitidas desde el estado actual (útil para el frontend)
            'allowed_transitions'     => $this->allowed_transitions,

            // Cliente que compra
            'customer' => $this->whenLoaded('customer', fn () => [
                'id'            => $this->customer->id,
                'name'          => $this->customer->name,
                'email'         => $this->customer->email,
                'customer_type' => $this->customer->customer_type,
                'business_name' => $this->customer->business_name,
                'phone'         => $this->customer->phone,
            ]),

            // Usuario que registró la orden
            'created_by' => $this->whenLoaded('user', fn () => $this->user ? [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ] : null),

            // Items del pedido
            'items_count' => $this->when(
                isset($this->items_count),
                $this->items_count
            ),
            'items' => OrderItemResource::collection(
                $this->whenLoaded('items')
            ),

            // Pagos registrados (solo en show)
            'payments' => $this->whenLoaded('payments', fn () =>
                $this->payments->map(fn ($p) => [
                    'id'             => $p->id,
                    'payment_method' => $p->payment_method,
                    'amount'         => (float) $p->amount,
                    'status'         => $p->status,
                    'transaction_id' => $p->transaction_id,
                    'paid_at'        => $p->paid_at,
                    'created_at'     => $p->created_at,
                ])
            ),

            // Historial completo de envíos (solo en show)
            'shipments' => $this->whenLoaded('shipments', fn () =>
                $this->shipments->sortByDesc('created_at')->map(fn ($s) => [
                    'id'             => $s->id,
                    'status'         => $s->status,
                    'tracking_number'=> $s->tracking_number,
                    'courier_name'   => $s->courier_name,
                    'failure_reason' => $s->failure_reason,
                    'notes'          => $s->notes,
                    'shipped_at'     => $s->shipped_at,
                    'delivered_at'   => $s->delivered_at,
                    'created_at'     => $s->created_at,
                ])->values()
            ),

            // Devoluciones del pedido (solo en show)
            'returns' => $this->whenLoaded('returns', fn () =>
                $this->returns->sortByDesc('request_at')->map(fn ($r) => [
                    'id'            => $r->id,
                    'return_type'   => $r->return_type,
                    'status'        => $r->status,
                    'reason'        => $r->reason,
                    'refund_amount' => $r->refund_amount ? (float) $r->refund_amount : null,
                    'product_name'  => $r->orderItem?->product_name,
                    'request_at'    => $r->request_at,
                ])->values()
            ),

            // Historial de gestión (solo en show)
            'handlers' => $this->whenLoaded('handlers', fn () =>
                $this->handlers->sortBy('handled_at')->values()->map(fn ($h) => [
                    'id'           => $h->id,
                    'handler_name' => $h->handler_name,
                    'handler_role' => $h->handler_role,
                    'action_taken' => $h->action_taken,
                    'notes'        => $h->notes,
                    'handled_at'   => $h->handled_at,
                ])
            ),
        ];
    }
}
