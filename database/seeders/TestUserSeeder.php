<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    /**
     * Create test users for each role.
     *
     * This seeder is idempotent - safe to run multiple times.
     *
     * Test Users Created:
     * - admin@silvertreebrands.com (admin) - Full access to all panels
     * - pim@silvertreebrands.com (pim-editor) - PIM panel access
     * - supplier-basic@test.com (supplier-basic) - Supply panel (basic features)
     * - supplier-premium@test.com (supplier-premium) - Supply panel (all features)
     * - pricing@silvertreebrands.com (pricing-analyst) - Pricing panel access
     */
    public function run(): void
    {
        // Admin - full access to all panels
        $admin = User::firstOrCreate(
            ['email' => 'admin@silvertreebrands.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]
        );
        $admin->syncRoles(['admin']);

        // PIM Editor - access to PIM panel
        $pimEditor = User::firstOrCreate(
            ['email' => 'pim@silvertreebrands.com'],
            [
                'name' => 'PIM Editor',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]
        );
        $pimEditor->syncRoles(['pim-editor']);

        // Supplier Basic - basic access to Supply panel
        $supplierBasic = User::firstOrCreate(
            ['email' => 'supplier-basic@test.com'],
            [
                'name' => 'Basic Supplier',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]
        );
        $supplierBasic->syncRoles(['supplier-basic']);

        // Assign first brand to supplier if brands exist
        if ($brand = Brand::first()) {
            $supplierBasic->brands()->syncWithoutDetaching([$brand->id]);
        }

        // Supplier Premium - full access to Supply panel
        $supplierPremium = User::firstOrCreate(
            ['email' => 'supplier-premium@test.com'],
            [
                'name' => 'Premium Supplier',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]
        );
        $supplierPremium->syncRoles(['supplier-premium']);

        // Assign first brand to premium supplier if brands exist
        if ($brand = Brand::first()) {
            $supplierPremium->brands()->syncWithoutDetaching([$brand->id]);
        }

        // Pricing Analyst - access to Pricing panel
        $pricingAnalyst = User::firstOrCreate(
            ['email' => 'pricing@silvertreebrands.com'],
            [
                'name' => 'Pricing Analyst',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]
        );
        $pricingAnalyst->syncRoles(['pricing-analyst']);

        $this->command->info('Test users created/updated successfully.');
        $this->command->table(
            ['Email', 'Role', 'Panel Access'],
            [
                ['admin@silvertreebrands.com', 'admin', 'All panels'],
                ['pim@silvertreebrands.com', 'pim-editor', 'PIM'],
                ['supplier-basic@test.com', 'supplier-basic', 'Supply (basic)'],
                ['supplier-premium@test.com', 'supplier-premium', 'Supply (premium)'],
                ['pricing@silvertreebrands.com', 'pricing-analyst', 'Pricing'],
            ]
        );
    }
}
