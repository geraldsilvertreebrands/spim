<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pipelines table - one per pipeline-driven attribute
        Schema::create('pipelines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('attribute_id')->unique();
            $table->unsignedBigInteger('entity_type_id');
            $table->string('name')->nullable();
            $table->unsignedInteger('pipeline_version')->default(1);
            $table->timestamp('pipeline_updated_at')->useCurrent();

            // Last run stats (cached from most recent pipeline_run)
            $table->timestamp('last_run_at')->nullable();
            $table->enum('last_run_status', ['running', 'completed', 'failed', 'aborted'])->nullable();
            $table->unsignedInteger('last_run_duration_ms')->nullable();
            $table->unsignedInteger('last_run_processed')->nullable();
            $table->unsignedInteger('last_run_failed')->nullable();
            $table->unsignedBigInteger('last_run_tokens_in')->nullable();
            $table->unsignedBigInteger('last_run_tokens_out')->nullable();

            $table->timestamps();

            $table->foreign('attribute_id')
                ->references('id')
                ->on('attributes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('entity_type_id')
                ->references('id')
                ->on('entity_types')
                ->restrictOnDelete();

            $table->index(['entity_type_id', 'created_at']);
            $table->index('pipeline_updated_at');
        });

        // Pipeline modules - steps in a pipeline
        Schema::create('pipeline_modules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('pipeline_id');
            $table->unsignedSmallInteger('order');
            $table->string('module_class');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->foreign('pipeline_id')
                ->references('id')
                ->on('pipelines')
                ->cascadeOnDelete();

            $table->unique(['pipeline_id', 'order']);
            $table->index('module_class');
        });

        // Pipeline runs - execution history
        Schema::create('pipeline_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('pipeline_id');
            $table->unsignedInteger('pipeline_version');
            $table->enum('triggered_by', ['schedule', 'entity_save', 'manual']);
            $table->string('trigger_reference')->nullable(); // entity_id or user_id
            $table->enum('status', ['running', 'completed', 'failed', 'aborted'])->default('running');
            $table->unsignedInteger('batch_size')->nullable();
            $table->unsignedInteger('entities_processed')->default(0);
            $table->unsignedInteger('entities_failed')->default(0);
            $table->unsignedInteger('entities_skipped')->default(0);
            $table->unsignedBigInteger('tokens_in')->nullable();
            $table->unsignedBigInteger('tokens_out')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('pipeline_id')
                ->references('id')
                ->on('pipelines')
                ->cascadeOnDelete();

            $table->index(['pipeline_id', 'created_at']);
            $table->index(['status', 'started_at']);
            $table->index('triggered_by');
        });

        // Pipeline evals - test cases for pipelines
        Schema::create('pipeline_evals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('pipeline_id');
            $table->ulid('entity_id');
            $table->string('input_hash', 64)->nullable();
            $table->json('desired_output');
            $table->text('notes')->nullable();
            $table->json('actual_output')->nullable();
            $table->text('justification')->nullable();
            $table->decimal('confidence', 5, 4)->nullable(); // 0.0000 to 1.0000
            $table->timestamp('last_ran_at')->nullable();
            $table->timestamps();

            $table->foreign('pipeline_id')
                ->references('id')
                ->on('pipelines')
                ->cascadeOnDelete();

            $table->foreign('entity_id')
                ->references('id')
                ->on('entities')
                ->cascadeOnDelete();

            $table->unique(['pipeline_id', 'entity_id']);
            $table->index('input_hash');
            $table->index('last_ran_at');
        });

        // Add pipeline_id to attributes table
        Schema::table('attributes', function (Blueprint $table) {
            $table->ulid('pipeline_id')->nullable()->after('ui_class');

            $table->foreign('pipeline_id')
                ->references('id')
                ->on('pipelines')
                ->nullOnDelete();

            $table->unique('pipeline_id');
        });

        // Add pipeline_version to eav_versioned table
        Schema::table('eav_versioned', function (Blueprint $table) {
            $table->unsignedInteger('pipeline_version')->nullable()->after('input_hash');

            $table->index('pipeline_version');
        });
    }

    public function down(): void
    {
        Schema::table('eav_versioned', function (Blueprint $table) {
            $table->dropIndex(['pipeline_version']);
            $table->dropColumn('pipeline_version');
        });

        Schema::table('attributes', function (Blueprint $table) {
            $table->dropForeign(['pipeline_id']);
            $table->dropUnique(['pipeline_id']);
            $table->dropColumn('pipeline_id');
        });

        Schema::dropIfExists('pipeline_evals');
        Schema::dropIfExists('pipeline_runs');
        Schema::dropIfExists('pipeline_modules');
        Schema::dropIfExists('pipelines');
    }
};

