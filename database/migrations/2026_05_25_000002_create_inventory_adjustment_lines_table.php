<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustment_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')
                  ->constrained('inventory_adjustment_batches')
                  ->cascadeOnDelete();
            $table->foreignUuid('product_id')
                  ->constrained('products')
                  ->restrictOnDelete();
            // Snapshots al momento del ajuste
            $table->string('product_name', 200);
            $table->string('product_sku', 100);
            // Control de stock
            $table->integer('previous_qty');
            $table->integer('qty_delta');   // positivo = entrada, negativo = salida
            $table->integer('new_qty');
            // Control de precios (nullable — solo cuando se actualiza)
            $table->decimal('previous_cost_price', 12, 2)->nullable();
            $table->decimal('new_cost_price', 12, 2)->nullable();
            $table->decimal('previous_sale_price', 12, 2)->nullable();
            $table->decimal('new_sale_price', 12, 2)->nullable();
            $table->text('line_notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_lines');
    }
};
