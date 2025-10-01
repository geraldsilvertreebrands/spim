<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('entity_type_id');
            $table->string('entity_id'); // natural external ID per entity type
            $table->timestamps();

            $table->unique(['entity_type_id', 'entity_id']);
            $table->index('entity_type_id');

            $table->foreign('entity_type_id')
                ->references('id')
                ->on('entity_types')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};
