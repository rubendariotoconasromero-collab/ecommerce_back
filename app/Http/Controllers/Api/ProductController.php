<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category:id,name,slug,image_url', 'images']);

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('active')) {
            $query->where('is_active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        $query->orderBy('created_at', 'desc');

        // Sin paginación: solo para catálogos públicos compactos, con límite máximo
        if ($request->boolean('nopaginate')) {
            return response()->json($query->limit(100)->get());
        }

        return response()->json($query->paginate(15));
    }

    public function store(StoreProductRequest $request)
    {
        $product = Product::create($request->validated());

        return response()->json([
            'message' => 'Producto registrado exitosamente',
            'product' => $product->load(['category', 'images']),
        ], 201);
    }

    public function show(Product $product)
    {
        return response()->json($product->load(['category', 'images', 'inventory']));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return response()->json([
            'message' => 'Producto actualizado exitosamente',
            'product' => $product->load(['category', 'images']),
        ]);
    }

    public function destroy(Product $product)
    {
        if ($product->orderItems()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar un producto que tiene órdenes registradas.',
            ], 422);
        }

        $product->delete();

        return response()->json(['message' => 'Producto eliminado correctamente']);
    }
}
