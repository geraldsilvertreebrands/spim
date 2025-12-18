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
        // Modify the is_sync enum to include 'bidirectional'
        DB::statement("ALTER TABLE attributes MODIFY COLUMN is_sync ENUM('no', 'from_external', 'to_external', 'bidirectional') NOT NULL DEFAULT 'no'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, reset any bidirectional values to 'no' to avoid data loss
        DB::statement("UPDATE attributes SET is_sync = 'no' WHERE is_sync = 'bidirectional'");

        // Then modify the enum back to the original values
        DB::statement("ALTER TABLE attributes MODIFY COLUMN is_sync ENUM('no', 'from_external', 'to_external') NOT NULL DEFAULT 'no'");
    }
};
