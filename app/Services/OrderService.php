<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderHandler;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Mapa de transiciones de estado permitidas.
     * pending → in_production habilita el flujo make-to-order (sin reserva de stock).
     */
    private const TRANSITIONS = [
        'pending'       => ['confirmed', 'in_production', 'cancelled'],
        'confirmed'     => ['in_production', 'cancelled'],
        'in_production' => ['ready', 'cancelled'],
        'ready'         => ['shipped', 'cancelled'],
        'shipped'       => ['delivered'],
        'delivered'     => ['returned'],
        'cancelled'     => [],
        'returned'      => [],
    ];

    public function __construct(private readonly InventoryService $inventoryService) {}

    // =========================================================================
    // CREAR ORDEN
    // =========================================================================

    /**
     * Crea una orden completa con sus items dentro de una transacción atómica.
     *
     * - Los precios se calculan en el servidor (nunca desde el frontend).
     * - Se hace snapshot de nombre y precios del producto al momento de la venta.
     * - El stock NO se reserva al crear: se reserva al confirmar.
     * - La fecha de entrega se calcula si no se proporciona.
     */
    public function create(array $data, ?User $actor): Order
    {
        return DB::transaction(function () use ($data, $actor) {

            // 1. Cargar todos los productos en una sola consulta
            $productIds = array_column($data['items'], 'product_id');
            $products   = Product::whereIn('id', $productIds)->get()->keyBy('id');

            // 2. Validar que todos los productos existen y están activos
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);

                if (!$product) {
                    throw new \InvalidArgumentException(
                        "El producto '{$item['product_id']}' no existe."
                    );
                }
                if (!$product->is_active) {
                    throw new \InvalidArgumentException(
                        "El producto '{$product->name}' no está disponible para la venta."
                    );
                }
            }

            // 3. Calcular items (precios siempre del servidor)
            $calculatedItems  = $this->buildItems($data['items'], $products);
            $totalAmount      = collect($calculatedItems)->sum('subtotal');

            // 4. Calcular fecha de entrega estimada si no se proporcionó
            $expectedDelivery = $data['expected_delivery_date']
                ?? $this->estimateDeliveryDate($products, $data['items']);

            // 5. Crear la orden (user_id null cuando proviene de la tienda pública)
            $order = Order::create([
                'customer_id'           => $data['customer_id'],
                'user_id'               => $actor?->id,
                'order_number'          => $this->generateOrderNumber(),
                'total_amount'          => $totalAmount,
                'status'                => 'pending',
                'expected_delivery_date'=> $expectedDelivery,
                'shipping_address'      => $data['shipping_address'],
                'notes'                 => $data['notes'] ?? null,
            ]);

            // 6. Crear los items con snapshot de precios
            foreach ($calculatedItems as $itemData) {
                OrderItem::create(array_merge($itemData, ['order_id' => $order->id]));
            }

            // 7. Registrar en el historial
            if ($actor) {
                $this->recordHandler(
                    $order,
                    $actor,
                    'Orden creada en estado pendiente.',
                    $data['notes'] ?? null
                );
            } else {
                // Pedido registrado desde la tienda en línea por el cliente
                \App\Models\OrderHandler::create([
                    'order_id'     => $order->id,
                    'user_id'      => null,
                    'handler_name' => 'Cliente Web',
                    'handler_role' => 'Cliente',
                    'action_taken' => 'Pedido registrado desde la tienda en línea.',
                    'notes'        => $data['notes'] ?? null,
                ]);
            }

            return $order->load(['customer:id,name,email,customer_type,business_name', 'items']);
        });
    }

    // =========================================================================
    // CAMBIAR ESTADO
    // =========================================================================

    /**
     * Ejecuta una transición de estado validada con todos sus efectos secundarios.
     *
     * Efectos por transición:
     *  pending → confirmed    : reserva stock (lanza InsufficientStockException si falta)
     *  *       → cancelled    : libera stock si ya estaba reservado
     *  Otros                  : solo actualizan estado y registran handler
     */
    public function transition(Order $order, string $newStatus, User $actor, ?string $notes = null): Order
    {
        // Validar que la transición sea permitida
        $allowed = self::TRANSITIONS[$order->status] ?? [];

        if (!in_array($newStatus, $allowed)) {
            throw new \LogicException(
                "No se puede cambiar el estado de '{$order->status}' a '{$newStatus}'. " .
                "Transiciones permitidas: " . (empty($allowed) ? 'ninguna' : implode(', ', $allowed)) . '.'
            );
        }

        return DB::transaction(function () use ($order, $newStatus, $actor, $notes) {

            // Efectos secundarios ANTES de cambiar el estado
            match ($newStatus) {
                'confirmed' => $this->reserveStock($order),
                'cancelled' => $this->releaseStockIfNeeded($order),
                default     => null,
            };

            // Actualizar estado
            $order->update(['status' => $newStatus]);

            // Registrar en el historial
            $this->recordHandler(
                $order,
                $actor,
                "Estado cambiado a: {$newStatus}.",
                $notes
            );

            return $order->fresh();
        });
    }

    // =========================================================================
    // CONSULTAS AUXILIARES
    // =========================================================================

    public function getAllowedTransitions(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }

    public function canTransitionTo(Order $order, string $newStatus): bool
    {
        return in_array($newStatus, self::TRANSITIONS[$order->status] ?? []);
    }

    // =========================================================================
    // LÓGICA DE STOCK
    // =========================================================================

    private function reserveStock(Order $order): void
    {
        $order->loadMissing('items');

        $productIds = $order->items->pluck('product_id')->unique();
        $products   = Product::whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($order->items as $item) {
            $product = $products->get($item->product_id);

            if (!$product || !$product->is_active) {
                throw new \InvalidArgumentException(
                    "El producto '{$item->product_name}' fue desactivado y ya no puede ser confirmado en esta orden."
                );
            }

            $this->inventoryService->reserve(
                $item->product_id,
                $item->quantity,
                $item->product_name
            );
        }

        // Marcar que este pedido tiene stock reservado para liberar correctamente al cancelar.
        $order->update(['stock_reserved' => true]);
    }

    private function releaseStockIfNeeded(Order $order): void
    {
        // Solo liberar si el stock fue efectivamente reservado (flujo make-to-stock).
        // Los pedidos make-to-order (pending → in_production) nunca reservan stock.
        if (!$order->stock_reserved) {
            return;
        }

        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $this->inventoryService->release($item->product_id, $item->quantity);
        }

        $order->update(['stock_reserved' => false]);
    }

    // =========================================================================
    // HELPERS INTERNOS
    // =========================================================================

    /**
     * Construye los datos calculados de cada item.
     * unit_price  = sale_price del producto
     * unit_cost   = cost_price del producto
     * subtotal    = unit_price * quantity
     * line_profit = (unit_price - unit_cost) * quantity
     */
    private function buildItems(array $rawItems, $products): array
    {
        return array_map(function (array $raw) use ($products) {
            $product    = $products->get($raw['product_id']);
            $unitPrice  = (float) $product->sale_price;
            $unitCost   = (float) $product->cost_price;
            $quantity   = (int) $raw['quantity'];

            return [
                'product_id'           => $product->id,
                'product_name'         => $product->name,
                'quantity'             => $quantity,
                'unit_cost'            => $unitCost,
                'unit_price'           => $unitPrice,
                'subtotal'             => round($unitPrice * $quantity, 2),
                'line_profit'          => round(($unitPrice - $unitCost) * $quantity, 2),
                'customization_notes'  => $raw['customization_notes'] ?? null,
                'reference_image_path' => $raw['reference_image_path'] ?? null,
            ];
        }, $rawItems);
    }

    /**
     * Calcula la fecha de entrega estimada sumando al día de hoy
     * el mayor production_lead_time_days de los productos del pedido.
     */
    private function estimateDeliveryDate($products, array $rawItems): string
    {
        $maxLeadDays = collect($rawItems)
            ->map(fn ($item) => $products->get($item['product_id'])?->production_lead_time_days ?? 0)
            ->max();

        return Carbon::today()->addDays(max(1, $maxLeadDays))->toDateString();
    }

    /**
     * Registra una entrada en el historial de gestión de la orden.
     */
    private function recordHandler(Order $order, User $actor, string $action, ?string $notes = null): void
    {
        OrderHandler::create([
            'order_id'     => $order->id,
            'user_id'      => $actor->id,
            'handler_name' => $actor->name,
            'handler_role' => $actor->role?->name ?? 'Sistema',
            'action_taken' => $action,
            'notes'        => $notes,
        ]);
    }

    /**
     * Genera un número de orden correlativo por año con formato PED-YYYY-NNNNN.
     * Se llama dentro de una transacción con lockForUpdate para garantizar unicidad
     * bajo concurrencia. El contador se reinicia cada año.
     */
    private function generateOrderNumber(): string
    {
        $year   = now()->year;
        $prefix = "PED-{$year}-";

        $last = Order::where('order_number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->max('order_number');

        $seq = $last ? ((int) substr($last, -5)) + 1 : 1;

        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
