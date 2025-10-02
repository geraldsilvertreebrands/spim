# User Management System

## Overview

The application uses Spatie Laravel Permission for role-based access control (RBAC). This provides a flexible and extensible system for managing user permissions.

## Roles

The system comes with three pre-defined roles:

### Admin
- Full access to all features
- Can manage users, entities, attributes, settings, and syncs
- Has all permissions

### Editor
- Can manage content (entities and attributes)
- Can review and approve changes
- Cannot manage users or system settings
- Permissions:
  - view entities, create entities, edit entities
  - view attributes, create attributes, edit attributes
  - review changes, approve changes

### Viewer
- Read-only access
- Can view entities and attributes
- Cannot make changes or approve workflows
- Permissions:
  - view entities
  - view attributes

## User Status

Users have an `is_active` field:
- **Active users** can log in and access the system
- **Inactive users** are automatically logged out and cannot log in
- This is useful for temporarily disabling accounts without deleting them

## User Profile / Self-Service

All users can manage their own account by clicking their name in the top-right corner and selecting "Profile" (or navigating to `/admin/profile`):

- **Update name and email**: Users can edit their own information
- **Change password**: Users can change their own password
- **No admin required**: Users don't need admin intervention for basic account updates

This provides self-service capability while keeping admin controls separate for role management and account activation.

## User Management Interface

Access the user management interface at `/admin/users` (requires admin role).

Features:
- **Create users**: Add new users with email, name, password, and role(s)
- **Edit users**: Update user information, change roles, reset passwords
- **Activate/Deactivate**: Toggle user active status
- **Bulk actions**: Activate or deactivate multiple users at once
- **Filters**: Filter by active status and role

## Creating Users

### Via Admin Interface
1. Navigate to Settings > Users
2. Click "Create User"
3. Fill in the user details
4. Assign one or more roles
5. Save

### Via Command Line

Create an admin user:
```bash
php artisan user:create-admin admin@example.com "Admin Name" --password=yourpassword
```

Or interactively (will prompt for password):
```bash
php artisan user:create-admin admin@example.com "Admin Name"
```

## Adding Custom Roles

To add new roles:

1. **Create the role in the database**:
   ```php
   use Spatie\Permission\Models\Role;
   use Spatie\Permission\Models\Permission;
   
   // Create the role
   $role = Role::create(['name' => 'content_manager', 'guard_name' => 'web']);
   
   // Assign permissions
   $role->givePermissionTo([
       'view entities',
       'create entities',
       'edit entities',
       'view attributes',
   ]);
   ```

2. **Or add it to the RoleSeeder** (`database/seeders/RoleSeeder.php`):
   ```php
   $contentManager = Role::firstOrCreate(['name' => 'content_manager', 'guard_name' => 'web']);
   $contentManager->syncPermissions([
       'view entities',
       'create entities',
       'edit entities',
       'view attributes',
   ]);
   ```

3. **Run the seeder**:
   ```bash
   php artisan db:seed --class=RoleSeeder
   ```

## Available Permissions

Current permissions:
- `view users`, `create users`, `edit users`, `delete users`
- `view entities`, `create entities`, `edit entities`, `delete entities`
- `view attributes`, `create attributes`, `edit attributes`, `delete attributes`
- `review changes`, `approve changes`
- `manage settings`, `manage syncs`

To add new permissions, update the `RoleSeeder` and run it again.

## Checking Permissions in Code

### In Controllers/Services
```php
// Check if user has permission
if (auth()->user()->can('edit entities')) {
    // User can edit entities
}

// Check if user has role
if (auth()->user()->hasRole('admin')) {
    // User is an admin
}

// Check for any of multiple permissions
if (auth()->user()->hasAnyPermission(['edit entities', 'view entities'])) {
    // User can either edit or view
}
```

### In Blade Views
```blade
@can('edit users')
    <button>Edit User</button>
@endcan

@role('admin')
    <a href="/admin/settings">Settings</a>
@endrole
```

### In Filament Resources
Policies are automatically enforced. Create a policy class extending the UserPolicy pattern:
```php
public function viewAny(User $user): bool
{
    return $user->hasPermissionTo('view entities') || $user->hasRole('admin');
}
```

## Security Features

1. **Active User Check**: Middleware automatically logs out inactive users
2. **Self-Protection**: Users cannot delete themselves
3. **Policy-Based Authorization**: All resources use policies for fine-grained control
4. **Password Hashing**: Passwords are automatically hashed using Laravel's bcrypt

## Database Structure

The permission system uses these tables:
- `roles`: Stores role definitions
- `permissions`: Stores permission definitions
- `model_has_roles`: Links users to roles (many-to-many)
- `model_has_permissions`: Links users to specific permissions (many-to-many)
- `role_has_permissions`: Links roles to permissions (many-to-many)

Users can have:
- Multiple roles
- Direct permissions (in addition to role permissions)
- The `is_active` flag on the `users` table

## Extending the System

The system is designed to be easily extensible:

1. **Add new roles**: No code changes needed, just database entries
2. **Add new permissions**: Update the seeder and assign to roles
3. **Custom authorization logic**: Extend policies or create new ones
4. **Resource-level permissions**: Add policies for each Filament resource

All without rewriting core authorization code.

