<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StorePublicOrderRequest;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;

class PublicOrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    /**
     * POST /api/public/orders
     *
     * Registra un pedido desde la tienda pública (landing) sin autenticación.
     * Flujo:
     *   1. Busca cliente existente por teléfono → email (con lock para evitar race condition).
     *   2. Si no existe, crea un perfil Customer de tipo inferido automáticamente.
     *   3. Crea la orden via OrderService (sin actor autenticado).
     *   4. Auto-genera un pago contraentrega en efectivo con status 'pending'.
     */
    public function store(StorePublicOrderRequest $request)
    {
        $validated = $request->validated();

        $customer = $this->findOrCreateCustomer($validated);

        $shippingParts = array_filter([
            $validated['address'],
            $validated['city'],
            !empty($validated['reference']) ? 'Ref: ' . $validated['reference'] : null,
        ]);

        $orderData = [
            'customer_id'      => $customer->id,
            'shipping_address' => implode(', ', $shippingParts),
            'notes'            => $validated['notes'] ?? null,
            'items'            => array_map(fn ($i) => [
                'product_id' => $i['product_id'],
                'quantity'   => $i['quantity'],
            ], $validated['items']),
        ];

        try {
            $order = $this->orderService->create($orderData, null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Pago contraentrega: se liquida cuando el repartidor entrega la mercadería
        Payment::create([
            'order_id'       => $order->id,
            'payment_method' => 'efectivo',
            'amount'         => $order->total_amount,
            'status'         => 'pending',
        ]);

        return response()->json([
            'message'       => 'Pedido registrado exitosamente.',
            'reference'     => $order->order_number ?? strtoupper(substr($order->id, 0, 8)),
            'order_id'      => $order->id,
            'total'         => (float) $order->total_amount,
            'delivery_date' => $order->expected_delivery_date,
        ], 201);
    }

    /**
     * Busca un cliente existente por teléfono o email dentro de una transacción
     * con lockForUpdate para evitar race conditions en requests concurrentes.
     * Si no existe, lo crea. El tipo (individual/business) se infiere automáticamente
     * desde el modelo Customer.
     */
    private function findOrCreateCustomer(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $phone = $data['phone'];
            $email = $data['email'] ?? null;

            $customer = Customer::where('phone', $phone)->lockForUpdate()->first();

            if (!$customer && $email) {
                $customer = Customer::where('email', $email)->lockForUpdate()->first();
            }

            if ($customer) {
                return $customer;
            }

            return Customer::create([
                'customer_type' => Customer::inferType($data['name'], $data['tax_id'] ?? ''),
                'name'          => $data['name'],
                'email'         => $email,
                'phone'         => $phone,
                'tax_id'        => $data['tax_id'] ?? null,
                'is_active'     => true,
            ]);
        });
    }
}
