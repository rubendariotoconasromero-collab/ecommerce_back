<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Return\StoreReturnRequest;
use App\Http\Requests\Return\UpdateReturnStatusRequest;
use App\Http\Resources\OrderReturnResource;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Services\ReturnService;
use Illuminate\Http\Request;

class OrderReturnController extends Controller
{
    public function __construct(private readonly ReturnService $returnService) {}

    /**
     * GET /api/returns
     *
     * Listado global de devoluciones con filtros opcionales.
     * Filtros:
     *   ?status=requested|approved|rejected|resolved
     *   ?return_type=full|partial
     *   ?order_id=uuid
     *   ?from=2026-01-01
     *   ?to=2026-12-31
     */
    public function index(Request $request)
    {
        $request->validate([
            'status'      => 'nullable|string|in:requested,approved,rejected,resolved',
            'return_type' => 'nullable|string|in:full,partial',
            'order_id'    => 'nullable|uuid|exists:orders,id',
            'from'        => 'nullable|date',
            'to'          => 'nullable|date|after_or_equal:from',
        ]);

        $query = OrderReturn::with([
                'order:id,status,total_amount,customer_id',
                'orderItem:id,product_name,quantity,subtotal',
            ])
            ->orderBy('request_at', 'desc');

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('return_type')) {
            $query->where('return_type', $request->return_type);
        }

        if ($request->filled('order_id')) {
            $query->forOrder($request->order_id);
        }

        if ($request->filled('from')) {
            $query->where('request_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('request_at', '<=', $request->to . ' 23:59:59');
        }

        return OrderReturnResource::collection($query->paginate(20));
    }

    /**
     * GET /api/orders/{order}/returns
     *
     * Lista todas las devoluciones de una orden específica.
     */
    public function indexForOrder(Order $order)
    {
        $returns = OrderReturn::forOrder($order->id)
            ->with(['orderItem:id,product_name,quantity,subtotal'])
            ->orderBy('request_at', 'desc')
            ->get();

        return OrderReturnResource::collection($returns);
    }

    /**
     * GET /api/orders/{order}/returns/{return}
     *
     * Detalle de una devolución.
     */
    public function show(Order $order, OrderReturn $return)
    {
        $this->assertReturnBelongsToOrder($return, $order);

        $return->load([
            'order:id,status,total_amount',
            'orderItem:id,product_name,quantity,unit_price,subtotal',
        ]);

        return new OrderReturnResource($return);
    }

    /**
     * POST /api/orders/{order}/returns
     *
     * Crea una nueva solicitud de devolución para una orden entregada.
     */
    public function store(StoreReturnRequest $request, Order $order)
    {
        try {
            $return = $this->returnService->request($order, $request->validated(), $request->user());
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Solicitud de devolución registrada correctamente.',
            'return'  => new OrderReturnResource($return),
        ], 201);
    }

    /**
     * PATCH /api/orders/{order}/returns/{return}/approve
     *
     * Aprueba una devolución en estado 'requested'.
     * Para devoluciones 'full': transiciona la orden a 'returned'.
     */
    public function approve(UpdateReturnStatusRequest $request, Order $order, OrderReturn $return)
    {
        $this->assertReturnBelongsToOrder($return, $order);

        // Opcionalmente actualiza el monto de reembolso al aprobar
        if ($request->filled('refund_amount')) {
            $return->update(['refund_amount' => $request->validated('refund_amount')]);
        }

        try {
            $return = $this->returnService->approve($return, $request->user(), $request->validated('notes'));
        } catch (\LogicException $e) {
            return response()->json([
                'message'             => $e->getMessage(),
                'allowed_transitions' => $return->allowed_transitions,
            ], 422);
        }

        return response()->json([
            'message' => 'Devolución aprobada correctamente.',
            'return'  => new OrderReturnResource($return->load('order:id,status,total_amount')),
        ]);
    }

    /**
     * PATCH /api/orders/{order}/returns/{return}/reject
     *
     * Rechaza una devolución en estado 'requested'.
     */
    public function reject(UpdateReturnStatusRequest $request, Order $order, OrderReturn $return)
    {
        $this->assertReturnBelongsToOrder($return, $order);

        try {
            $return = $this->returnService->reject($return, $request->user(), $request->validated('notes'));
        } catch (\LogicException $e) {
            return response()->json([
                'message'             => $e->getMessage(),
                'allowed_transitions' => $return->allowed_transitions,
            ], 422);
        }

        return response()->json([
            'message' => 'Devolución rechazada correctamente.',
            'return'  => new OrderReturnResource($return),
        ]);
    }

    /**
     * PATCH /api/orders/{order}/returns/{return}/resolve
     *
     * Marca una devolución aprobada como resuelta (reembolso ejecutado).
     * Habilita el uso de PaymentService::refund() para los pagos de esta orden.
     */
    public function resolve(UpdateReturnStatusRequest $request, Order $order, OrderReturn $return)
    {
        $this->assertReturnBelongsToOrder($return, $order);

        try {
            $return = $this->returnService->resolve($return, $request->user(), $request->validated('notes'));
        } catch (\LogicException $e) {
            return response()->json([
                'message'             => $e->getMessage(),
                'allowed_transitions' => $return->allowed_transitions,
            ], 422);
        }

        return response()->json([
            'message' => 'Devolución resuelta correctamente.',
            'return'  => new OrderReturnResource($return),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function assertReturnBelongsToOrder(OrderReturn $return, Order $order): void
    {
        if ($return->order_id !== $order->id) {
            abort(404, 'La devolución no pertenece a esta orden.');
        }
    }
}
