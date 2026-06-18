<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    // Colores por categoría para los SVGs de productos
    private array $categoryColors = [
        'Envases Industriales' => ['bg' => '#1a5276', 'accent' => '#2e86c1'],
        'Menaje y Hogar'       => ['bg' => '#1e8449', 'accent' => '#28b463'],
        'Embalajes Flexibles'  => ['bg' => '#6c3483', 'accent' => '#9b59b6'],
        'Botellas y Tapas'     => ['bg' => '#a04000', 'accent' => '#e67e22'],
    ];

    public function run(): void
    {
        $productsByCategory = [
            'Envases Industriales' => [
                ['name' => 'Bidón Apilable 20L',    'price' => 125.00],
                ['name' => 'Tambor 200L Soplado',   'price' => 850.00],
                ['name' => 'Contenedor IBC 1000L',  'price' => 4500.00],
            ],
            'Menaje y Hogar' => [
                ['name' => 'Set Herméticos x3',          'price' => 32.00],
                ['name' => 'Cesto de Residuos 50L',      'price' => 48.00],
                ['name' => 'Organizador Apilable Grande', 'price' => 15.00],
            ],
            'Embalajes Flexibles' => [
                ['name' => 'Film Stretch 50cm x 5kg', 'price' => 75.00],
                ['name' => 'Rollo Burbuja 1m x 50m', 'price' => 120.00],
            ],
            'Botellas y Tapas' => [
                ['name' => 'Botella PET 500ml Cristal', 'price' => 2.50],
                ['name' => 'Botella PET 2L Económica',  'price' => 5.80],
                ['name' => 'Tapa de Seguridad 28mm',    'price' => 0.50],
                ['name' => 'Botella HDPE 1L Blanca',    'price' => 8.20],
            ],
        ];

        Storage::disk('public')->makeDirectory('products');

        $total = 0;

        foreach ($productsByCategory as $categoryName => $products) {
            $category = Category::where('name', $categoryName)->first();

            if (!$category) {
                $this->command->warn("ProductSeeder: Categoría '{$categoryName}' no encontrada. Ejecuta CategorySeeder primero.");
                continue;
            }

            $colors = $this->categoryColors[$categoryName];

            foreach ($products as $prod) {
                $product = Product::updateOrCreate(
                    ['name' => $prod['name']],
                    [
                        'category_id'               => $category->id,
                        'sku'                       => strtoupper(Str::random(8)),
                        'description'               => 'Producto fabricado en polímeros de alta calidad, resistente a impactos y 100% reciclable.',
                        'base_price'                => $prod['price'],
                        'sale_price'                => round($prod['price'] * 1.2, 2),
                        'cost_price'                => round($prod['price'] * 0.7, 2),
                        'production_lead_time_days' => 5,
                        'is_active'                 => true,
                        'attributes'                => [
                            'material'   => 'PET/PP',
                            'color'      => 'Varios',
                            'reciclable' => true,
                        ],
                    ]
                );

                // Generar SVG y actualizar/crear la imagen primaria
                $slug      = Str::slug($prod['name']);
                $imagePath = $this->generateSvg($prod['name'], $categoryName, $slug, $colors['bg'], $colors['accent']);

                ProductImage::updateOrCreate(
                    ['product_id' => $product->id, 'is_primary' => true],
                    ['image_path' => $imagePath, 'sort_order' => 0]
                );

                $total++;
            }
        }

        $this->command->info("ProductSeeder: {$total} productos con imagen sembrados.");
    }

    private function generateSvg(string $name, string $category, string $slug, string $bg, string $accent): string
    {
        // Dividir el nombre en máximo 2 líneas
        $words = explode(' ', $name);
        $mid   = (int) ceil(count($words) / 2);
        $line1 = implode(' ', array_slice($words, 0, $mid));
        $line2 = implode(' ', array_slice($words, $mid));

        $l1    = htmlspecialchars($line1, ENT_XML1 | ENT_QUOTES);
        $l2    = htmlspecialchars($line2, ENT_XML1 | ENT_QUOTES);
        $cat   = htmlspecialchars(strtoupper($category), ENT_XML1 | ENT_QUOTES);

        $hasLine2 = $line2 !== '';
        $y1       = $hasLine2 ? '185' : '205';

        $line2Svg = $hasLine2
            ? '<text x="300" y="230" text-anchor="middle" dominant-baseline="middle" '
              . 'fill="white" font-family="Arial, Helvetica, sans-serif" font-size="28" font-weight="700">'
              . $l2 . '</text>'
            : '';

        $svg = implode('', [
            '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600">',

            // Fondo
            '<rect width="600" height="600" fill="', $bg, '"/>',

            // Patrón de líneas cruzadas
            '<defs>',
            '<pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">',
            '<path d="M0 0 L40 40 M40 0 L0 40" stroke="white" stroke-width="0.4" opacity="0.1"/>',
            '</pattern>',
            '</defs>',
            '<rect width="600" height="600" fill="url(#grid)"/>',

            // Marco interior sutil
            '<rect x="25" y="25" width="550" height="550" rx="6" fill="none" stroke="white" stroke-width="1" opacity="0.12"/>',

            // Franja de acento superior
            '<rect x="0" y="0" width="600" height="8" fill="', $accent, '"/>',

            // Nombre del producto — línea 1
            '<text x="300" y="', $y1, '" text-anchor="middle" dominant-baseline="middle" ',
            'fill="white" font-family="Arial, Helvetica, sans-serif" font-size="28" font-weight="700">',
            $l1, '</text>',

            // Nombre del producto — línea 2 (si existe)
            $line2Svg,

            // Categoría en la parte inferior
            '<text x="300" y="555" text-anchor="middle" dominant-baseline="middle" ',
            'fill="rgba(255,255,255,0.45)" font-family="Arial, Helvetica, sans-serif" ',
            'font-size="11" letter-spacing="3">', $cat, '</text>',

            '</svg>',
        ]);

        $path = 'products/' . $slug . '.svg';
        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
