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
        Schema::create('price_scrapes', function (Blueprint $table) {
            $table->id();
            $table->char('product_id', 26); // ULID - references entities
            $table->string('competitor_name');
            $table->text('competitor_url')->nullable();
            $table->string('competitor_sku')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('ZAR');
            $table->boolean('in_stock')->default(true);
            $table->timestamp('scraped_at');
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['product_id', 'scraped_at']);
            $table->index(['competitor_name', 'scraped_at']);

            // Foreign key constraint
            $table->foreign('product_id')
                ->references('id')
                ->on('entities')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_scrapes');
    }
};
