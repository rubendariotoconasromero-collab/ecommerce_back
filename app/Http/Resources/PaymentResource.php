<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'order_id'       => $this->order_id,
            'payment_method' => $this->payment_method,
            'transaction_id' => $this->transaction_id,
            'amount'         => $this->amount,
            'status'         => $this->status,
            'paid_at'        => $this->paid_at?->toIso8601String(),
            'created_at'     => $this->created_at?->toIso8601String(),

            // Flags útiles para el frontend
            'is_refundable'  => $this->is_refundable,
            'is_completable' => $this->is_completable,

            // Balance de la orden (se agrega manualmente desde el controlador)
            'order_balance'  => $this->when(
                isset($this->additional['balance']),
                fn () => $this->additional['balance']
            ),

            'order' => $this->whenLoaded('order', fn () => [
                'id'           => $this->order->id,
                'status'       => $this->order->status,
                'total_amount' => $this->order->total_amount,
            ]),
        ];
    }
}
