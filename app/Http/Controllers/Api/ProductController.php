<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Requests\PublicProductRequest;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Iniciamos la consulta cargando la relación de categoría e imágenes
        $query = Product::with(['category:id,name,slug,image_url', 'images']);

        // Filtro por Destacados: ?featured=true
        if ($request->has('featured') && $request->featured == 'true') {
            $query->where('is_featured', true);
        }

        // Filtro por Categoría: Usamos filled() para ignorar strings vacíos
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filtro por Estado: Captura tanto 'true' como 'false' del select de Vue
        if ($request->filled('active')) {
            $isActive = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        // Búsqueda por nombre o SKU
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('sku', 'LIKE', "%{$search}%");
            });
        }

        // Si es una petición pública sin paginación (ej: landing page)
        if ($request->has('nopaginate') && $request->nopaginate == 'true') {
            return response()->json($query->orderBy('id', 'desc')->get());
        }

        // Paginación de 15 elementos para el panel administrativo
        $products = $query->orderBy('id', 'desc')->paginate(15);

        return response()->json($products);
    }

    public function store(StoreProductRequest $request)
    {
        $product = Product::create($request->validated());

        return response()->json([
            'message' => 'Producto registrado exitosamente',
            'product' => $product->load(['category', 'images'])
        ], 201);
    }

    public function show(Product $product)
    {
        return response()->json($product->load(['category', 'images']));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return response()->json([
            'message' => 'Producto actualizado exitosamente',
            'product' => $product->load(['category', 'images'])
        ]);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'Producto eliminado correctamente']);
    }
}