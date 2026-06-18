<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $now            = Carbon::now();
        $startMonth     = $now->copy()->startOfMonth();
        $startLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endLastMonth   = $now->copy()->subMonth()->endOfMonth();

        return response()->json([
            'orders'          => $this->orderStats($startMonth, $startLastMonth, $endLastMonth),
            'revenue'         => $this->revenueStats($startMonth, $startLastMonth, $endLastMonth),
            'customers'       => $this->customerStats($startMonth),
            'inventory'       => $this->inventoryStats(),
            'monthly_revenue' => $this->monthlyRevenue(),
            'recent_orders'   => $this->recentOrders(),
            'top_customers'   => $this->topCustomers(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Órdenes
    // -------------------------------------------------------------------------

    private function orderStats(Carbon $startMonth, Carbon $startLastMonth, Carbon $endLastMonth): array
    {
        $byStatus = Order::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $activeStatuses = ['pending', 'confirmed', 'in_production', 'ready', 'shipped'];

        return [
            'total'      => array_sum($byStatus),
            'this_month' => Order::whereDate('created_at', '>=', $startMonth)->count(),
            'last_month' => Order::whereBetween('created_at', [$startLastMonth, $endLastMonth])->count(),
            'active'     => Order::whereIn('status', $activeStatuses)->count(),
            'by_status'  => [
                'pending'       => $byStatus['pending']       ?? 0,
                'confirmed'     => $byStatus['confirmed']     ?? 0,
                'in_production' => $byStatus['in_production'] ?? 0,
                'ready'         => $byStatus['ready']         ?? 0,
                'shipped'       => $byStatus['shipped']       ?? 0,
                'delivered'     => $byStatus['delivered']     ?? 0,
                'cancelled'     => $byStatus['cancelled']     ?? 0,
                'returned'      => $byStatus['returned']      ?? 0,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Ingresos
    // -------------------------------------------------------------------------

    private function revenueStats(Carbon $startMonth, Carbon $startLastMonth, Carbon $endLastMonth): array
    {
        $thisMonth = Payment::where('status', 'completed')
            ->whereDate('paid_at', '>=', $startMonth)
            ->sum('amount');

        $lastMonth = Payment::where('status', 'completed')
            ->whereBetween('paid_at', [$startLastMonth, $endLastMonth])
            ->sum('amount');

        $pendingCollection = Payment::where('status', 'pending')->sum('amount');

        $growth = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
            : null;

        return [
            'this_month'         => (float) $thisMonth,
            'last_month'         => (float) $lastMonth,
            'growth_percent'     => $growth,
            'pending_collection' => (float) $pendingCollection,
        ];
    }

    // -------------------------------------------------------------------------
    // Clientes
    // -------------------------------------------------------------------------

    private function customerStats(Carbon $startMonth): array
    {
        return [
            'total'          => Customer::count(),
            'active'         => Customer::where('is_active', true)->count(),
            'new_this_month' => Customer::whereDate('created_at', '>=', $startMonth)->count(),
            'businesses'     => Customer::where('customer_type', 'business')->count(),
            'individuals'    => Customer::where('customer_type', 'individual')->count(),
        ];
    }

    // -------------------------------------------------------------------------
    // Inventario
    // -------------------------------------------------------------------------

    private function inventoryStats(): array
    {
        $lowStock   = Inventory::whereRaw('(qty_available - qty_reserved) <= reorder_point')->count();
        $outOfStock = Inventory::where('qty_available', '<=', 0)->count();
        $inProd     = Inventory::where('qty_in_production', '>', 0)->sum('qty_in_production');

        return [
            'low_stock_count'     => $lowStock,
            'out_of_stock_count'  => $outOfStock,
            'units_in_production' => (int) $inProd,
        ];
    }

    // -------------------------------------------------------------------------
    // Tendencia de ingresos — últimos 6 meses
    // -------------------------------------------------------------------------

    private function monthlyRevenue(): array
    {
        $result = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);

            $result[] = [
                'year'    => $month->year,
                'month'   => $month->month,
                'revenue' => (float) Payment::where('status', 'completed')
                    ->whereYear('paid_at', $month->year)
                    ->whereMonth('paid_at', $month->month)
                    ->sum('amount'),
                'orders'  => Order::whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->count(),
            ];
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Pedidos recientes
    // -------------------------------------------------------------------------

    private function recentOrders(): array
    {
        return Order::with(['customer:id,name,business_name,customer_type'])
            ->select('id', 'order_number', 'customer_id', 'total_amount', 'status', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get()
            ->map(fn ($o) => [
                'id'            => $o->id,
                'order_number'  => $o->order_number ?? strtoupper(substr($o->id, 0, 8)),
                'total_amount'  => (float) $o->total_amount,
                'status'        => $o->status,
                'created_at'    => $o->created_at->toIso8601String(),
                'customer_name' => $o->customer?->business_name ?? $o->customer?->name ?? '—',
            ])
            ->toArray();
    }

    // -------------------------------------------------------------------------
    // Top clientes por facturación
    // -------------------------------------------------------------------------

    private function topCustomers(): array
    {
        return Customer::select('customers.id', 'customers.name', 'customers.business_name', 'customers.customer_type')
            ->withSum(
                ['orders as total_spent' => fn ($q) => $q->where('status', 'delivered')],
                'total_amount'
            )
            ->withCount('orders')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get()
            ->map(fn ($c) => [
                'id'            => $c->id,
                'name'          => $c->business_name ?? $c->name,
                'customer_type' => $c->customer_type,
                'orders_count'  => $c->orders_count,
                'total_spent'   => (float) ($c->total_spent ?? 0),
            ])
            ->toArray();
    }
}
