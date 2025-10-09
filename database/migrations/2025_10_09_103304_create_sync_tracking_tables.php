<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracks each sync execution
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_type_id')->nullable();
            $table->enum('sync_type', ['options', 'products', 'full']);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->enum('status', ['running', 'completed', 'failed', 'partial'])->default('running');
            $table->integer('total_items')->default(0);
            $table->integer('successful_items')->default(0);
            $table->integer('failed_items')->default(0);
            $table->integer('skipped_items')->default(0);
            $table->text('error_summary')->nullable();
            $table->string('triggered_by', 50); // 'user', 'schedule', 'cli'
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('entity_type_id')->references('id')->on('entity_types')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['entity_type_id', 'created_at']);
            $table->index('status');
        });

        // Tracks individual item results within a sync run
        Schema::create('sync_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sync_run_id');
            $table->ulid('entity_id')->nullable(); // if syncing a product
            $table->unsignedBigInteger('attribute_id')->nullable(); // if syncing an option/attribute
            $table->string('item_identifier')->nullable(); // SKU or attribute name for display
            $table->enum('operation', ['create', 'update', 'skip', 'validate'])->nullable();
            $table->enum('status', ['success', 'error', 'warning']);
            $table->text('error_message')->nullable();
            $table->json('details')->nullable(); // before/after values, API response, etc
            $table->timestamp('created_at');

            $table->foreign('sync_run_id')->references('id')->on('sync_runs')->cascadeOnDelete();
            $table->foreign('entity_id')->references('id')->on('entities')->nullOnDelete();
            $table->foreign('attribute_id')->references('id')->on('attributes')->nullOnDelete();

            $table->index(['sync_run_id', 'status']);
            $table->index('entity_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_results');
        Schema::dropIfExists('sync_runs');
    }
};
