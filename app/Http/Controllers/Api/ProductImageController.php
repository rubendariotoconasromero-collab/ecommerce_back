<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    // Obtener todas las imágenes de un producto
    public function index($productId)
    {
        $images = ProductImage::where('product_id', $productId)
                              ->orderBy('is_primary', 'desc') // La principal primero
                              ->orderBy('created_at', 'asc')
                              ->get();
                              
        return response()->json($images);
    }

    // Subir una o varias imágenes
    public function store(Request $request, $productId)
    {
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120', // Máximo 5MB por imagen
        ]);

        $product = Product::findOrFail($productId);
        $uploadedImages = [];

        foreach ($request->file('images') as $file) {
            // Subir archivo al disco público
            $path = $file->store("products/{$product->id}", 'public');

            // Si es la primera imagen, la hacemos principal por defecto
            $isFirst = $product->images()->count() === 0 && count($uploadedImages) === 0;

            $image = ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $path,
                'is_primary' => $isFirst,
            ]);

            $uploadedImages[] = $image;
        }

        return response()->json([
            'message' => 'Imágenes subidas correctamente',
            'images' => $uploadedImages
        ], 201);
    }

    // Marcar una imagen como principal
    public function setPrimary($productId, $imageId)
    {
        // 1. Quitar el primary a todas las imágenes de este producto
        ProductImage::where('product_id', $productId)->update(['is_primary' => false]);

        // 2. Asignar el primary a la seleccionada
        $image = ProductImage::where('product_id', $productId)->findOrFail($imageId);
        $image->update(['is_primary' => true]);

        return response()->json(['message' => 'Imagen principal actualizada']);
    }

    // Eliminar una imagen
    public function destroy($productId, $imageId)
    {
        $image = ProductImage::where('product_id', $productId)->findOrFail($imageId);

        // Eliminar archivo físico del servidor
        if (Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }

        $wasPrimary = $image->is_primary;
        $image->delete();

        // Si borramos la principal, asignamos la siguiente disponible como principal
        if ($wasPrimary) {
            $nextImage = ProductImage::where('product_id', $productId)->first();
            if ($nextImage) {
                $nextImage->update(['is_primary' => true]);
            }
        }

        return response()->json(['message' => 'Imagen eliminada']);
    }
}