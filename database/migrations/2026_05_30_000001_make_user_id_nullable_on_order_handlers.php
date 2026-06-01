<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_handlers', function (Blueprint $table) {
            // Pedidos desde la tienda pública no tienen user_id (cliente anónimo)
            $table->foreignUuid('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_handlers', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable(false)->change();
        });
    }
};
