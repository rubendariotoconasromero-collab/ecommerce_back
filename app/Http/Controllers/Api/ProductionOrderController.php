<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Production\AssignWorkerRequest;
use App\Http\Requests\Production\StoreProductionOrderRequest;
use App\Http\Requests\Production\UpdateProductionOrderRequest;
use App\Http\Resources\ProductionOrderResource;
use App\Models\Order;
use App\Models\ProductionOrder;
use App\Services\ProductionService;
use Illuminate\Http\Request;

class ProductionOrderController extends Controller
{
    public function __construct(private readonly ProductionService $productionService) {}

    /**
     * GET /api/production-orders
     *
     * Listado global de órdenes de producción con filtros opcionales.
     * Filtros:
     *   ?status=queued|in_progress|completed|cancelled
     *   ?order_id=uuid           — filtra por pedido padre
     *   ?worker_id=uuid          — filtra por trabajador asignado
     *   ?unassigned=1            — solo sin trabajador asignado
     *   ?from=2026-01-01
     *   ?to=2026-12-31
     */
    public function index(Request $request)
    {
        $request->validate([
            'status'     => 'nullable|string|in:queued,in_progress,completed,cancelled',
            'order_id'   => 'nullable|uuid|exists:orders,id',
            'worker_id'  => 'nullable|uuid|exists:users,id',
            'unassigned' => 'nullable|boolean',
            'from'       => 'nullable|date',
            'to'         => 'nullable|date|after_or_equal:from',
        ]);

        $query = ProductionOrder::with([
                'orderItem:id,order_id,product_name,quantity,customization_notes,reference_image_path',
                'worker:id,name',
            ])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('order_id')) {
            $query->forOrder($request->order_id);
        }

        if ($request->filled('worker_id')) {
            $query->forWorker($request->worker_id);
        }

        if ($request->boolean('unassigned')) {
            $query->unassigned();
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        return ProductionOrderResource::collection($query->paginate(20));
    }

    /**
     * GET /api/orders/{order}/production-orders
     *
     * Lista todas las órdenes de producción de un pedido.
     */
    public function indexForOrder(Order $order)
    {
        $prodOrders = ProductionOrder::forOrder($order->id)
            ->with([
                'orderItem:id,order_id,product_name,quantity,customization_notes,reference_image_path',
                'worker:id,name',
            ])
            ->orderBy('created_at', 'asc')
            ->get();

        return ProductionOrderResource::collection($prodOrders);
    }

    /**
     * POST /api/orders/{order}/production-orders
     *
     * Crea órdenes de producción para el pedido.
     *
     *  - Con order_item_id: crea una sola orden para ese ítem.
     *  - Sin order_item_id: genera automáticamente una por cada ítem
     *    que no tenga orden de producción activa.
     *
     * Si la orden padre está en 'confirmed', la transiciona a 'in_production'.
     */
    public function store(StoreProductionOrderRequest $request, Order $order)
    {
        $data       = $request->validated();
        $itemId     = $data['order_item_id'] ?? null;

        try {
            if ($itemId) {
                $result  = $this->productionService->create($order, $itemId, $data, $request->user());
                $message = 'Orden de producción creada correctamente.';
                $payload = new ProductionOrderResource($result);
                $status  = 201;
            } else {
                $result  = $this->productionService->createForAllItems($order, $data, $request->user());
                $message = "Se crearon {$result->count()} orden(es) de producción.";
                $payload = ProductionOrderResource::collection($result);
                $status  = 201;
            }
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => $message, 'data' => $payload], $status);
    }

    /**
     * GET /api/orders/{order}/production-orders/{prodOrder}
     *
     * Detalle de una orden de producción.
     */
    public function show(Order $order, ProductionOrder $prodOrder)
    {
        $this->assertBelongsToOrder($prodOrder, $order);

        $prodOrder->load([
            'orderItem:id,order_id,product_name,quantity,unit_price,subtotal,customization_notes,reference_image_path',
            'worker:id,name',
        ]);

        return new ProductionOrderResource($prodOrder);
    }

    /**
     * PATCH /api/orders/{order}/production-orders/{prodOrder}/start
     *
     * Inicia la producción: queued → in_progress.
     * Efecto inventario: qty_reserved → qty_in_production.
     */
    public function start(UpdateProductionOrderRequest $request, Order $order, ProductionOrder $prodOrder)
    {
        $this->assertBelongsToOrder($prodOrder, $order);

        try {
            $prodOrder = $this->productionService->start(
                $prodOrder,
                $request->user(),
                $request->validated('internal_notes')
            );
        } catch (\LogicException $e) {
            return response()->json([
                'message'             => $e->getMessage(),
                'allowed_transitions' => $prodOrder->allowed_transitions,
            ], 422);
        }

        return response()->json([
            'message'   => 'Producción iniciada correctamente.',
            'prod_order'=> new ProductionOrderResource($prodOrder->load('worker:id,name')),
        ]);
    }

    /**
     * PATCH /api/orders/{order}/production-orders/{prodOrder}/complete
     *
     * Completa la producción: in_progress → completed.
     * Efecto inventario: qty_in_production → qty_available.
     * Si todos los ítems del pedido están completados, el pedido pasa a 'ready'.
     */
    public function complete(UpdateProductionOrderRequest $request, Order $order, ProductionOrder $prodOrder)
    {
        $this->assertBelongsToOrder($prodOrder, $order);

        try {
            $prodOrder = $this->productionService->complete(
                $prodOrder,
                $request->user(),
                $request->validated('internal_notes')
            );
        } catch (\LogicException $e) {
            return response()->json([
                'message'             => $e->getMessage(),
                'allowed_transitions' => $prodOrder->allowed_transitions,
            ], 422);
        }

        return response()->json([
            'message'   => 'Producción completada. Inventario actualizado.',
            'prod_order'=> new ProductionOrderResource($prodOrder->load('worker:id,name')),
        ]);
    }

    /**
     * PATCH /api/orders/{order}/production-orders/{prodOrder}/cancel
     *
     * Cancela la orden de producción.
     * Si estaba en 'in_progress', revierte el stock de in_production → reserved.
     */
    public function cancel(UpdateProductionOrderRequest $request, Order $order, ProductionOrder $prodOrder)
    {
        $this->assertBelongsToOrder($prodOrder, $order);

        try {
            $prodOrder = $this->productionService->cancel(
                $prodOrder,
                $request->user(),
                $request->validated('internal_notes')
            );
        } catch (\LogicException $e) {
            return response()->json([
                'message'             => $e->getMessage(),
                'allowed_transitions' => $prodOrder->allowed_transitions,
            ], 422);
        }

        return response()->json([
            'message'   => 'Orden de producción cancelada.',
            'prod_order'=> new ProductionOrderResource($prodOrder),
        ]);
    }

    /**
     * PATCH /api/orders/{order}/production-orders/{prodOrder}/assign
     *
     * Asigna o reasigna el trabajador responsable.
     * Enviar assigned_worker_id=null para desasignar.
     */
    public function assign(AssignWorkerRequest $request, Order $order, ProductionOrder $prodOrder)
    {
        $this->assertBelongsToOrder($prodOrder, $order);

        try {
            $prodOrder = $this->productionService->assign(
                $prodOrder,
                $request->validated('assigned_worker_id')
            );
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $workerName = $prodOrder->worker?->name ?? 'Sin asignar';

        return response()->json([
            'message'   => "Trabajador actualizado: {$workerName}.",
            'prod_order'=> new ProductionOrderResource($prodOrder),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function assertBelongsToOrder(ProductionOrder $prodOrder, Order $order): void
    {
        $prodOrder->loadMissing('orderItem');

        if ($prodOrder->orderItem->order_id !== $order->id) {
            abort(404, 'La orden de producción no pertenece a este pedido.');
        }
    }
}
