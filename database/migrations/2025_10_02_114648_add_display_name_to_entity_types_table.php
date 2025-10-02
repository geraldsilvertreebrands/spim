<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_types', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
        });

        // Populate display_name for existing entity types with capitalized pluralized names
        $entityTypes = DB::table('entity_types')->get();
        foreach ($entityTypes as $entityType) {
            $pluralized = Str::plural($entityType->name);
            $capitalized = Str::ucfirst($pluralized);
            DB::table('entity_types')
                ->where('id', $entityType->id)
                ->update(['display_name' => $capitalized]);
        }

        // Now make it non-nullable
        Schema::table('entity_types', function (Blueprint $table) {
            $table->string('display_name')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('entity_types', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
