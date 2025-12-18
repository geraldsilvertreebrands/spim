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
        Schema::create('price_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('product_id', 26)->nullable(); // ULID - references entities
            $table->string('competitor_name')->nullable();
            $table->enum('alert_type', ['price_below', 'competitor_beats', 'price_change', 'out_of_stock']);
            $table->decimal('threshold', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['user_id', 'is_active']);
            $table->index(['product_id', 'is_active']);
            $table->index(['alert_type', 'is_active']);

            // Foreign key for product (optional - nullable)
            $table->foreign('product_id')
                ->references('id')
                ->on('entities')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_alerts');
    }
};
