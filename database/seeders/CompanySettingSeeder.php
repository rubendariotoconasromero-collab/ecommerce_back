<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use Illuminate\Database\Seeder;

class CompanySettingSeeder extends Seeder
{
    public function run(): void
    {
        CompanySetting::updateOrCreate(['id' => 1], [
            'company_name'      => 'SOLUPLAST',
            'email'             => 'ventas@soluplast.com',
            'phone'             => '+591 4 456-7890',
            'address'           => 'Parque Industrial Latinoamericano, Zona Norte, Cochabamba, Bolivia',
            'facebook_url'      => 'https://facebook.com/soluplastbolivia',
            'instagram_url'     => 'https://instagram.com/soluplast.bo',
            'whatsapp'          => '59170000000',
            'hero_title'        => 'Soluciones en Plástico de Alta Resistencia',
            'hero_subtitle'     => 'Fabricamos envases, embalajes y soluciones industriales en polímeros con estándares de calidad para Bolivia y la región.',
            'about_title'       => 'Más de 15 años innovando en plásticos industriales',
            'about_description' => 'En SOLUPLAST nos especializamos en la transformación de termoplásticos mediante procesos de inyección y soplado. Ofrecemos soluciones a medida para la industria alimentaria, farmacéutica, agrícola y de consumo masivo, garantizando durabilidad, inocuidad y sostenibilidad en cada producto.',
            'footer_text'       => '© 2026 SOLUPLAST — Soluciones Plásticas Industriales. Cochabamba, Bolivia.',
        ]);

        $this->command->info('CompanySettingSeeder: Configuración de SOLUPLAST aplicada correctamente.');
    }
}
