<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\CompanySetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlasticCompanySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Configuración de la Empresa (si existe la tabla)
        try {
            CompanySetting::updateOrCreate(['id' => 1], [
                'company_name' => 'Plastix Industrial S.A.',
                'email' => 'ventas@plastix-industrial.com',
                'phone' => '+54 11 4455-6677',
                'address' => 'Parque Industrial Norte, Lote 45, Buenos Aires, Argentina',
                'facebook_url' => 'https://facebook.com/plastixindustrial',
                'instagram_url' => 'https://instagram.com/plastix.ok',
                'whatsapp' => '5491144556677',
                'hero_title' => 'Soluciones en Plástico de Alta Resistencia',
                'hero_subtitle' => 'Fabricación nacional con estándares internacionales para la industria y el hogar.',
                'about_title' => 'Más de 20 años innovando en polímeros',
                'about_description' => 'En Plastix Industrial nos dedicamos a la transformación de termoplásticos mediante procesos de inyección y soplado. Contamos con tecnología de punta para garantizar durabilidad y sostenibilidad en cada uno de nuestros productos.',
                'footer_text' => '© 2026 Plastix Industrial S.A. - Innovación en Plásticos.'
            ]);
        } catch (\Exception $e) {
            // Ignorar si la tabla company_settings no está en la base de datos
        }

        // 2. Categorías
        $categories = [
            ['name' => 'Envases Industriales'],
            ['name' => 'Menaje y Hogar'],
            ['name' => 'Embalajes Flexibles'],
            ['name' => 'Botellas y Tapas'],
        ];

        foreach ($categories as $cat) {
            $category = Category::updateOrCreate(
                ['name' => $cat['name']],
                ['is_active' => true]
            );

            // 3. Productos por Categoría
            if ($cat['name'] === 'Envases Industriales') {
                $this->createProducts($category, [
                    ['name' => 'Bidón Apilable 20L', 'price' => 125.00, 'image' => 'https://images.unsplash.com/photo-1591871937573-74dbba515c4c?q=80&w=400&auto=format&fit=crop'],
                    ['name' => 'Tambor 200L Soplado', 'price' => 850.00, 'image' => 'https://images.unsplash.com/photo-1621259182978-f09e5e2ca09a?q=80&w=400&auto=format&fit=crop'],
                    ['name' => 'Contenedor IBC 1000L', 'price' => 4500.00, 'image' => 'https://images.unsplash.com/photo-1584622781564-1d9876a3e740?q=80&w=400&auto=format&fit=crop'],
                ]);
            } elseif ($cat['name'] === 'Menaje y Hogar') {
                $this->createProducts($category, [
                    ['name' => 'Set Herméticos x3', 'price' => 32.00, 'image' => 'https://images.unsplash.com/photo-1591147043328-98e3b207559e?q=80&w=400&auto=format&fit=crop'],
                    ['name' => 'Cesto de Residuos 50L', 'price' => 48.00, 'image' => 'https://images.unsplash.com/photo-1591871937573-74dbba515c4c?q=80&w=400&auto=format&fit=crop'],
                    ['name' => 'Organizador Apilable Grande', 'price' => 15.00, 'image' => 'https://images.unsplash.com/photo-1584622781564-1d9876a3e740?q=80&w=400&auto=format&fit=crop'],
                ]);
            } elseif ($cat['name'] === 'Embalajes Flexibles') {
                $this->createProducts($category, [
                    ['name' => 'Film Stretch 50cm x 5kg', 'price' => 75.00, 'image' => 'https://images.unsplash.com/photo-1591147043328-98e3b207559e?q=80&w=400&auto=format&fit=crop'],
                    ['name' => 'Rollo Burbuja 1m x 50m', 'price' => 120.00, 'image' => 'https://images.unsplash.com/photo-1584622781564-1d9876a3e740?q=80&w=400&auto=format&fit=crop'],
                ]);
            } elseif ($cat['name'] === 'Botellas y Tapas') {
                $this->createProducts($category, [
                    ['name' => 'Botella PET 500ml Cristal', 'price' => 2.50, 'image' => 'https://images.unsplash.com/photo-1584622781564-1d9876a3e740?q=80&w=400&auto=format&fit=crop'],
                    ['name' => 'Botella PET 2L Económica', 'price' => 5.80, 'image' => 'https://images.unsplash.com/photo-1591147043328-98e3b207559e?q=80&w=400&auto=format&fit=crop'],
                    ['name' => 'Tapa de Seguridad 28mm', 'price' => 0.50, 'image' => 'https://images.unsplash.com/photo-1584622781564-1d9876a3e740?q=80&w=400&auto=format&fit=crop'],
                    ['name' => 'Botella HDPE 1L Blanca', 'price' => 8.20, 'image' => 'https://images.unsplash.com/photo-1591147043328-98e3b207559e?q=80&w=400&auto=format&fit=crop'],
                ]);
            }
        }
    }

    private function createProducts($category, $products)
    {
        foreach ($products as $prod) {
            $product = Product::updateOrCreate(
                ['name' => $prod['name']],
                [
                    'category_id' => $category->id,
                    'sku' => strtoupper(Str::random(8)),
                    'description' => 'Producto fabricado en polímeros de alta calidad, resistente a impactos y 100% reciclable.',
                    'base_price' => $prod['price'],
                    'sale_price' => $prod['price'] * 1.2,
                    'cost_price' => $prod['price'] * 0.7,
                    'production_lead_time_days' => 5,
                    'is_active' => true,
                    'attributes' => ['material' => 'PET/PP', 'color' => 'Varios', 'reciclable' => true]
                ]
            );

            // Crear imagen si se proporciona
            if (isset($prod['image'])) {
                $product->images()->updateOrCreate(
                    ['image_path' => $prod['image']],
                    ['is_primary' => true]
                );
            }
        }
    }
}
