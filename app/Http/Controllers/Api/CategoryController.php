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
        $query = Category::withCount('products')->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('slug', 'LIKE', "%{$search}%");
        }

        if ($request->boolean('active')) {
            $query->where('is_active', true);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:categories,name',
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|uuid|exists:categories,id',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active'   => 'nullable|boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        if ($request->hasFile('image')) {
            $validated['image_url'] = $request->file('image')->store('categories', 'public');
        }

        $category = Category::create($validated);

        return response()->json([
            'message'  => 'Categoría creada exitosamente',
            'category' => $category,
        ], 201);
    }

    public function show(Category $category)
    {
        $category->loadCount('products');

        return response()->json($category);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100', Rule::unique('categories')->ignore($category->id)],
            'description' => 'nullable|string',
            'parent_id'   => 'nullable|uuid|exists:categories,id',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active'   => 'nullable|boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        if ($request->hasFile('image')) {
            $rawPath = $category->getRawOriginal('image_url');
            if ($rawPath && !str_starts_with($rawPath, 'http')) {
                Storage::disk('public')->delete($rawPath);
            }
            $validated['image_url'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($validated);

        return response()->json([
            'message'  => 'Categoría actualizada exitosamente',
            'category' => $category,
        ]);
    }

    public function destroy(Category $category)
    {
        $category->update(['is_active' => false]);

        return response()->json(['message' => 'Categoría desactivada correctamente']);
    }

    public function restore(Category $category)
    {
        $category->update(['is_active' => true]);

        return response()->json(['message' => 'Categoría reactivada correctamente']);
    }
}
