<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RoleSeeder::class);

        // Seed sample entity types and data
        $this->call(SampleDevDatasetSeeder::class);

        // Create test users for development
        $this->call(TestUserSeeder::class);
    }
}
