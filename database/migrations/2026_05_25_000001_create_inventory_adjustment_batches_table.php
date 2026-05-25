<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustment_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('batch_number', 30)->unique()->notNull();
            $table->enum('adjustment_type', ['entry', 'exit', 'initial_stock', 'correction']);
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_batches');
    }
};
