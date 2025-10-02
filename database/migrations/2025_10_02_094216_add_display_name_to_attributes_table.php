<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
        });

        // Update existing records to use name as display_name if display_name is null
        DB::table('attributes')->whereNull('display_name')->update([
            'display_name' => DB::raw('name'),
        ]);
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
