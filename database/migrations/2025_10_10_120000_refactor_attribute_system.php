<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add new fields to attributes table
        Schema::table('attributes', function (Blueprint $table) {
            $table->enum('editable', ['yes', 'no', 'overridable'])->default('yes')->after('data_type');
            $table->enum('is_pipeline', ['yes', 'no'])->default('no')->after('editable');
            $table->enum('is_sync', ['no', 'from_external', 'to_external'])->default('no')->after('is_pipeline');
            $table->enum('needs_approval', ['yes', 'no', 'only_low_confidence'])->default('no')->after('is_sync');
        });

        // Step 2: Migrate existing data from old schema to new schema
        // This maps the old attribute_type + is_synced + review_required to the new fields

        // For all attributes, set default values based on old fields
        DB::statement("
            UPDATE attributes
            SET
                editable = CASE
                    WHEN attribute_type = 'input' AND is_synced = 1 THEN 'no'
                    ELSE 'yes'
                END,
                is_pipeline = 'no',
                is_sync = CASE
                    WHEN attribute_type = 'input' AND is_synced = 1 THEN 'from_external'
                    WHEN attribute_type = 'versioned' AND is_synced = 1 THEN 'to_external'
                    ELSE 'no'
                END,
                needs_approval = CASE
                    WHEN review_required = 'always' THEN 'yes'
                    WHEN review_required = 'low_confidence' THEN 'only_low_confidence'
                    ELSE 'no'
                END
        ");

        // Step 3: Drop old fields from attributes table
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn(['attribute_type', 'review_required', 'is_synced']);
        });

        // Step 4: Drop eav_input and eav_timeseries tables (no live data)
        Schema::dropIfExists('eav_input');
        Schema::dropIfExists('eav_timeseries');

        // Step 5: Drop and recreate the EAV views to only use eav_versioned
        DB::statement('DROP VIEW IF EXISTS entity_attr_json');
        DB::statement('DROP VIEW IF EXISTS entity_attribute_resolved');
        DB::statement('DROP VIEW IF EXISTS eav_timeseries_latest');

        // Recreate entity_attribute_resolved view - now only from eav_versioned
        DB::statement("
            CREATE VIEW entity_attribute_resolved AS
            SELECT
                v.entity_id,
                v.attribute_id,
                COALESCE(NULLIF(v.value_override, ''), v.value_current) AS resolved_with_override,
                v.value_current AS resolved_current_only,
                v.value_approved AS resolved_approved,
                v.value_live AS resolved_live,
                v.updated_at
            FROM eav_versioned v
        ");

        // Recreate entity_attr_json view
        DB::statement("
            CREATE VIEW entity_attr_json AS
            SELECT
                ear.entity_id,
                JSON_OBJECTAGG(a.name, ear.resolved_with_override) AS attrs_with_override,
                JSON_OBJECTAGG(a.name, ear.resolved_current_only) AS attrs_current_only,
                JSON_OBJECTAGG(a.name, ear.resolved_approved) AS attrs_approved,
                JSON_OBJECTAGG(a.name, ear.resolved_live) AS attrs_live
            FROM entity_attribute_resolved ear
            JOIN attributes a ON ear.attribute_id = a.id
            GROUP BY ear.entity_id
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new views
        DB::statement('DROP VIEW IF EXISTS entity_attr_json');
        DB::statement('DROP VIEW IF EXISTS entity_attribute_resolved');

        // Recreate eav_input table
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

        // Recreate eav_timeseries table
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

        // Add old fields back to attributes
        Schema::table('attributes', function (Blueprint $table) {
            $table->enum('attribute_type', ['versioned','input','timeseries'])->default('versioned')->after('data_type');
            $table->enum('review_required', ['always', 'low_confidence', 'no'])->default('no')->after('attribute_type');
            $table->boolean('is_synced')->default(false)->after('linked_entity_type_id');
        });

        // Migrate data back (reverse mapping)
        DB::statement("
            UPDATE attributes
            SET
                attribute_type = CASE
                    WHEN is_sync = 'from_external' THEN 'input'
                    ELSE 'versioned'
                END,
                review_required = CASE
                    WHEN needs_approval = 'yes' THEN 'always'
                    WHEN needs_approval = 'only_low_confidence' THEN 'low_confidence'
                    ELSE 'no'
                END,
                is_synced = CASE
                    WHEN is_sync IN ('from_external', 'to_external') THEN 1
                    ELSE 0
                END
        ");

        // Drop new fields
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn(['editable', 'is_pipeline', 'is_sync', 'needs_approval']);
        });

        // Recreate old views
        DB::statement("
            CREATE VIEW eav_timeseries_latest AS
            SELECT
                entity_id,
                attribute_id,
                value,
                source,
                observed_at,
                updated_at
            FROM (
                SELECT
                    entity_id,
                    attribute_id,
                    value,
                    source,
                    observed_at,
                    updated_at,
                    ROW_NUMBER() OVER (
                        PARTITION BY entity_id, attribute_id
                        ORDER BY observed_at DESC
                    ) as rn
                FROM eav_timeseries
            ) ranked
            WHERE rn = 1
        ");

        DB::statement("
            CREATE VIEW entity_attribute_resolved AS
            SELECT
                v.entity_id,
                v.attribute_id,
                COALESCE(NULLIF(v.value_override, ''), v.value_current) AS resolved_with_override,
                v.value_current AS resolved_current_only,
                v.updated_at
            FROM eav_versioned v
            UNION ALL
            SELECT
                i.entity_id,
                i.attribute_id,
                i.value AS resolved_with_override,
                i.value AS resolved_current_only,
                i.updated_at
            FROM eav_input i
            UNION ALL
            SELECT
                t.entity_id,
                t.attribute_id,
                t.value AS resolved_with_override,
                t.value AS resolved_current_only,
                t.updated_at
            FROM eav_timeseries_latest t
        ");

        DB::statement("
            CREATE VIEW entity_attr_json AS
            SELECT
                ear.entity_id,
                JSON_OBJECTAGG(a.name, ear.resolved_with_override) AS attrs_with_override,
                JSON_OBJECTAGG(a.name, ear.resolved_current_only) AS attrs_current_only
            FROM entity_attribute_resolved ear
            JOIN attributes a ON ear.attribute_id = a.id
            GROUP BY ear.entity_id
        ");
    }
};



