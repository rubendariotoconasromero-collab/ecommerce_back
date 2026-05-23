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
        // 1. ORDER_HANDLERS
        Schema::create('order_handlers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('handler_name', 150);
            $table->string('handler_role', 100);
            $table->text('action_taken');
            $table->text('notes')->nullable();
            $table->timestamp('handled_at')->useCurrent();
        });

        // 2. SHIPMENTS
        Schema::create('shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders');
            $table->string('tracking_number', 100)->nullable();
            $table->string('courier_name', 100)->nullable();
            $table->foreignUuid('handler_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['preparing', 'shipped', 'in_transit', 'delivered', 'failed'])->default('preparing');
            $table->text('failure_reason')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        // 3. PRODUCTION_ORDERS
        Schema::create('production_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_item_id')->constrained('order_items');
            $table->foreignUuid('assigned_worker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['queued', 'in_progress', 'completed', 'cancelled'])->default('queued');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('order_handlers');
    }
};
