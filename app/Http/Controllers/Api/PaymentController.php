<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    /**
     * GET /api/payments
     *
     * Listado global de pagos con filtros opcionales.
     * Filtros disponibles:
     *   ?status=completed|pending|failed|refunded
     *   ?order_id=uuid
     *   ?payment_method=efectivo|...
     *   ?from=2026-01-01
     *   ?to=2026-12-31
     */
    public function index(Request $request)
    {
        $request->validate([
            'status'         => 'nullable|string|in:pending,completed,failed,refunded',
            'order_id'       => 'nullable|uuid|exists:orders,id',
            'payment_method' => 'nullable|string|in:' . implode(',', Payment::METHODS),
            'from'           => 'nullable|date',
            'to'             => 'nullable|date|after_or_equal:from',
        ]);

        $query = Payment::with('order:id,status,total_amount,customer_id')
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        return PaymentResource::collection($query->paginate(20));
    }

    /**
     * GET /api/orders/{order}/payments
     *
     * Lista todos los pagos de una orden + balance financiero.
     */
    public function indexForOrder(Order $order)
    {
        $payments = Payment::forOrder($order->id)
            ->orderBy('created_at', 'asc')
            ->get();

        $balance = $this->paymentService->getOrderBalance($order);

        return response()->json([
            'balance'  => $balance,
            'payments' => PaymentResource::collection($payments),
        ]);
    }

    /**
     * POST /api/orders/{order}/payments
     *
     * Registra un nuevo pago para la orden.
     * Con mark_completed=true el pago se confirma al instante
     * (efectivo / transferencia ya verificada).
     */
    public function store(StorePaymentRequest $request, Order $order)
    {
        $data          = $request->validated();
        $markCompleted = (bool) ($data['mark_completed'] ?? false);

        try {
            $payment = $this->paymentService->register($order, $data, $request->user(), $markCompleted);
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $balance = $this->paymentService->getOrderBalance($order->fresh());

        return response()->json([
            'message' => 'Pago registrado correctamente.',
            'payment' => (new PaymentResource($payment))
                ->additional(['balance' => $balance]),
            'balance' => $balance,
        ], 201);
    }

    /**
     * PATCH /api/orders/{order}/payments/{payment}/complete
     *
     * Marca un pago 'pending' como 'completed'.
     * Puede disparar auto-confirmación de la orden si queda totalmente pagada.
     */
    public function complete(Order $order, Payment $payment)
    {
        $this->assertPaymentBelongsToOrder($payment, $order);

        try {
            $payment = $this->paymentService->complete($payment);
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $balance = $this->paymentService->getOrderBalance($order->fresh());

        return response()->json([
            'message' => 'Pago completado correctamente.',
            'payment' => (new PaymentResource($payment))
                ->additional(['balance' => $balance]),
            'balance' => $balance,
        ]);
    }

    /**
     * PATCH /api/orders/{order}/payments/{payment}/fail
     *
     * Marca un pago 'pending' como 'failed'.
     */
    public function fail(Order $order, Payment $payment)
    {
        $this->assertPaymentBelongsToOrder($payment, $order);

        try {
            $payment = $this->paymentService->fail($payment);
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Pago marcado como fallido.',
            'payment' => new PaymentResource($payment),
        ]);
    }

    /**
     * PATCH /api/orders/{order}/payments/{payment}/refund
     *
     * Reembolsa un pago 'completed'.
     * Requiere devolución aprobada/resuelta en la orden.
     */
    public function refund(Request $request, Order $order, Payment $payment)
    {
        $this->assertPaymentBelongsToOrder($payment, $order);

        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $payment = $this->paymentService->refund($payment, $request->notes);
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $balance = $this->paymentService->getOrderBalance($order->fresh());

        return response()->json([
            'message' => 'Pago reembolsado correctamente.',
            'payment' => (new PaymentResource($payment))
                ->additional(['balance' => $balance]),
            'balance' => $balance,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function assertPaymentBelongsToOrder(Payment $payment, Order $order): void
    {
        if ($payment->order_id !== $order->id) {
            abort(404, 'El pago no pertenece a esta orden.');
        }
    }
}
