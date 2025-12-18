<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates all roles and permissions required for multi-panel architecture:
     * - PIM Panel: admin, pim-editor
     * - Supply Panel: admin, supplier-basic, supplier-premium
     * - Pricing Panel: admin, pricing-analyst
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions
        $permissions = [
            // PIM permissions
            'access-pim-panel',
            'manage-products',
            'manage-attributes',
            'run-pipelines',
            'run-magento-sync',

            // Supply permissions
            'access-supply-panel',
            'view-own-brand-data',
            'view-premium-features',

            // Pricing permissions
            'access-pricing-panel',
            'manage-price-alerts',

            // Admin permissions
            'manage-users',
            'manage-brands',

            // Legacy permissions (kept for backward compatibility)
            'view users',
            'create users',
            'edit users',
            'delete users',
            'view entities',
            'create entities',
            'edit entities',
            'delete entities',
            'view attributes',
            'create attributes',
            'edit attributes',
            'delete attributes',
            'review changes',
            'approve changes',
            'manage settings',
            'manage syncs',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Admin - full access to all panels
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        // PIM Editor - full access to PIM panel
        $pimEditor = Role::firstOrCreate(['name' => 'pim-editor', 'guard_name' => 'web']);
        $pimEditor->syncPermissions([
            'access-pim-panel',
            'manage-products',
            'manage-attributes',
            'run-pipelines',
            'run-magento-sync',
            'view entities',
            'create entities',
            'edit entities',
            'view attributes',
            'create attributes',
            'edit attributes',
            'review changes',
            'approve changes',
        ]);

        // Supplier Basic - limited access to Supply panel
        $supplierBasic = Role::firstOrCreate(['name' => 'supplier-basic', 'guard_name' => 'web']);
        $supplierBasic->syncPermissions([
            'access-supply-panel',
            'view-own-brand-data',
        ]);

        // Supplier Premium - full access to Supply panel including premium features
        $supplierPremium = Role::firstOrCreate(['name' => 'supplier-premium', 'guard_name' => 'web']);
        $supplierPremium->syncPermissions([
            'access-supply-panel',
            'view-own-brand-data',
            'view-premium-features',
        ]);

        // Pricing Analyst - access to Pricing panel
        $pricingAnalyst = Role::firstOrCreate(['name' => 'pricing-analyst', 'guard_name' => 'web']);
        $pricingAnalyst->syncPermissions([
            'access-pricing-panel',
            'manage-price-alerts',
        ]);

        // Legacy roles (kept for backward compatibility)
        $editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
        $editor->syncPermissions([
            'view entities',
            'create entities',
            'edit entities',
            'view attributes',
            'create attributes',
            'edit attributes',
            'review changes',
            'approve changes',
        ]);

        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->syncPermissions([
            'view entities',
            'view attributes',
        ]);
    }
}
