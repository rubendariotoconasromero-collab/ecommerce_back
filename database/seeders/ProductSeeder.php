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
                [
                    'name'     => 'Bidón Apilable 20L',
                    'price'    => 125.00,
                    'keywords' => 'plastic barrel container blue industrial storage',
                ],
                [
                    'name'     => 'Tambor 200L Soplado',
                    'price'    => 850.00,
                    'keywords' => 'industrial drum barrel chemical plastic storage',
                ],
                [
                    'name'     => 'Contenedor IBC 1000L',
                    'price'    => 4500.00,
                    'keywords' => 'industrial bulk container tank chemical warehouse',
                ],
            ],
            'Menaje y Hogar' => [
                [
                    'name'     => 'Set Herméticos x3',
                    'price'    => 32.00,
                    'keywords' => 'food storage containers kitchen plastic hermetic',
                ],
                [
                    'name'     => 'Cesto de Residuos 50L',
                    'price'    => 48.00,
                    'keywords' => 'trash bin waste basket recycling plastic home',
                ],
                [
                    'name'     => 'Organizador Apilable Grande',
                    'price'    => 15.00,
                    'keywords' => 'plastic storage box organizer stackable home',
                ],
            ],
            'Embalajes Flexibles' => [
                [
                    'name'     => 'Film Stretch 50cm x 5kg',
                    'price'    => 75.00,
                    'keywords' => 'stretch wrap film plastic pallet packaging industrial',
                ],
                [
                    'name'     => 'Rollo Burbuja 1m x 50m',
                    'price'    => 120.00,
                    'keywords' => 'bubble wrap packaging plastic protection roll',
                ],
            ],
            'Botellas y Tapas' => [
                [
                    'name'     => 'Botella PET 500ml Cristal',
                    'price'    => 2.50,
                    'keywords' => 'clear plastic bottle pet 500ml transparent water',
                ],
                [
                    'name'     => 'Botella PET 2L Económica',
                    'price'    => 5.80,
                    'keywords' => 'plastic bottle 2 liter water beverage pet',
                ],
                [
                    'name'     => 'Tapa de Seguridad 28mm',
                    'price'    => 0.50,
                    'keywords' => 'bottle cap closure plastic security lid',
                ],
                [
                    'name'     => 'Botella HDPE 1L Blanca',
                    'price'    => 8.20,
                    'keywords' => 'white plastic bottle hdpe 1 liter chemical',
                ],
            ],
        ];

        Storage::disk('public')->makeDirectory('products');

        $total = 0;

        foreach ($productsByCategory as $categoryName => $products) {
            $category = Category::where('name', $categoryName)->first();

            if (!$category) {
                $this->command->warn("ProductSeeder: Categoría '{$categoryName}' no encontrada.");
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

                $slug      = Str::slug($prod['name']);
                $imagePath = $this->resolveImage(
                    $prod['keywords'],
                    'products/' . $slug,
                    $prod['name'],
                    $categoryName,
                    $colors['bg'],
                    $colors['accent']
                );

                ProductImage::updateOrCreate(
                    ['product_id' => $product->id, 'is_primary' => true],
                    ['image_path' => $imagePath, 'sort_order' => 0]
                );

                $this->command->line("  ✓ {$prod['name']} → {$imagePath}");
                $total++;
            }
        }

        $this->command->info("ProductSeeder: {$total} productos sembrados.");
    }

    // -------------------------------------------------------------------------

    private function resolveImage(
        string $keywords,
        string $baseStoragePath,
        string $name,
        string $category,
        string $bg,
        string $accent
    ): string {
        $slug    = basename($baseStoragePath);
        $jpgPath = $baseStoragePath . '.jpg';

        if (Storage::disk('public')->exists($jpgPath)) {
            return $jpgPath;
        }

        $content = $this->downloadFromPicsum($slug, 600, 600);

        if ($content) {
            Storage::disk('public')->put($jpgPath, $content);
            return $jpgPath;
        }

        return $this->generateSvg($name, $slug, $bg, $accent);
    }

    private function downloadFromPicsum(string $seed, int $width, int $height): string|false
    {
        $url = "https://picsum.photos/seed/{$seed}/{$width}/{$height}";

        $context = stream_context_create([
            'http' => [
                'timeout'         => 20,
                'follow_location' => 1,
                'max_redirects'   => 5,
                'user_agent'      => 'Mozilla/5.0 (compatible; SOLUPLAST-Seeder/1.0)',
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);

        if ($content === false || strlen($content) < 10_000) {
            return false;
        }

        return $content;
    }

    private function generateSvg(string $name, string $slug, string $bg, string $accent): string
    {
        $words  = explode(' ', $name);
        $mid    = (int) ceil(count($words) / 2);
        $line1  = htmlspecialchars(implode(' ', array_slice($words, 0, $mid)), ENT_XML1 | ENT_QUOTES);
        $line2  = htmlspecialchars(implode(' ', array_slice($words, $mid)), ENT_XML1 | ENT_QUOTES);

        $y1       = $line2 ? '190' : '210';
        $line2Svg = $line2
            ? '<text x="300" y="240" text-anchor="middle" dominant-baseline="middle" fill="white" font-family="Arial" font-size="30" font-weight="700">' . $line2 . '</text>'
            : '';

        $svg = implode('', [
            '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600">',
            '<rect width="600" height="600" fill="', $bg, '"/>',
            '<defs><pattern id="g" width="40" height="40" patternUnits="userSpaceOnUse">',
            '<path d="M0 0 L40 40 M40 0 L0 40" stroke="white" stroke-width="0.4" opacity="0.1"/></pattern></defs>',
            '<rect width="600" height="600" fill="url(#g)"/>',
            '<rect x="0" y="0" width="600" height="6" fill="', $accent, '"/>',
            '<text x="300" y="', $y1, '" text-anchor="middle" dominant-baseline="middle" fill="white" font-family="Arial" font-size="30" font-weight="700">', $line1, '</text>',
            $line2Svg,
            '<text x="300" y="560" text-anchor="middle" dominant-baseline="middle" fill="rgba(255,255,255,0.4)" font-family="Arial" font-size="11" letter-spacing="4">SOLUPLAST</text>',
            '</svg>',
        ]);

        $path = 'products/' . $slug . '.svg';
        Storage::disk('public')->put($path, $svg);
        return $path;
    }
}
