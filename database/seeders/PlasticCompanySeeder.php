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
        // 1. Configuración de la Empresa
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

        // 2. Categorías
        $categories = [
            ['name' => 'Envases Industriales', 'description' => 'Bidones, tambores y contenedores para logística y químicos.'],
            ['name' => 'Menaje y Hogar', 'description' => 'Recipientes herméticos, cestos y organizadores de alta calidad.'],
            ['name' => 'Embalajes Flexibles', 'description' => 'Film stretch, burbuja y bolsas industriales.'],
            ['name' => 'Seguridad Vial', 'description' => 'Conos, vallas y canalizadores de tránsito.'],
        ];

        foreach ($categories as $cat) {
            $category = Category::updateOrCreate(
                ['name' => $cat['name']],
                ['description' => $cat['description'], 'is_active' => true]
            );

            // 3. Productos por Categoría
            if ($cat['name'] === 'Envases Industriales') {
                $this->createProducts($category, [
                    ['name' => 'Bidón Apilable 20L', 'price' => 1250.00],
                    ['name' => 'Tambor 200L Soplado', 'price' => 8500.00],
                    ['name' => 'Contenedor IBC 1000L', 'price' => 45000.00],
                ]);
            } elseif ($cat['name'] === 'Menaje y Hogar') {
                $this->createProducts($category, [
                    ['name' => 'Set Herméticos x3', 'price' => 3200.00],
                    ['name' => 'Cesto de Residuos 50L', 'price' => 4800.00],
                    ['name' => 'Organizador Apilable Grande', 'price' => 1500.00],
                ]);
            } elseif ($cat['name'] === 'Embalajes Flexibles') {
                $this->createProducts($category, [
                    ['name' => 'Film Stretch 50cm x 5kg', 'price' => 7500.00],
                    ['name' => 'Rollo Burbuja 1m x 50m', 'price' => 12000.00],
                ]);
            }
        }
    }

    private function createProducts($category, $products)
    {
        foreach ($products as $prod) {
            Product::updateOrCreate(
                ['name' => $prod['name']],
                [
                    'category_id' => $category->id,
                    'sku' => strtoupper(Str::random(8)),
                    'description' => 'Producto fabricado en polipropileno virgen de alta densidad, resistente a impactos.',
                    'base_price' => $prod['price'],
                    'sale_price' => $prod['price'] * 1.2,
                    'cost_price' => $prod['price'] * 0.7,
                    'production_lead_time_days' => 5,
                    'is_active' => true,
                    'attributes' => ['material' => 'PP', 'color' => 'Varios', 'reciclable' => true]
                ]
            );
        }
    }
}
