<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $blueprint) {
            $blueprint->id();
            
            // Información General
            $blueprint->string('company_name')->nullable();
            $blueprint->string('email')->nullable();
            $blueprint->string('phone')->nullable();
            $blueprint->text('address')->nullable();
            
            // Redes Sociales
            $blueprint->string('facebook_url')->nullable();
            $blueprint->string('instagram_url')->nullable();
            $blueprint->string('whatsapp')->nullable();
            
            // Sección Hero (Landing)
            $blueprint->string('hero_title')->nullable();
            $blueprint->string('hero_subtitle')->nullable();
            $blueprint->string('hero_image_path')->nullable();
            
            // Sección Nosotros (Landing)
            $blueprint->string('about_title')->nullable();
            $blueprint->text('about_description')->nullable();
            $blueprint->string('about_image_path')->nullable();
            
            // Footer
            $blueprint->string('footer_text')->nullable();

            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
