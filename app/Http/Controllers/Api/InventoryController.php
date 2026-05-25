<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryResource;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
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
     * PATCH /api/inventory/{product}/reorder-point
     * Actualiza únicamente el punto de reorden (stock mínimo de alerta).
     */
    public function updateReorderPoint(Request $request, Product $product)
    {
        $request->validate([
            'reorder_point' => 'required|integer|min:0',
        ], [
            'reorder_point.required' => 'El punto de reorden es obligatorio.',
            'reorder_point.integer'  => 'El punto de reorden debe ser un número entero.',
            'reorder_point.min'      => 'El punto de reorden no puede ser negativo.',
        ]);

        $inventory = Inventory::where('product_id', $product->id)->firstOrFail();
        $inventory->reorder_point = $request->reorder_point;
        $inventory->save();

        $inventory->load('product.category:id,name');

        return response()->json([
            'message'   => 'Punto de reorden actualizado.',
            'inventory' => new InventoryResource($inventory),
        ]);
    }
}
