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
        // 1. PAYMENTS
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders');
            $table->string('payment_method', 80);
            $table->string('transaction_id', 200)->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // 2. ORDER_RETURNS
        Schema::create('order_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders');
            $table->foreignUuid('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->enum('return_type', ['full', 'partial'])->default('full');
            $table->enum('status', ['requested', 'approved', 'rejected', 'resolved'])->default('requested');
            $table->text('reason')->nullable();
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->timestamp('request_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_returns');
        Schema::dropIfExists('payments');
    }
};
