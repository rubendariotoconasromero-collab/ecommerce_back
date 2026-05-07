<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanySettingController extends Controller
{
    /**
     * Display the company settings.
     */
    public function show()
    {
        $settings = CompanySetting::first();
        
        if (!$settings) {
            return response()->json(['message' => 'Configuración no encontrada'], 404);
        }

        return response()->json($settings);
    }

    /**
     * Update the company settings.
     */
    public function update(Request $request)
    {
        $settings = CompanySetting::first();

        if (!$settings) {
            $settings = new CompanySetting();
        }

        $validatedData = $request->validate([
            'company_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'facebook_url' => 'nullable|url|max:255',
            'instagram_url' => 'nullable|url|max:255',
            'whatsapp' => 'nullable|string|max:20',
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string|max:255',
            'hero_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'about_title' => 'nullable|string|max:255',
            'about_description' => 'nullable|string',
            'about_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'footer_text' => 'nullable|string|max:255',
        ]);

        $settings->fill($request->except(['hero_image', 'about_image', 'hero_image_path', 'about_image_path']));

        // Manejar subida de Hero Image
        if ($request->hasFile('hero_image')) {
            if ($settings->hero_image_path) {
                Storage::disk('public')->delete($settings->hero_image_path);
            }
            $path = $request->file('hero_image')->store('company', 'public');
            $settings->hero_image_path = $path;
        }

        // Manejar subida de About Image
        if ($request->hasFile('about_image')) {
            if ($settings->about_image_path) {
                Storage::disk('public')->delete($settings->about_image_path);
            }
            $path = $request->file('about_image')->store('company', 'public');
            $settings->about_image_path = $path;
        }

        $settings->save();

        return response()->json([
            'message' => 'Configuración actualizada correctamente',
            'settings' => $settings
        ]);
    }
}
