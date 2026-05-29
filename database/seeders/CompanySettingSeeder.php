<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

class CompanySettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Configuración de la Empresa
        try {
            CompanySetting::updateOrCreate(['id' => 1], [
                'company_name'      => 'Plastix Industrial S.A.',
                'email'             => 'ventas@plastix-industrial.com',
                'phone'             => '+54 11 4455-6677',
                'address'           => 'Parque Industrial Norte, Lote 45, Buenos Aires, Argentina',
                'facebook_url'      => 'https://facebook.com/plastixindustrial',
                'instagram_url'     => 'https://instagram.com/plastix.ok',
                'whatsapp'          => '5491144556677',
                'hero_title'        => 'Soluciones en Plástico de Alta Resistencia',
                'hero_subtitle'     => 'Fabricación nacional con estándares internacionales para la industria y el hogar.',
                'about_title'       => 'Más de 20 años innovando en polímeros',
                'about_description' => 'En Plastix Industrial nos dedicamos a la transformación de termoplásticos mediante procesos de inyección y soplado. Contamos con tecnología de punta para garantizar durabilidad y sostenibilidad en cada uno de nuestros productos.',
                'footer_text'       => '© 2026 Plastix Industrial S.A. - Innovación en Plásticos.'
            ]);
            
            $this->command->info("CompanySettingSeeder: Configuración de empresa sembrada correctamente.");
        } catch (\Exception $e) {
            $this->command->warn("CompanySettingSeeder: No se pudo sembrar la configuración de empresa. Detalle: " . $e->getMessage());
        }
    }
}
