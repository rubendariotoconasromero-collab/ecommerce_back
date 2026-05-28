<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanySettingController extends Controller
{
    public function show()
    {
        $settings = CompanySetting::first();

        if (!$settings) {
            return response()->json(['message' => 'Configuración no encontrada'], 404);
        }

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $request->validate([
            'company_name'      => 'nullable|string|max:255',
            'email'             => 'nullable|email|max:255',
            'phone'             => 'nullable|string|max:20',
            'address'           => 'nullable|string',
            'facebook_url'      => 'nullable|url|max:255',
            'instagram_url'     => 'nullable|url|max:255',
            'whatsapp'          => 'nullable|string|max:20',
            'hero_title'        => 'nullable|string|max:255',
            'hero_subtitle'     => 'nullable|string|max:255',
            'hero_image'        => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'hero_image_2'      => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'hero_image_3'      => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'about_title'       => 'nullable|string|max:255',
            'about_description' => 'nullable|string',
            'about_image'       => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'footer_text'       => 'nullable|string|max:255',
            // Logos del sistema (file en lugar de image para admitir SVG)
            'logo_login'           => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'logo_sidebar'         => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'logo_sidebar_compact' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            'logo_landing'         => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
            // Flags para eliminar imágenes del slider
            'remove_hero_image'          => 'nullable|boolean',
            'remove_hero_image_2'        => 'nullable|boolean',
            'remove_hero_image_3'        => 'nullable|boolean',
            // Flags para eliminar logos
            'remove_logo_login'          => 'nullable|boolean',
            'remove_logo_sidebar'        => 'nullable|boolean',
            'remove_logo_sidebar_compact'=> 'nullable|boolean',
            'remove_logo_landing'        => 'nullable|boolean',
        ]);

        $settings = CompanySetting::firstOrNew([]);

        // Campos de texto simples
        $textFields = [
            'company_name', 'email', 'phone', 'address',
            'facebook_url', 'instagram_url', 'whatsapp',
            'hero_title', 'hero_subtitle',
            'about_title', 'about_description', 'footer_text',
        ];

        foreach ($textFields as $field) {
            if ($request->has($field)) {
                $settings->$field = $request->input($field);
            }
        }

        // Procesamiento de imágenes hero/nosotros
        $this->handleImage($request, $settings, 'hero_image',   'hero_image_path',   'remove_hero_image');
        $this->handleImage($request, $settings, 'hero_image_2', 'hero_image_2_path', 'remove_hero_image_2');
        $this->handleImage($request, $settings, 'hero_image_3', 'hero_image_3_path', 'remove_hero_image_3');
        $this->handleImage($request, $settings, 'about_image',  'about_image_path',  null);

        // Logos del sistema
        $this->handleImage($request, $settings, 'logo_login',           'logo_login_path',           'remove_logo_login');
        $this->handleImage($request, $settings, 'logo_sidebar',         'logo_sidebar_path',         'remove_logo_sidebar');
        $this->handleImage($request, $settings, 'logo_sidebar_compact', 'logo_sidebar_compact_path', 'remove_logo_sidebar_compact');
        $this->handleImage($request, $settings, 'logo_landing',         'logo_landing_path',         'remove_logo_landing');

        $settings->save();

        return response()->json([
            'message'  => 'Configuración actualizada correctamente.',
            'settings' => $settings->fresh(),
        ]);
    }

    private function handleImage(Request $request, CompanySetting $settings, string $fileKey, string $pathField, ?string $removeKey): void
    {
        // Eliminar imagen explícitamente solicitada
        if ($removeKey && $request->boolean($removeKey)) {
            if ($settings->$pathField) {
                Storage::disk('public')->delete($settings->$pathField);
            }
            $settings->$pathField = null;
            return;
        }

        // Subir nueva imagen
        if ($request->hasFile($fileKey)) {
            if ($settings->$pathField) {
                Storage::disk('public')->delete($settings->$pathField);
            }
            $settings->$pathField = $request->file($fileKey)->store('company', 'public');
        }
    }
}
