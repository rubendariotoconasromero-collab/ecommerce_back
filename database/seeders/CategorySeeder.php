<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name'     => 'Envases Industriales',
                'keywords' => 'industrial plastic drums barrels warehouse storage',
                'bg'       => '#0d3b6e',
                'stripe'   => '#1a5fa8',
            ],
            [
                'name'     => 'Menaje y Hogar',
                'keywords' => 'kitchen containers food storage plastic home',
                'bg'       => '#1b4d3e',
                'stripe'   => '#2d7a5f',
            ],
            [
                'name'     => 'Embalajes Flexibles',
                'keywords' => 'stretch wrap packaging plastic film industrial',
                'bg'       => '#4a2040',
                'stripe'   => '#7a3568',
            ],
            [
                'name'     => 'Botellas y Tapas',
                'keywords' => 'plastic bottles water beverage container',
                'bg'       => '#7a2500',
                'stripe'   => '#c44000',
            ],
        ];

        Storage::disk('public')->makeDirectory('categories');

        foreach ($categories as $cat) {
            $slug      = Str::slug($cat['name']);
            $imagePath = $this->resolveImage(
                $cat['keywords'],
                'categories/' . $slug,
                $cat['name'],
                $cat['bg'],
                $cat['stripe']
            );

            Category::updateOrCreate(
                ['name' => $cat['name']],
                [
                    'slug'      => $slug,
                    'image_url' => $imagePath,
                    'is_active' => true,
                ]
            );

            $this->command->line("  ✓ {$cat['name']} → {$imagePath}");
        }

        $this->command->info('CategorySeeder: ' . count($categories) . ' categorías sembradas.');
    }

    // -------------------------------------------------------------------------

    /**
     * Intenta descargar de Unsplash. Si falla, genera un SVG de respaldo.
     * La descarga se omite si el archivo ya existe en storage.
     */
    private function resolveImage(
        string $keywords,
        string $baseStoragePath,
        string $name,
        string $bg,
        string $stripe
    ): string {
        $slug    = basename($baseStoragePath);
        $jpgPath = $baseStoragePath . '.jpg';

        if (Storage::disk('public')->exists($jpgPath)) {
            return $jpgPath;
        }

        $content = $this->downloadFromPicsum($slug, 800, 600);

        if ($content) {
            Storage::disk('public')->put($jpgPath, $content);
            return $jpgPath;
        }

        // Fallback SVG si no hay conexión
        return $this->generateSvg($name, $slug, $bg, $stripe);
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

    private function generateSvg(string $name, string $slug, string $bg, string $stripe): string
    {
        $label = htmlspecialchars($name, ENT_XML1 | ENT_QUOTES);

        $svg = implode('', [
            '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">',
            '<defs><pattern id="dots" width="30" height="30" patternUnits="userSpaceOnUse">',
            '<circle cx="15" cy="15" r="1.3" fill="white" opacity="0.2"/></pattern></defs>',
            '<rect width="800" height="600" fill="', $bg, '"/>',
            '<rect width="800" height="600" fill="url(#dots)"/>',
            '<rect x="0" y="460" width="800" height="140" fill="', $stripe, '" opacity="0.55"/>',
            '<rect x="0" y="460" width="5" height="140" fill="white" opacity="0.4"/>',
            '<text x="400" y="300" text-anchor="middle" dominant-baseline="middle" ',
            'fill="white" font-family="Arial, Helvetica, sans-serif" font-size="42" font-weight="700">',
            $label, '</text>',
            '<text x="400" y="530" text-anchor="middle" dominant-baseline="middle" ',
            'fill="rgba(255,255,255,0.65)" font-family="Arial" font-size="14" letter-spacing="4">SOLUPLAST</text>',
            '</svg>',
        ]);

        $path = 'categories/' . $slug . '.svg';
        Storage::disk('public')->put($path, $svg);
        return $path;
    }
}
