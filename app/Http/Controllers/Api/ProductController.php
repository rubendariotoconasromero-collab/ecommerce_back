<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Iniciamos la consulta cargando la relación de categoría
        $query = Product::with('category:id,name,slug');

        // Filtro por Categoría: Usamos filled() para ignorar strings vacíos
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filtro por Estado: Captura tanto 'true' como 'false' del select de Vue
        if ($request->filled('active')) {
            // filter_var convierte los strings 'true'/'false' a booleanos reales de PHP
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

        // Paginación de 15 elementos
        $products = $query->orderBy('id', 'desc')->paginate(15);

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id'               => 'required|exists:categories,id',
            'sku'                       => 'required|string|max:50|unique:products,sku',
            'name'                      => 'required|string|max:255',
            'description'               => 'nullable|string',
            'base_price'                => 'required|numeric|min:0',
            'cost_price'                => 'required|numeric|min:0',
            'sale_price'                => 'required|numeric|min:0',
            'production_lead_time_days' => 'required|integer|min:0',
            'attributes'                => 'nullable|array', // Valida que sea un objeto/array
            'is_active'                 => 'boolean'
        ]);

        $product = Product::create($validated);

        return response()->json([
            'message' => 'Producto registrado exitosamente',
            'product' => $product->load('category')
        ], 201);
    }

    public function show(Product $product)
    {
        // Retorna el producto con su categoría (y futuras imágenes)
        return response()->json($product->load('category'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id'               => 'required|exists:categories,id',
            'sku'                       => ['required', 'string', 'max:50', Rule::unique('products')->ignore($product->id)],
            'name'                      => 'required|string|max:255',
            'description'               => 'nullable|string',
            'base_price'                => 'required|numeric|min:0',
            'cost_price'                => 'required|numeric|min:0',
            'sale_price'                => 'required|numeric|min:0',
            'production_lead_time_days' => 'required|integer|min:0',
            'attributes'                => 'nullable|array',
            'is_active'                 => 'boolean'
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Producto actualizado exitosamente',
            'product' => $product->load('category')
        ]);
    }

    public function destroy(Product $product)
    {
        // Más adelante, aquí deberás validar si el producto está en algún OrderItem
        // antes de eliminarlo. Por ahora, permitimos la eliminación.
        
        $product->delete();
        
        return response()->json(['message' => 'Producto eliminado correctamente']);
    }
}