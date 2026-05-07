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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // Relación con Categorías (Restringimos borrado para evitar inconsistencias)
            $table->foreignId('category_id')->constrained('categories')->onDelete('restrict');
            
            $table->string('sku')->unique()->comment('Código único del producto');
            $table->string('name');
            $table->text('description')->nullable();
            
            // Finanzas y Precios (10 dígitos en total, 2 decimales para Bolivianos Bs.)
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->default(0);
            
            // Logística de Producción de Plásticos
            $table->integer('production_lead_time_days')->default(0)->comment('Días estimados de fabricación');
            
            // Atributos dinámicos (Ej: {"color": "Rojo", "material": "PET", "peso_gramos": 150})
            $table->json('attributes')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
