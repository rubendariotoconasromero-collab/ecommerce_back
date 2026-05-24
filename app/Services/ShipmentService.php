<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class ShipmentService
{
    /**
     * Estados de la orden que permiten crear un envío.
     *  - 'ready'    : primer envío — disparará la transición ready → shipped
     *  - 'shipped'  : reenvío tras un fallo — la orden ya está en shipped, no se vuelve a transicionar
     */
    private const ORDER_STATUSES_ALLOWED = ['ready', 'shipped'];

    public function __construct(private readonly OrderService $orderService) {}

    // -------------------------------------------------------------------------
    // Crear envío
    // -------------------------------------------------------------------------

    /**
     * Crea un nuevo envío para la orden.
     *
     * Reglas:
     *  - La orden debe estar en estado 'ready' o 'shipped'.
     *  - No puede haber otro envío activo (distinto de delivered/failed) en la misma orden.
     *  - Si la orden está en 'ready', se transiciona automáticamente a 'shipped'.
     */
    public function create(Order $order, array $data, User $actor): Shipment
    {
        return DB::transaction(function () use ($order, $data, $actor) {

            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (!in_array($order->status, self::ORDER_STATUSES_ALLOWED)) {
                throw new LogicException(
                    "Solo se puede crear un envío para órdenes en estado 'ready' o 'shipped'. " .
                    "Estado actual: '{$order->status}'."
                );
            }

            $hasActiveShipment = Shipment::forOrder($order->id)->active()->exists();
            if ($hasActiveShipment) {
                throw new LogicException(
                    'Ya existe un envío activo para esta orden. Finalícelo (entregado o fallido) antes de crear uno nuevo.'
                );
            }

            $shipment = Shipment::create([
                'order_id'       => $order->id,
                'tracking_number'=> $data['tracking_number'] ?? null,
                'courier_name'   => $data['courier_name'] ?? null,
                'handler_id'     => $data['handler_id'] ?? null,
                'status'         => 'preparing',
                'failure_reason' => null,
            ]);

            // Si la orden estaba en 'ready', transicionarla a 'shipped'
            if ($order->status === 'ready') {
                $this->orderService->transition(
                    $order,
                    'shipped',
                    $actor,
                    $data['notes'] ?? 'Envío creado y orden despachada.'
                );
            }

            return $shipment;
        });
    }

    // -------------------------------------------------------------------------
    // Transiciones de estado
    // -------------------------------------------------------------------------

    /**
     * Despacha el envío: preparing → shipped.
     * Registra shipped_at y opcionalmente actualiza tracking y courier.
     */
    public function dispatch(Shipment $shipment, array $data, User $actor): Shipment
    {
        return DB::transaction(function () use ($shipment, $data, $actor) {

            $shipment = Shipment::lockForUpdate()->findOrFail($shipment->id);
            $this->assertCanTransition($shipment, 'shipped');

            $shipment->update([
                'status'          => 'shipped',
                'shipped_at'      => now(),
                'tracking_number' => $data['tracking_number'] ?? $shipment->tracking_number,
                'courier_name'    => $data['courier_name'] ?? $shipment->courier_name,
                'handler_id'      => $data['handler_id'] ?? $shipment->handler_id,
            ]);

            return $shipment->fresh();
        });
    }

    /**
     * Marca el envío en tránsito: shipped → in_transit.
     */
    public function markInTransit(Shipment $shipment, array $data, User $actor): Shipment
    {
        return DB::transaction(function () use ($shipment, $data, $actor) {

            $shipment = Shipment::lockForUpdate()->findOrFail($shipment->id);
            $this->assertCanTransition($shipment, 'in_transit');

            $shipment->update([
                'status'          => 'in_transit',
                'tracking_number' => $data['tracking_number'] ?? $shipment->tracking_number,
                'courier_name'    => $data['courier_name'] ?? $shipment->courier_name,
            ]);

            return $shipment->fresh();
        });
    }

    /**
     * Marca el envío como entregado: (shipped|in_transit) → delivered.
     * Auto-transiciona la orden de 'shipped' a 'delivered'.
     */
    public function deliver(Shipment $shipment, User $actor, ?string $notes = null): Shipment
    {
        return DB::transaction(function () use ($shipment, $actor, $notes) {

            $shipment = Shipment::lockForUpdate()->findOrFail($shipment->id);
            $this->assertCanTransition($shipment, 'delivered');

            $shipment->update([
                'status'       => 'delivered',
                'delivered_at' => now(),
            ]);

            // Transicionar la orden a 'delivered' si todavía está en 'shipped'
            $order = Order::lockForUpdate()->findOrFail($shipment->order_id);
            if ($order->canTransitionTo('delivered')) {
                $this->orderService->transition(
                    $order,
                    'delivered',
                    $actor,
                    $notes ?? 'Entrega confirmada.'
                );
            }

            return $shipment->fresh();
        });
    }

    /**
     * Marca el envío como fallido.
     * La orden permanece en 'shipped' para permitir un reenvío.
     */
    public function fail(Shipment $shipment, string $failureReason, User $actor): Shipment
    {
        return DB::transaction(function () use ($shipment, $failureReason, $actor) {

            $shipment = Shipment::lockForUpdate()->findOrFail($shipment->id);
            $this->assertCanTransition($shipment, 'failed');

            $shipment->update([
                'status'         => 'failed',
                'failure_reason' => $failureReason,
            ]);

            return $shipment->fresh();
        });
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    private function assertCanTransition(Shipment $shipment, string $newStatus): void
    {
        if (!$shipment->canTransitionTo($newStatus)) {
            $allowed = implode(', ', $shipment->allowed_transitions) ?: 'ninguna';
            throw new LogicException(
                "No se puede cambiar el estado de '{$shipment->status}' a '{$newStatus}'. " .
                "Transiciones permitidas: {$allowed}."
            );
        }
    }
}
