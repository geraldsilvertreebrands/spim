<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // VERSIONED attributes table
        Schema::create('eav_versioned', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('entity_id');
            $table->unsignedBigInteger('attribute_id');
            $table->longText('value_current')->nullable();
            $table->longText('value_approved')->nullable();
            $table->longText('value_live')->nullable();
            $table->longText('value_override')->nullable();
            $table->string('input_hash', 64)->nullable();
            $table->string('justification')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'attribute_id']);
            $table->index(['attribute_id']);

            $table->foreign('entity_id')->references('id')->on('entities')->cascadeOnDelete();
            $table->foreign('attribute_id')->references('id')->on('attributes')->cascadeOnDelete();
        });

        // INPUT attributes table
        Schema::create('eav_input', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('entity_id');
            $table->unsignedBigInteger('attribute_id');
            $table->longText('value')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'attribute_id']);
            $table->index(['attribute_id']);

            $table->foreign('entity_id')->references('id')->on('entities')->cascadeOnDelete();
            $table->foreign('attribute_id')->references('id')->on('attributes')->cascadeOnDelete();
        });

        // TIMESERIES attributes table
        Schema::create('eav_timeseries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('entity_id');
            $table->unsignedBigInteger('attribute_id');
            $table->timestamp('observed_at');
            $table->longText('value')->nullable();
            $table->string('source')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['entity_id', 'attribute_id', 'observed_at']);

            $table->foreign('entity_id')->references('id')->on('entities')->cascadeOnDelete();
            $table->foreign('attribute_id')->references('id')->on('attributes')->cascadeOnDelete();
        });

        // RELATIONS for belongs_to / belongs_to_multi
        Schema::create('entity_attr_links', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('entity_id');
            $table->unsignedBigInteger('attribute_id');
            $table->ulid('target_entity_id');
            $table->timestamps();

            $table->unique(['entity_id', 'attribute_id', 'target_entity_id']);
            $table->index(['attribute_id']);

            $table->foreign('entity_id')->references('id')->on('entities')->cascadeOnDelete();
            $table->foreign('target_entity_id')->references('id')->on('entities')->cascadeOnDelete();
            $table->foreign('attribute_id')->references('id')->on('attributes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_attr_links');
        Schema::dropIfExists('eav_timeseries');
        Schema::dropIfExists('eav_input');
        Schema::dropIfExists('eav_versioned');
    }
};
