<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function index(Product $product)
    {
        $images = $product->images()
                          ->orderBy('is_primary', 'desc')
                          ->orderBy('sort_order', 'asc')
                          ->get();

        return response()->json($images);
    }

    public function store(Request $request, Product $product)
    {
        $request->validate([
            'images'   => 'required|array|max:10',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $uploadedImages = DB::transaction(function () use ($request, $product) {
            $result = [];
            $alreadyHasImages = $product->images()->exists();

            foreach ($request->file('images') as $index => $file) {
                $path = $file->store("products/{$product->id}", 'public');

                $isPrimary = !$alreadyHasImages && $index === 0;

                $result[] = ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => $isPrimary,
                    'sort_order' => $product->images()->count() + $index,
                ]);
            }

            return $result;
        });

        return response()->json([
            'message' => 'Imágenes subidas correctamente',
            'images'  => $uploadedImages,
        ], 201);
    }

    public function setPrimary(Product $product, ProductImage $image)
    {
        if ($image->product_id !== $product->id) {
            return response()->json(['message' => 'La imagen no pertenece a este producto.'], 404);
        }

        DB::transaction(function () use ($product, $image) {
            ProductImage::where('product_id', $product->id)->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
        });

        return response()->json(['message' => 'Imagen principal actualizada']);
    }

    public function destroy(Product $product, ProductImage $image)
    {
        if ($image->product_id !== $product->id) {
            return response()->json(['message' => 'La imagen no pertenece a este producto.'], 404);
        }

        if (Storage::disk('public')->exists($image->image_path)) {
            Storage::disk('public')->delete($image->image_path);
        }

        $wasPrimary = $image->is_primary;
        $image->delete();

        if ($wasPrimary) {
            $next = ProductImage::where('product_id', $product->id)->orderBy('sort_order')->first();
            $next?->update(['is_primary' => true]);
        }

        return response()->json(['message' => 'Imagen eliminada']);
    }
}
