<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class ReturnService
{
    public function __construct(private readonly OrderService $orderService) {}

    // -------------------------------------------------------------------------
    // Crear solicitud de devolución
    // -------------------------------------------------------------------------

    /**
     * Registra una solicitud de devolución para una orden entregada.
     *
     * Reglas:
     *  - La orden debe estar en estado 'delivered'.
     *  - No puede haber otra devolución activa (requested/approved) en la misma orden.
     *  - return_type = 'partial' requiere order_item_id válido y refund_amount <= subtotal del ítem.
     *  - return_type = 'full'    usa order_item_id = null y refund_amount <= total_amount de la orden.
     */
    public function request(Order $order, array $data, User $actor): OrderReturn
    {
        return DB::transaction(function () use ($order, $data, $actor) {

            $order = Order::lockForUpdate()->findOrFail($order->id);

            if ($order->status !== 'delivered') {
                throw new LogicException(
                    "Solo se pueden solicitar devoluciones para órdenes en estado 'delivered'. " .
                    "Estado actual: '{$order->status}'."
                );
            }

            $hasActive = OrderReturn::forOrder($order->id)->active()->exists();
            if ($hasActive) {
                throw new LogicException(
                    'Ya existe una devolución activa (solicitada o aprobada) para esta orden. ' .
                    'Resuelva o rechace la existente antes de crear una nueva.'
                );
            }

            $refundAmount = $data['refund_amount'] ?? null;
            $orderItemId  = $data['order_item_id'] ?? null;

            if ($data['return_type'] === 'partial') {
                $this->assertPartialReturnValid($order, $orderItemId, $refundAmount);
            } else {
                // full return
                $maxRefund = (float) $order->total_amount;
                if ($refundAmount !== null && (float) $refundAmount > $maxRefund) {
                    throw new LogicException(
                        "El monto de reembolso ({$refundAmount}) supera el total de la orden ({$maxRefund})."
                    );
                }
                $orderItemId = null; // full returns no se asocian a un ítem específico
            }

            return OrderReturn::create([
                'order_id'      => $order->id,
                'order_item_id' => $orderItemId,
                'return_type'   => $data['return_type'],
                'status'        => 'requested',
                'reason'        => $data['reason'] ?? null,
                'refund_amount' => $refundAmount,
            ]);
        });
    }

    // -------------------------------------------------------------------------
    // Transiciones de estado
    // -------------------------------------------------------------------------

    /**
     * Aprueba una devolución.
     * Para devoluciones 'full': transiciona la orden a estado 'returned'.
     */
    public function approve(OrderReturn $return, User $actor, ?string $notes = null): OrderReturn
    {
        return DB::transaction(function () use ($return, $actor, $notes) {

            $return = OrderReturn::lockForUpdate()->findOrFail($return->id);
            $this->assertCanTransition($return, 'approved');

            $return->update(['status' => 'approved']);

            // Las devoluciones completas cambian el estado de la orden
            if ($return->return_type === 'full') {
                $order = Order::lockForUpdate()->findOrFail($return->order_id);

                if ($order->canTransitionTo('returned')) {
                    $this->orderService->transition(
                        $order,
                        'returned',
                        $actor,
                        $notes ?? 'Devolución completa aprobada.'
                    );
                }
            }

            return $return->fresh();
        });
    }

    /**
     * Rechaza una devolución.
     */
    public function reject(OrderReturn $return, User $actor, ?string $reason = null): OrderReturn
    {
        return DB::transaction(function () use ($return, $actor, $reason) {

            $return = OrderReturn::lockForUpdate()->findOrFail($return->id);
            $this->assertCanTransition($return, 'rejected');

            $return->update(['status' => 'rejected']);

            return $return->fresh();
        });
    }

    /**
     * Marca una devolución como resuelta (reembolso ejecutado).
     * Este estado habilita el reembolso de pagos vía PaymentService.
     */
    public function resolve(OrderReturn $return, User $actor, ?string $notes = null): OrderReturn
    {
        return DB::transaction(function () use ($return, $actor, $notes) {

            $return = OrderReturn::lockForUpdate()->findOrFail($return->id);
            $this->assertCanTransition($return, 'resolved');

            $return->update([
                'status'      => 'resolved',
                'resolved_at' => now(),
            ]);

            return $return->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    private function assertPartialReturnValid(Order $order, ?string $orderItemId, ?float $refundAmount): void
    {
        if (!$orderItemId) {
            throw new LogicException(
                "Las devoluciones parciales requieren especificar el ítem (order_item_id)."
            );
        }

        $item = OrderItem::where('id', $orderItemId)
            ->where('order_id', $order->id)
            ->first();

        if (!$item) {
            throw new LogicException(
                'El ítem especificado no pertenece a esta orden.'
            );
        }

        if ($refundAmount !== null && (float) $refundAmount > (float) $item->subtotal) {
            throw new LogicException(
                "El monto de reembolso ({$refundAmount}) supera el subtotal del ítem ({$item->subtotal})."
            );
        }
    }

    private function assertCanTransition(OrderReturn $return, string $newStatus): void
    {
        if (!$return->canTransitionTo($newStatus)) {
            $allowed = implode(', ', $return->allowed_transitions) ?: 'ninguna';
            throw new LogicException(
                "No se puede transicionar desde '{$return->status}' a '{$newStatus}'. " .
                "Transiciones permitidas: {$allowed}."
            );
        }
    }
}
