<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class CompanySettingSeeder extends Seeder
{
    // Seeds de Picsum que producen fotos industriales/manufactureras de buen aspecto
    private array $heroSeeds = [
        'hero_image_path'   => 'factory-industrial-plastics-1',
        'hero_image_2_path' => 'manufacturing-polymer-containers-2',
        'hero_image_3_path' => 'industrial-storage-warehouse-3',
    ];

    public function run(): void
    {
        Storage::disk('public')->makeDirectory('settings');

        $heroPaths = [];

        foreach ($this->heroSeeds as $field => $seed) {
            $filename = str_replace('_path', '', $field) . '.jpg';
            $storagePath = 'settings/' . $filename;

            if (Storage::disk('public')->exists($storagePath)) {
                $this->command->line("  (cached) {$field} → {$storagePath}");
            } else {
                $content = $this->downloadFromPicsum($seed, 1280, 720);
                if ($content) {
                    Storage::disk('public')->put($storagePath, $content);
                    $this->command->line("  ✓ {$field} → {$storagePath}");
                } else {
                    $storagePath = $this->generateHeroSvg($field, $storagePath);
                    $this->command->warn("  ⚠ {$field} → SVG fallback");
                }
            }

            $heroPaths[$field] = $storagePath;
        }

        CompanySetting::updateOrCreate(['id' => 1], [
            'company_name'      => 'SOLUPLAST',
            'email'             => 'ventas@soluplast.com',
            'phone'             => '+591 4 456-7890',
            'address'           => 'Parque Industrial Latinoamericano, Zona Norte, Santa Cruz, Bolivia',
            'facebook_url'      => 'https://facebook.com/soluplastbolivia',
            'instagram_url'     => 'https://instagram.com/soluplast.bo',
            'whatsapp'          => '59170000000',
            'hero_title'        => 'Soluciones en Plástico de Alta Resistencia',
            'hero_subtitle'     => 'Fabricamos envases, embalajes y soluciones industriales en polímeros con estándares de calidad para Bolivia y la región.',
            'about_title'       => 'Más de 15 años innovando en plásticos industriales',
            'about_description' => 'En SOLUPLAST nos especializamos en la transformación de termoplásticos mediante procesos de inyección y soplado. Ofrecemos soluciones a medida para la industria alimentaria, farmacéutica, agrícola y de consumo masivo, garantizando durabilidad, inocuidad y sostenibilidad en cada producto.',
            'footer_text'       => '© 2026 SOLUPLAST — Soluciones Plásticas Industriales. Santa Cruz, Bolivia.',
            'hero_image_path'   => $heroPaths['hero_image_path'],
            'hero_image_2_path' => $heroPaths['hero_image_2_path'],
            'hero_image_3_path' => $heroPaths['hero_image_3_path'],
        ]);

        $this->command->info('CompanySettingSeeder: Configuración de SOLUPLAST aplicada correctamente.');
    }

    // -------------------------------------------------------------------------

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

    private function generateHeroSvg(string $field, string $storagePath): string
    {
        $labels = [
            'hero_image_path'   => ['Envases Industriales', '#002954', '#00719e'],
            'hero_image_2_path' => ['Embalajes Flexibles',  '#1b3a2d', '#2d7a5f'],
            'hero_image_3_path' => ['Botellas y Tapas',     '#4a1a00', '#c44000'],
        ];

        [$label, $bg, $accent] = $labels[$field] ?? ['SOLUPLAST', '#002954', '#00719e'];
        $label = htmlspecialchars($label, ENT_XML1 | ENT_QUOTES);

        $svg = implode('', [
            '<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720" viewBox="0 0 1280 720">',
            '<rect width="1280" height="720" fill="', $bg, '"/>',
            '<defs><pattern id="g" width="60" height="60" patternUnits="userSpaceOnUse">',
            '<path d="M0 0 L60 60 M60 0 L0 60" stroke="white" stroke-width="0.5" opacity="0.07"/></pattern></defs>',
            '<rect width="1280" height="720" fill="url(#g)"/>',
            '<rect x="0" y="620" width="1280" height="100" fill="', $accent, '" opacity="0.4"/>',
            '<text x="640" y="340" text-anchor="middle" dominant-baseline="middle" ',
            'fill="white" font-family="Arial,Helvetica,sans-serif" font-size="64" font-weight="700" opacity="0.9">',
            $label, '</text>',
            '<text x="640" y="420" text-anchor="middle" dominant-baseline="middle" ',
            'fill="rgba(255,255,255,0.5)" font-family="Arial" font-size="20" letter-spacing="6">SOLUPLAST</text>',
            '</svg>',
        ]);

        Storage::disk('public')->put($storagePath, $svg);
        return $storagePath;
    }
}
