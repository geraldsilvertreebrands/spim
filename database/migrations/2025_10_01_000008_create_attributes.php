<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('entity_type_id');
            $table->string('name');
            $table->enum('data_type', ['integer','text','html','json','select','multiselect','belongs_to','belongs_to_multi']);
            $table->enum('attribute_type', ['versioned','input','timeseries']);
            $table->enum('review_required', ['always', 'low_confidence', 'no'])->default('no');
            $table->json('allowed_values')->nullable();
            $table->unsignedBigInteger('linked_entity_type_id')->nullable();
            $table->boolean('is_synced')->default(false);
            $table->string('ui_class')->nullable();
            $table->timestamps();

            $table->unique(['entity_type_id', 'name']);
            $table->index('linked_entity_type_id');

            $table->foreign('entity_type_id')
                ->references('id')
                ->on('entity_types')
                ->cascadeOnDelete();

            $table->foreign('linked_entity_type_id')
                ->references('id')
                ->on('entity_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
