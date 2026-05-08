<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

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
            'description' => 'nullable|string',
            'image'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active' => 'boolean'
        ]);

        // Autogenerar el slug a partir del nombre
        $validated['slug'] = Str::slug($validated['name']);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('categories', 'public');
            $validated['image_url'] = asset('storage/' . $path);
        }

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
            'description' => 'nullable|string',
            'image'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active' => 'boolean'
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        if ($request->hasFile('image')) {
            // Eliminar imagen anterior si existe y no es una URL externa
            if ($category->image_url && str_contains($category->image_url, asset('storage/'))) {
                $oldPath = str_replace(asset('storage/'), '', $category->image_url);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('image')->store('categories', 'public');
            $validated['image_url'] = asset('storage/' . $path);
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

        // Eliminar imagen física
        if ($category->image_url && str_contains($category->image_url, asset('storage/'))) {
            $path = str_replace(asset('storage/'), '', $category->image_url);
            Storage::disk('public')->delete($path);
        }

        $category->delete();
        
        return response()->json(['message' => 'Categoría eliminada correctamente']);
    }
}
