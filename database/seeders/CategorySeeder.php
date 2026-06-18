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
                'name'   => 'Envases Industriales',
                'bg'     => '#0d3b6e',
                'stripe' => '#1a5fa8',
            ],
            [
                'name'   => 'Menaje y Hogar',
                'bg'     => '#1b4d3e',
                'stripe' => '#2d7a5f',
            ],
            [
                'name'   => 'Embalajes Flexibles',
                'bg'     => '#4a2040',
                'stripe' => '#7a3568',
            ],
            [
                'name'   => 'Botellas y Tapas',
                'bg'     => '#7a2500',
                'stripe' => '#c44000',
            ],
        ];

        Storage::disk('public')->makeDirectory('categories');

        foreach ($categories as $cat) {
            $slug      = Str::slug($cat['name']);
            $imagePath = $this->generateSvg($cat['name'], $slug, $cat['bg'], $cat['stripe']);

            Category::updateOrCreate(
                ['name' => $cat['name']],
                [
                    'slug'      => $slug,
                    'image_url' => $imagePath,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('CategorySeeder: ' . count($categories) . ' categorías con imagen sembradas.');
    }

    private function generateSvg(string $name, string $slug, string $bg, string $stripe): string
    {
        $label = htmlspecialchars($name, ENT_XML1 | ENT_QUOTES);

        $svg = implode('', [
            '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400" viewBox="0 0 600 400">',

            // Fondo principal
            '<rect width="600" height="400" fill="', $bg, '"/>',

            // Patrón de puntos sutil
            '<defs>',
            '<pattern id="dots" width="30" height="30" patternUnits="userSpaceOnUse">',
            '<circle cx="15" cy="15" r="1.3" fill="white" opacity="0.2"/>',
            '</pattern>',
            '</defs>',
            '<rect width="600" height="400" fill="url(#dots)"/>',

            // Franja inferior con color de acento
            '<rect x="0" y="310" width="600" height="90" fill="', $stripe, '" opacity="0.55"/>',

            // Línea decorativa izquierda
            '<rect x="0" y="310" width="5" height="90" fill="white" opacity="0.4"/>',

            // Nombre de la categoría centrado
            '<text x="300" y="205" text-anchor="middle" dominant-baseline="middle" ',
            'fill="white" font-family="Arial, Helvetica, sans-serif" ',
            'font-size="34" font-weight="700" letter-spacing="0.5">', $label, '</text>',

            // Subtítulo SOLUPLAST
            '<text x="300" y="355" text-anchor="middle" dominant-baseline="middle" ',
            'fill="rgba(255,255,255,0.65)" font-family="Arial, Helvetica, sans-serif" ',
            'font-size="12" letter-spacing="4">SOLUPLAST</text>',

            '</svg>',
        ]);

        $path = 'categories/' . $slug . '.svg';
        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
