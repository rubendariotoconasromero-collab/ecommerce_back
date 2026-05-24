<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustInventoryRequest;
use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * GET /api/inventory
     * Lista paginada del inventario con filtros:
     *   ?low_stock=true       — productos con qty_sellable <= reorder_point
     *   ?category_id=uuid     — filtra por categoría de producto
     *   ?search=term          — busca por nombre o SKU del producto
     *   ?active=true|false    — filtra por estado del producto
     */
    public function index(Request $request)
    {
        $request->validate([
            'low_stock'   => 'nullable|boolean',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'search'      => 'nullable|string|max:100',
            'active'      => 'nullable|boolean',
        ]);

        $query = Inventory::with(['product.category:id,name'])
            ->join('products', 'inventory.product_id', '=', 'products.id')
            ->select('inventory.*');

        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                  ->orWhere('products.sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('active')) {
            $query->where('products.is_active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }

        // Filtro de bajo stock: qty_available - qty_reserved <= reorder_point
        if ($request->boolean('low_stock')) {
            $query->whereRaw('(inventory.qty_available - inventory.qty_reserved) <= inventory.reorder_point');
        }

        $query->orderByRaw('(inventory.qty_available - inventory.qty_reserved) ASC');

        $inventory = $query->paginate(20);

        return InventoryResource::collection($inventory);
    }

    /**
     * GET /api/inventory/{product}
     * Devuelve la ficha de inventario de un producto específico.
     */
    public function show(Product $product)
    {
        $inventory = Inventory::with(['product.category:id,name'])
            ->where('product_id', $product->id)
            ->firstOrFail();

        return new InventoryResource($inventory);
    }

    /**
     * POST /api/inventory/{product}/adjust
     * Ajuste manual de stock por un administrador.
     *
     * Body: { qty_available: int, reorder_point: int, reason: string }
     *
     * Permite reducir qty_available por debajo de qty_reserved (se informa advertencia).
     */
    public function adjust(AdjustInventoryRequest $request, Product $product)
    {
        $inventory = Inventory::where('product_id', $product->id)->firstOrFail();

        $validated = $request->validated();

        $newQtyAvailable = $validated['qty_available'];
        $newReorderPoint = $validated['reorder_point'] ?? $inventory->reorder_point;

        $updated = $this->inventoryService->adjust(
            $inventory,
            $newQtyAvailable,
            $newReorderPoint,
            $validated['reason']
        );

        $updated->load('product.category:id,name');

        $response = [
            'message'   => 'Inventario ajustado correctamente.',
            'inventory' => new InventoryResource($updated),
        ];

        // Advertencia si hay conflicto con stock reservado
        if ($newQtyAvailable < $updated->qty_reserved) {
            $response['warning'] = sprintf(
                'El nuevo stock disponible (%d) es menor al stock reservado (%d). Hay %d unidad(es) en riesgo.',
                $newQtyAvailable,
                $updated->qty_reserved,
                $updated->qty_reserved - $newQtyAvailable
            );
        }

        return response()->json($response);
    }
}
