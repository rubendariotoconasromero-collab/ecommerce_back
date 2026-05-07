<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        // Traemos las categorías con el conteo de productos asociados
        $query = Category::withCount('products')->orderBy('name', 'asc');

        // Si se envía el parámetro ?active=true, filtramos solo las activas
        if ($request->has('active') && $request->active == 'true') {
            $query->where('is_active', true);
        }

        $categories = $query->get();

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255|unique:categories,name',
            'is_active' => 'boolean'
        ]);

        // Autogenerar el slug a partir del nombre
        $validated['slug'] = Str::slug($validated['name']);

        $category = Category::create($validated);

        return response()->json([
            'message'  => 'Categoría creada exitosamente',
            'category' => $category
        ], 201);
    }

    public function show(Category $category)
    {
        // Cargamos los productos asociados al mostrar una categoría específica
        return response()->json($category->load('products'));
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255', Rule::unique('categories')->ignore($category->id)],
            'is_active' => 'boolean'
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return response()->json([
            'message'  => 'Categoría actualizada exitosamente',
            'category' => $category
        ]);
    }

    public function destroy(Category $category)
    {
        // Protección de integridad referencial: No eliminar si tiene productos
        if ($category->products()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque tiene productos asociados.'
            ], 422);
        }

        $category->delete();
        
        return response()->json(['message' => 'Categoría eliminada correctamente']);
    }
}