<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    // Estatuses de orden que bloquean la desactivación de un cliente
    private const ACTIVE_ORDER_STATUSES = [
        'pending', 'confirmed', 'in_production', 'ready', 'shipped',
    ];

    /**
     * GET /api/customers
     * Lista paginada con filtros: search, type, active.
     */
    public function index(Request $request)
    {
        $query = Customer::query()
            ->withCount([
                'orders',
                'orders as active_orders_count' => fn ($q) =>
                    $q->whereIn('status', self::ACTIVE_ORDER_STATUSES),
            ])
            ->withSum(
                ['orders as total_spent' => fn ($q) => $q->where('status', 'delivered')],
                'total_amount'
            );

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('customer_type', $request->type);
        }

        if ($request->filled('active')) {
            $query->where('is_active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate(15);

        return CustomerResource::collection($customers);
    }

    /**
     * POST /api/customers
     */
    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create($request->validated());

        return response()->json([
            'message'  => 'Cliente creado exitosamente.',
            'customer' => new CustomerResource($customer),
        ], 201);
    }

    /**
     * GET /api/customers/{customer}
     * Muestra el cliente con su usuario vinculado, estadísticas y últimas 10 órdenes.
     */
    public function show(Customer $customer)
    {
        $customer->load('user:id,name,email');

        // Últimas 10 órdenes se exponen como relación virtual
        $customer->setRelation(
            'recentOrders',
            $customer->orders()
                     ->select('id', 'status', 'total_amount', 'created_at')
                     ->latest()
                     ->limit(10)
                     ->get()
        );

        // Agregados estadísticos
        $customer->orders_count        = $customer->orders()->count();
        $customer->active_orders_count = $customer->orders()
                                                   ->whereIn('status', self::ACTIVE_ORDER_STATUSES)
                                                   ->count();
        $customer->total_spent         = $customer->orders()
                                                   ->where('status', 'delivered')
                                                   ->sum('total_amount');

        return new CustomerResource($customer);
    }

    /**
     * PUT /api/customers/{customer}
     */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return response()->json([
            'message'  => 'Cliente actualizado exitosamente.',
            'customer' => new CustomerResource($customer->fresh()),
        ]);
    }

    /**
     * DELETE /api/customers/{customer}
     * Desactivación lógica (is_active = false).
     * Bloqueada si el cliente tiene órdenes activas.
     */
    public function destroy(Customer $customer)
    {
        if ($customer->orders()->whereIn('status', self::ACTIVE_ORDER_STATUSES)->exists()) {
            return response()->json([
                'message' => 'No se puede desactivar un cliente con órdenes activas pendientes de resolución.',
            ], 422);
        }

        $customer->update(['is_active' => false]);

        return response()->json(['message' => 'Cliente desactivado correctamente.']);
    }

    /**
     * GET /public/customers/lookup?tax_id=xxx
     * Búsqueda pública por NIT/CI — sin autenticación.
     */
    public function lookup(Request $request)
    {
        $request->validate(['tax_id' => 'required|string|max:30']);

        $customer = Customer::where('tax_id', $request->tax_id)
                            ->where('is_active', true)
                            ->first();

        if (!$customer) {
            return response()->json(['found' => false], 404);
        }

        return response()->json([
            'found'  => true,
            'name'   => $customer->business_name ?: $customer->name,
            'tax_id' => $customer->tax_id,
            'phone'  => $customer->phone,
            'email'  => $customer->email,
        ]);
    }

    /**
     * PATCH /api/customers/{customer}/restore
     * Reactiva un cliente desactivado.
     */
    public function restore(Customer $customer)
    {
        if ($customer->is_active) {
            return response()->json(['message' => 'El cliente ya está activo.'], 422);
        }

        $customer->update(['is_active' => true]);

        return response()->json([
            'message'  => 'Cliente reactivado correctamente.',
            'customer' => new CustomerResource($customer->fresh()),
        ]);
    }
}
