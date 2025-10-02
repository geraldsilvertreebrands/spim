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
        Schema::table('attributes', function (Blueprint $table) {
            $table->foreignId('attribute_section_id')->nullable()->after('entity_type_id')->constrained('attribute_sections')->nullOnDelete();
            $table->integer('sort_order')->default(0)->after('name');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropForeign(['attribute_section_id']);
            $table->dropColumn(['attribute_section_id', 'sort_order']);
        });
    }
};
