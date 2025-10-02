<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $editor = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        // Create permissions for key areas
        $permissions = [
            // User management
            'view users',
            'create users',
            'edit users',
            'delete users',

            // Entity management
            'view entities',
            'create entities',
            'edit entities',
            'delete entities',

            // Attribute management
            'view attributes',
            'create attributes',
            'edit attributes',
            'delete attributes',

            // Review workflow
            'review changes',
            'approve changes',

            // Settings
            'manage settings',
            'manage syncs',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Admin has all permissions
        $admin->syncPermissions(Permission::all());

        // Editor can manage content but not users or settings
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

        // Viewer can only view
        $viewer->syncPermissions([
            'view entities',
            'view attributes',
        ]);
    }
}

