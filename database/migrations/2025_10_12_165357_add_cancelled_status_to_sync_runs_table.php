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
        // Modify the status ENUM to include 'cancelled'
        DB::statement("ALTER TABLE sync_runs MODIFY COLUMN status ENUM('running', 'completed', 'failed', 'partial', 'cancelled') DEFAULT 'running'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the status ENUM to original values
        DB::statement("ALTER TABLE sync_runs MODIFY COLUMN status ENUM('running', 'completed', 'failed', 'partial') DEFAULT 'running'");
    }
};
