<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
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

        if ($request->boolean('nopaginate')) {
            // Limitado a 100 y solo disponible en rutas autenticadas (admin)
            return ProductResource::collection($query->limit(100)->get());
        }

        return ProductResource::collection($query->paginate(15));
    }

    public function store(StoreProductRequest $request)
    {
        $product = Product::create($request->validated());

        return response()->json([
            'message' => 'Producto registrado exitosamente',
            'product' => new ProductResource($product->load(['category', 'images'])),
        ], 201);
    }

    public function show(Product $product)
    {
        return new ProductResource($product->load(['category', 'images', 'inventory']));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return response()->json([
            'message' => 'Producto actualizado exitosamente',
            'product' => new ProductResource($product->load(['category', 'images'])),
        ]);
    }

    public function destroy(Product $product)
    {
        $product->update(['is_active' => false]);

        return response()->json(['message' => 'Producto desactivado correctamente']);
    }

    public function restore(Product $product)
    {
        $product->update(['is_active' => true]);

        return response()->json(['message' => 'Producto reactivado correctamente']);
    }
}
