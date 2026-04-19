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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Agregamos tus campos B2B/B2C
            $table->enum('customer_type', ['individual', 'company'])->default('individual');
            $table->string('name');
            $table->string('business_name')->nullable();
            $table->string('tax_id', 50)->nullable(); // NIT / RUT
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable(); // Nativo de Laravel, muy útil
            $table->string('password'); // Laravel espera que se llame 'password'
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->rememberToken(); // Nativo de Laravel
            $table->timestamps(); // Crea created_at y updated_at
            $table->softDeletes(); // Crea el deleted_at
        });

        // Tablas por defecto de Laravel para resetear passwords y sesiones
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
