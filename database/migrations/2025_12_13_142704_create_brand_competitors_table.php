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
        Schema::create('brand_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->onDelete('cascade');
            $table->foreignId('competitor_brand_id')->constrained('brands')->onDelete('cascade');
            $table->unsignedTinyInteger('position'); // 1, 2, or 3
            $table->timestamps();

            $table->unique(['brand_id', 'position']);
            $table->unique(['brand_id', 'competitor_brand_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('brand_competitors');
    }
};
