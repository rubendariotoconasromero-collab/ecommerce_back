<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    /**
     * GET /api/orders
     *
     * Filtros disponibles:
     *   ?status=pending                — estado exacto
     *   ?status[]=pending&status[]=confirmed — múltiples estados
     *   ?customer_id=uuid
     *   ?user_id=uuid
     *   ?from=2026-01-01               — fecha de creación desde
     *   ?to=2026-12-31                 — fecha de creación hasta
     *   ?search=texto                  — busca en id o nombre de cliente
     */
    public function index(Request $request)
    {
        $request->validate([
            'status'      => 'nullable',
            'customer_id' => 'nullable|uuid|exists:customers,id',
            'user_id'     => 'nullable|uuid|exists:users,id',
            'from'        => 'nullable|date',
            'to'          => 'nullable|date|after_or_equal:from',
            'search'      => 'nullable|string|max:100',
        ]);

        $query = Order::with([
                'customer:id,name,email,customer_type,business_name',
                'user:id,name',
                'items:id,order_id,product_name,quantity,unit_price,subtotal,line_profit',
            ])
            ->withCount('items')
            ->withSum(
                ['payments as amount_paid' => fn ($q) => $q->where('status', 'completed')],
                'amount'
            );

        // Filtro de estado (acepta string único o array)
        if ($request->filled('status')) {
            $statuses = (array) $request->status;
            $query->whereIn('status', $statuses);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('orders.id', 'like', "{$search}%")
                  ->orWhereHas('customer', fn ($cq) =>
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('business_name', 'like', "%{$search}%")
                  );
            });
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20);

        return OrderResource::collection($orders);
    }

    /**
     * POST /api/orders
     *
     * Acepta multipart/form-data para permitir imágenes de referencia por ítem.
     * Los precios se calculan en el servidor; nunca desde el frontend.
     */
    public function store(StoreOrderRequest $request)
    {
        $data = $request->validated();

        // Procesar imágenes de referencia por ítem (si se subieron)
        foreach ($data['items'] as $index => &$item) {
            $file = $request->file("items.{$index}.reference_image");
            if ($file) {
                $item['reference_image_path'] = $file->store('orders/references', 'public');
            }
        }
        unset($item);

        try {
            $order = $this->orderService->create($data, $request->user());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Orden creada exitosamente.',
            'order'   => new OrderResource($order),
        ], 201);
    }

    /**
     * GET /api/orders/{order}
     * Devuelve el detalle completo con items, pagos, envío e historial.
     */
    public function show(Order $order)
    {
        $order->load([
            'customer:id,name,email,customer_type,business_name,phone',
            'user:id,name',
            'items',
            'payments',
            'shipments',
            'handlers',
        ]);

        return new OrderResource($order);
    }

    /**
     * PATCH /api/orders/{order}/status
     *
     * Cambia el estado de la orden ejecutando todos los efectos secundarios:
     *  - pending → confirmed : reserva stock (falla con 422 si stock insuficiente)
     *  - * → cancelled       : libera stock reservado
     *
     * Devuelve las transiciones disponibles después del cambio.
     */
    public function changeStatus(UpdateOrderStatusRequest $request, Order $order)
    {
        $newStatus = $request->validated('status');
        $notes     = $request->validated('notes');

        try {
            $updated = $this->orderService->transition(
                $order,
                $newStatus,
                $request->user(),
                $notes
            );
        } catch (\LogicException $e) {
            return response()->json([
                'message'             => $e->getMessage(),
                'allowed_transitions' => $this->orderService->getAllowedTransitions($order->status),
            ], 422);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'message'   => $e->getMessage(),
                'requested' => $e->requested,
                'available' => $e->available,
                'product'   => $e->productName,
            ], 422);
        }

        return response()->json([
            'message' => "Estado actualizado a '{$newStatus}' correctamente.",
            'order'   => new OrderResource($updated->load([
                'customer:id,name,email,customer_type',
                'items:id,order_id,product_name,quantity,subtotal',
            ])),
        ]);
    }

    /**
     * DELETE /api/orders/{order}
     * Solo cancela órdenes en estado 'pending'.
     * Para cancelar desde otros estados usar PATCH /status.
     */
    public function destroy(Request $request, Order $order)
    {
        if ($order->status !== 'pending') {
            return response()->json([
                'message' => "Solo se pueden cancelar directamente órdenes en estado 'pending'. " .
                             "Para cancelar esta orden use PATCH /api/orders/{$order->id}/status.",
            ], 422);
        }

        $this->orderService->transition($order, 'cancelled', $request->user(), 'Cancelación directa.');

        return response()->json(['message' => 'Orden cancelada correctamente.']);
    }
}
