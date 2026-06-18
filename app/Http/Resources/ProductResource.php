<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isAdmin = $request->user() !== null;

        return [
            'id'                        => $this->id,
            'category_id'               => $this->category_id,
            'sku'                       => $this->sku,
            'name'                      => $this->name,
            'description'               => $this->description,
            'sale_price'                => (float) $this->sale_price,
            'production_lead_time_days' => (int) $this->production_lead_time_days,
            'attributes'                => $this->attributes,
            'is_active'                 => (bool) $this->is_active,
            'is_featured'               => (bool) $this->is_featured,
            'created_at'                => $this->created_at,
            'updated_at'                => $this->updated_at,

            // Campos sensibles: solo visibles para usuarios autenticados (admin)
            'cost_price'  => $this->when($isAdmin, fn () => (float) $this->cost_price),
            'base_price'  => $this->when($isAdmin, fn () => (float) $this->base_price),

            // URL de imagen primaria (primera marcada como is_primary, o la primera disponible)
            'primary_image_url' => $this->whenLoaded('images', function () {
                $primary = $this->images->firstWhere('is_primary', true)
                    ?? $this->images->first();
                return $primary?->image_url;
            }),

            // Relaciones (solo cuando se cargan explícitamente)
            'category' => $this->whenLoaded('category', fn () => [
                'id'        => $this->category->id,
                'name'      => $this->category->name,
                'slug'      => $this->category->slug,
                'image_url' => $this->category->image_url,
            ]),

            'images' => $this->whenLoaded('images', fn () =>
                $this->images->map(fn ($img) => [
                    'id'         => $img->id,
                    'image_path' => $img->image_path,
                    'image_url'  => $img->image_url,
                    'is_primary' => (bool) $img->is_primary,
                    'sort_order' => $img->sort_order,
                ])
            ),

            'inventory' => $this->whenLoaded('inventory', fn () => $this->inventory ? [
                'qty_available'    => (int) $this->inventory->qty_available,
                'qty_reserved'     => (int) $this->inventory->qty_reserved,
                'qty_in_production'=> (int) $this->inventory->qty_in_production,
                'qty_sellable'     => (int) $this->inventory->qty_sellable,
                'reorder_point'    => (int) $this->inventory->reorder_point,
                'is_low_stock'     => (bool) $this->inventory->is_low_stock,
            ] : null),
        ];
    }
}
