<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class ProductionService
{
    /**
     * Estados de la orden que permiten crear órdenes de producción.
     */
    private const ALLOWED_ORDER_STATUSES = ['confirmed', 'in_production'];

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly OrderService     $orderService,
    ) {}

    // -------------------------------------------------------------------------
    // Crear órdenes de producción
    // -------------------------------------------------------------------------

    /**
     * Crea una única orden de producción para el ítem indicado.
     *
     * Reglas:
     *  - La orden padre debe estar en 'confirmed' o 'in_production'.
     *  - El ítem debe pertenecer a la orden.
     *  - No puede haber ya una orden de producción activa para ese ítem.
     */
    public function create(Order $order, string $orderItemId, array $data, User $actor): ProductionOrder
    {
        return DB::transaction(function () use ($order, $orderItemId, $data, $actor) {

            $order = Order::lockForUpdate()->findOrFail($order->id);
            $this->assertOrderAllowsProduction($order);

            $item = OrderItem::where('id', $orderItemId)
                ->where('order_id', $order->id)
                ->firstOrFail();

            $this->assertNoActiveProdOrder($item);

            $prodOrder = ProductionOrder::create([
                'order_item_id'     => $item->id,
                'assigned_worker_id'=> $data['assigned_worker_id'] ?? null,
                'status'            => 'queued',
                'internal_notes'    => $data['internal_notes'] ?? null,
            ]);

            // Si la orden sigue en 'confirmed', transicionarla a 'in_production'
            if ($order->status === 'confirmed') {
                $this->orderService->transition(
                    $order,
                    'in_production',
                    $actor,
                    'Producción iniciada al crear primera orden de trabajo.'
                );
            }

            return $prodOrder;
        });
    }

    /**
     * Crea automáticamente una orden de producción para cada ítem de la orden
     * que todavía no tenga una activa.
     * Retorna la colección de órdenes de producción creadas.
     */
    public function createForAllItems(Order $order, array $data, User $actor): Collection
    {
        return DB::transaction(function () use ($order, $data, $actor) {

            $order = Order::lockForUpdate()->findOrFail($order->id);
            $this->assertOrderAllowsProduction($order);

            $order->loadMissing('items');

            $created = collect();

            foreach ($order->items as $item) {
                $hasActive = ProductionOrder::where('order_item_id', $item->id)
                    ->active()
                    ->exists();

                if ($hasActive) {
                    continue;
                }

                $created->push(ProductionOrder::create([
                    'order_item_id'     => $item->id,
                    'assigned_worker_id'=> $data['assigned_worker_id'] ?? null,
                    'status'            => 'queued',
                    'internal_notes'    => $data['internal_notes'] ?? null,
                ]));
            }

            if ($created->isEmpty()) {
                throw new LogicException(
                    'Todos los ítems de esta orden ya tienen una orden de producción activa.'
                );
            }

            if ($order->status === 'confirmed') {
                $this->orderService->transition(
                    $order,
                    'in_production',
                    $actor,
                    'Producción iniciada: órdenes de trabajo generadas para todos los ítems.'
                );
            }

            return $created;
        });
    }

    // -------------------------------------------------------------------------
    // Transiciones de estado
    // -------------------------------------------------------------------------

    /**
     * Inicia la producción: queued → in_progress.
     * Efecto en inventario: qty_reserved → qty_in_production.
     */
    public function start(ProductionOrder $prodOrder, User $actor, ?string $notes = null): ProductionOrder
    {
        return DB::transaction(function () use ($prodOrder, $actor, $notes) {

            $prodOrder = ProductionOrder::lockForUpdate()->findOrFail($prodOrder->id);
            $this->assertCanTransition($prodOrder, 'in_progress');

            $item = $prodOrder->orderItem()->with('product:id,product_id')->first();

            $this->inventoryService->moveToProduction(
                $item->product_id,
                $item->quantity
            );

            $prodOrder->update([
                'status'         => 'in_progress',
                'started_at'     => now(),
                'internal_notes' => $notes ?? $prodOrder->internal_notes,
            ]);

            return $prodOrder->fresh();
        });
    }

    /**
     * Completa la producción: in_progress → completed.
     * Efecto en inventario: qty_in_production → qty_available.
     * Si todos los ítems de la orden están completos, la orden pasa a 'ready'.
     */
    public function complete(ProductionOrder $prodOrder, User $actor, ?string $notes = null): ProductionOrder
    {
        return DB::transaction(function () use ($prodOrder, $actor, $notes) {

            $prodOrder = ProductionOrder::lockForUpdate()->findOrFail($prodOrder->id);
            $this->assertCanTransition($prodOrder, 'completed');

            $item = $prodOrder->orderItem;

            $this->inventoryService->completeProduction(
                $item->product_id,
                $item->quantity
            );

            $prodOrder->update([
                'status'         => 'completed',
                'completed_at'   => now(),
                'internal_notes' => $notes ?? $prodOrder->internal_notes,
            ]);

            $this->checkAndAutoReady($item->order_id, $actor);

            return $prodOrder->fresh();
        });
    }

    /**
     * Cancela una orden de producción: (queued|in_progress) → cancelled.
     * Efecto en inventario solo si estaba en 'in_progress':
     *   qty_in_production → qty_reserved (revierte el moveToProduction).
     */
    public function cancel(ProductionOrder $prodOrder, User $actor, ?string $notes = null): ProductionOrder
    {
        return DB::transaction(function () use ($prodOrder, $actor, $notes) {

            $prodOrder = ProductionOrder::lockForUpdate()->findOrFail($prodOrder->id);
            $this->assertCanTransition($prodOrder, 'cancelled');

            $previousStatus = $prodOrder->status;

            $prodOrder->update([
                'status'         => 'cancelled',
                'internal_notes' => $notes ?? $prodOrder->internal_notes,
            ]);

            // Revertir el moveToProduction si ya había iniciado
            if ($previousStatus === 'in_progress') {
                $item = $prodOrder->orderItem;
                $this->inventoryService->cancelProduction(
                    $item->product_id,
                    $item->quantity
                );
            }

            return $prodOrder->fresh();
        });
    }

    /**
     * Asigna o reasigna el trabajador responsable de una orden de producción.
     * Permitido en cualquier estado activo (queued o in_progress).
     */
    public function assign(ProductionOrder $prodOrder, ?string $workerId): ProductionOrder
    {
        return DB::transaction(function () use ($prodOrder, $workerId) {

            $prodOrder = ProductionOrder::lockForUpdate()->findOrFail($prodOrder->id);

            if (!$prodOrder->is_active) {
                throw new LogicException(
                    "No se puede reasignar un trabajador a una orden de producción en estado '{$prodOrder->status}'."
                );
            }

            $prodOrder->update(['assigned_worker_id' => $workerId]);

            return $prodOrder->fresh(['worker:id,name']);
        });
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Comprueba si todos los ítems del pedido tienen producción completada y,
     * de ser así, transiciona la orden a 'ready'.
     */
    private function checkAndAutoReady(string $orderId, User $actor): void
    {
        $order = Order::lockForUpdate()->findOrFail($orderId);

        if ($order->status !== 'in_production') {
            return;
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $hasCompletedProdOrder = ProductionOrder::where('order_item_id', $item->id)
                ->where('status', 'completed')
                ->exists();

            if (!$hasCompletedProdOrder) {
                return; // Al menos un ítem sin producción completada
            }
        }

        // Todos los ítems tienen producción completada
        $this->orderService->transition(
            $order,
            'ready',
            $actor,
            'Producción completada en todos los ítems. Orden lista para envío.'
        );
    }

    private function assertOrderAllowsProduction(Order $order): void
    {
        if (!in_array($order->status, self::ALLOWED_ORDER_STATUSES)) {
            $allowed = implode(', ', self::ALLOWED_ORDER_STATUSES);
            throw new LogicException(
                "Solo se pueden crear órdenes de producción para pedidos en estado: {$allowed}. " .
                "Estado actual: '{$order->status}'."
            );
        }
    }

    private function assertNoActiveProdOrder(OrderItem $item): void
    {
        $exists = ProductionOrder::where('order_item_id', $item->id)
            ->active()
            ->exists();

        if ($exists) {
            throw new LogicException(
                "El ítem '{$item->product_name}' ya tiene una orden de producción activa."
            );
        }
    }

    private function assertCanTransition(ProductionOrder $prodOrder, string $newStatus): void
    {
        if (!$prodOrder->canTransitionTo($newStatus)) {
            $allowed = implode(', ', $prodOrder->allowed_transitions) ?: 'ninguna';
            throw new LogicException(
                "No se puede cambiar el estado de '{$prodOrder->status}' a '{$newStatus}'. " .
                "Transiciones permitidas: {$allowed}."
            );
        }
    }
}
