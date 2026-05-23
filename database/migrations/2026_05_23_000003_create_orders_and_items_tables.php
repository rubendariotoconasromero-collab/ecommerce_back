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
        // 1. ORDERS
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('total_amount', 12, 2);
            $table->enum('status', ['pending', 'confirmed', 'in_production', 'ready', 'shipped', 'delivered', 'cancelled', 'returned'])->default('pending');
            $table->date('expected_delivery_date')->nullable();
            $table->text('shipping_address');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. ORDER_ITEMS
        Schema::create('order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products');
            $table->string('product_name', 200);
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('line_profit', 12, 2);
            $table->text('customization_notes')->nullable();
            $table->string('reference_image_path', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
