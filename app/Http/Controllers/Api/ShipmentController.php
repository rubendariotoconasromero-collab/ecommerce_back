<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shipment\StoreShipmentRequest;
use App\Http\Requests\Shipment\UpdateShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\ShipmentService;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function __construct(private readonly ShipmentService $shipmentService) {}

    /**
     * GET /api/shipments
     *
     * Listado global de envíos con filtros opcionales.
     * Filtros:
     *   ?status=preparing|shipped|in_transit|delivered|failed
     *   ?order_id=uuid
     *   ?courier_name=texto
     *   ?handler_id=uuid
     *   ?from=2026-01-01
     *   ?to=2026-12-31
     */
    public function index(Request $request)
    {
        $request->validate([
            'status'      => 'nullable|string|in:preparing,shipped,in_transit,delivered,failed',
            'order_id'    => 'nullable|uuid|exists:orders,id',
            'courier_name'=> 'nullable|string|max:100',
            'handler_id'  => 'nullable|uuid|exists:users,id',
            'from'        => 'nullable|date',
            'to'          => 'nullable|date|after_or_equal:from',
        ]);

        $query = Shipment::with([
                'order:id,status,total_amount,shipping_address,customer_id',
                'handler:id,name',
            ])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('order_id')) {
            $query->forOrder($request->order_id);
        }

        if ($request->filled('courier_name')) {
            $query->withCourier($request->courier_name);
        }

        if ($request->filled('handler_id')) {
            $query->where('handler_id', $request->handler_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        return ShipmentResource::collection($query->paginate(20));
    }

    /**
     * GET /api/orders/{order}/shipments
     *
     * Lista todos los envíos de una orden (historial de intentos).
     */
    public function indexForOrder(Order $order)
    {
        $shipments = Shipment::forOrder($order->id)
            ->with('handler:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return ShipmentResource::collection($shipments);
    }

    /**
     * POST /api/orders/{order}/shipments
     *
     * Crea un nuevo envío para la orden.
     *  - Orden en 'ready'   → auto-transiciona a 'shipped'.
     *  - Orden en 'shipped' → reenvío tras fallo; no modifica estado de orden.
     * El envío inicia siempre en estado 'preparing'.
     */
    public function store(StoreShipmentRequest $request, Order $order)
    {
        try {
            $shipment = $this->shipmentService->create($order, $request->validated(), $request->user());
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'  => 'Envío creado correctamente.',
            'shipment' => new ShipmentResource($shipment),
        ], 201);
    }

    /**
     * GET /api/orders/{order}/shipments/{shipment}
     *
     * Detalle de un envío.
     */
    public function show(Order $order, Shipment $shipment)
    {
        $this->assertShipmentBelongsToOrder($shipment, $order);

        $shipment->load([
            'order:id,status,total_amount,shipping_address',
            'handler:id,name',
        ]);

        return new ShipmentResource($shipment);
    }

    /**
     * PATCH /api/orders/{order}/shipments/{shipment}/dispatch
     *
     * Despacha el envío: preparing → shipped.
     * Registra shipped_at. Permite actualizar tracking_number, courier y handler.
     */
    public function dispatch(UpdateShipmentRequest $request, Order $order, Shipment $shipment)
    {
        $this->assertShipmentBelongsToOrder($shipment, $order);

        try {
            $shipment = $this->shipmentService->dispatch($shipment, $request->validated(), $request->user());
        } catch (\LogicException $e) {
            return response()->json([
                'message'             => $e->getMessage(),
                'allowed_transitions' => $shipment->allowed_transitions,
            ], 422);
        }

        return response()->json([
            'message'  => 'Envío despachado correctamente.',
            'shipment' => new ShipmentResource($shipment),
        ]);
    }

    /**
     * PATCH /api/orders/{order}/shipments/{shipment}/in-transit
     *
     * Marca el envío en tránsito: shipped → in_transit.
     */
    public function inTransit(UpdateShipmentRequest $request, Order $order, Shipment $shipment)
    {
        $this->assertShipmentBelongsToOrder($shipment, $order);

        try {
            $shipment = $this->shipmentService->markInTransit($shipment, $request->validated(), $request->user());
        } catch (\LogicException $e) {
            return response()->json([
                'message'             => $e->getMessage(),
                'allowed_transitions' => $shipment->allowed_transitions,
            ], 422);
        }

        return response()->json([
            'message'  => 'Envío marcado en tránsito.',
            'shipment' => new ShipmentResource($shipment),
        ]);
    }

    /**
     * PATCH /api/orders/{order}/shipments/{shipment}/deliver
     *
     * Confirma la entrega: (shipped|in_transit) → delivered.
     * Auto-transiciona la orden de 'shipped' a 'delivered'.
     */
    public function deliver(UpdateShipmentRequest $request, Order $order, Shipment $shipment)
    {
        $this->assertShipmentBelongsToOrder($shipment, $order);

        try {
            $shipment = $this->shipmentService->deliver($shipment, $request->user(), $request->validated('notes'));
        } catch (\LogicException $e) {
            return response()->json([
                'message'             => $e->getMessage(),
                'allowed_transitions' => $shipment->allowed_transitions,
            ], 422);
        }

        return response()->json([
            'message'  => 'Entrega confirmada. La orden ha sido marcada como entregada.',
            'shipment' => new ShipmentResource($shipment->load('order:id,status,total_amount')),
        ]);
    }

    /**
     * PATCH /api/orders/{order}/shipments/{shipment}/fail
     *
     * Marca el envío como fallido. La orden permanece en 'shipped'.
     * El campo failure_reason es obligatorio.
     */
    public function fail(UpdateShipmentRequest $request, Order $order, Shipment $shipment)
    {
        $this->assertShipmentBelongsToOrder($shipment, $order);

        $failureReason = $request->validated('failure_reason');

        if (empty($failureReason)) {
            return response()->json(['message' => 'El motivo de fallo es obligatorio.'], 422);
        }

        try {
            $shipment = $this->shipmentService->fail($shipment, $failureReason, $request->user());
        } catch (\LogicException $e) {
            return response()->json([
                'message'             => $e->getMessage(),
                'allowed_transitions' => $shipment->allowed_transitions,
            ], 422);
        }

        return response()->json([
            'message'  => 'Envío marcado como fallido. Puede crear un nuevo envío para reintentar.',
            'shipment' => new ShipmentResource($shipment),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function assertShipmentBelongsToOrder(Shipment $shipment, Order $order): void
    {
        if ($shipment->order_id !== $order->id) {
            abort(404, 'El envío no pertenece a esta orden.');
        }
    }
}
