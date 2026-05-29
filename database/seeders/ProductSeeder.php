<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productsByCategory = [
            'Envases Industriales' => [
                ['name' => 'Bidón Apilable 20L', 'price' => 125.00, 'image' => 'https://images.unsplash.com/photo-1591871937573-74dbba515c4c?q=80&w=400&auto=format&fit=crop'],
                ['name' => 'Tambor 200L Soplado', 'price' => 850.00, 'image' => 'https://images.unsplash.com/photo-1621259182978-f09e5e2ca09a?q=80&w=400&auto=format&fit=crop'],
                ['name' => 'Contenedor IBC 1000L', 'price' => 4500.00, 'image' => 'https://images.unsplash.com/photo-1584622781564-1d9876a3e740?q=80&w=400&auto=format&fit=crop'],
            ],
            'Menaje y Hogar' => [
                ['name' => 'Set Herméticos x3', 'price' => 32.00, 'image' => 'https://images.unsplash.com/photo-1591147043328-98e3b207559e?q=80&w=400&auto=format&fit=crop'],
                ['name' => 'Cesto de Residuos 50L', 'price' => 48.00, 'image' => 'https://images.unsplash.com/photo-1591871937573-74dbba515c4c?q=80&w=400&auto=format&fit=crop'],
                ['name' => 'Organizador Apilable Grande', 'price' => 15.00, 'image' => 'https://images.unsplash.com/photo-1584622781564-1d9876a3e740?q=80&w=400&auto=format&fit=crop'],
            ],
            'Embalajes Flexibles' => [
                ['name' => 'Film Stretch 50cm x 5kg', 'price' => 75.00, 'image' => 'https://images.unsplash.com/photo-1591147043328-98e3b207559e?q=80&w=400&auto=format&fit=crop'],
                ['name' => 'Rollo Burbuja 1m x 50m', 'price' => 120.00, 'image' => 'https://images.unsplash.com/photo-1584622781564-1d9876a3e740?q=80&w=400&auto=format&fit=crop'],
            ],
            'Botellas y Tapas' => [
                ['name' => 'Botella PET 500ml Cristal', 'price' => 2.50, 'image' => 'https://images.unsplash.com/photo-1584622781564-1d9876a3e740?q=80&w=400&auto=format&fit=crop'],
                ['name' => 'Botella PET 2L Económica', 'price' => 5.80, 'image' => 'https://images.unsplash.com/photo-1591147043328-98e3b207559e?q=80&w=400&auto=format&fit=crop'],
                ['name' => 'Tapa de Seguridad 28mm', 'price' => 0.50, 'image' => 'https://images.unsplash.com/photo-1584622781564-1d9876a3e740?q=80&w=400&auto=format&fit=crop'],
                ['name' => 'Botella HDPE 1L Blanca', 'price' => 8.20, 'image' => 'https://images.unsplash.com/photo-1591147043328-98e3b207559e?q=80&w=400&auto=format&fit=crop'],
            ]
        ];

        $totalProducts = 0;

        foreach ($productsByCategory as $categoryName => $products) {
            $category = Category::where('name', $categoryName)->first();

            if (!$category) {
                $this->command->warn("ProductSeeder: La categoría '{$categoryName}' no existe, omitiendo sus productos.");
                continue;
            }

            foreach ($products as $prod) {
                $product = Product::updateOrCreate(
                    ['name' => $prod['name']],
                    [
                        'category_id'               => $category->id,
                        'sku'                       => strtoupper(Str::random(8)),
                        'description'               => 'Producto fabricado en polímeros de alta calidad, resistente a impactos y 100% reciclable.',
                        'base_price'                => $prod['price'],
                        'sale_price'                => $prod['price'] * 1.2,
                        'cost_price'                => $prod['price'] * 0.7,
                        'production_lead_time_days' => 5,
                        'is_active'                 => true,
                        'attributes'                => [
                            'material'   => 'PET/PP',
                            'color'      => 'Varios',
                            'reciclable' => true
                        ]
                    ]
                );

                // Crear o actualizar la imagen primaria del producto
                if (isset($prod['image'])) {
                    $product->images()->updateOrCreate(
                        ['image_path' => $prod['image']],
                        ['is_primary' => true]
                    );
                }

                $totalProducts++;
            }
        }

        $this->command->info("ProductSeeder: {$totalProducts} productos sembrados correctamente.");
    }
}
