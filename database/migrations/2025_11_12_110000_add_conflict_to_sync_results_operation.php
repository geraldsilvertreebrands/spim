<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'conflict' to the operation enum
        DB::statement("ALTER TABLE sync_results MODIFY COLUMN operation ENUM('create', 'update', 'skip', 'validate', 'conflict') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'conflict' from the enum
        DB::statement("ALTER TABLE sync_results MODIFY COLUMN operation ENUM('create', 'update', 'skip', 'validate') NULL");
    }
};
