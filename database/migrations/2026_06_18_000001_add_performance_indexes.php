<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // orders: status y created_at se filtran/ordenan en absolutamente todos los listados
        Schema::table('orders', function (Blueprint $table) {
            $table->index('status', 'idx_orders_status');
            $table->index('created_at', 'idx_orders_created_at');
        });

        // customers: phone se usa como llave de lookup en el flujo de pedidos públicos
        Schema::table('customers', function (Blueprint $table) {
            $table->index('phone', 'idx_customers_phone');
        });

        // inventory_adjustment_batches: se lista siempre ordenado por confirmed_at DESC
        Schema::table('inventory_adjustment_batches', function (Blueprint $table) {
            $table->index('confirmed_at', 'idx_adj_batches_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_status');
            $table->dropIndex('idx_orders_created_at');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_phone');
        });

        Schema::table('inventory_adjustment_batches', function (Blueprint $table) {
            $table->dropIndex('idx_adj_batches_confirmed_at');
        });
    }
};
