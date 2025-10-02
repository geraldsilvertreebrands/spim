# User Management Quick Start

## Quick Commands

### Create an Admin User
```bash
docker exec spim_app bash -c "php artisan user:create-admin admin@example.com 'Admin Name' --password=yourpassword"
```

### Seed Roles and Permissions
```bash
docker exec spim_app bash -c "php artisan db:seed --class=RoleSeeder"
```

### Run Migrations
```bash
docker exec spim_app bash -c "php artisan migrate"
```

## Default Users Created

After running the setup:

| Email | Password | Role | Status |
|-------|----------|------|--------|
| admin@example.com | password | admin | Active |
| test@example.com | password | editor | Active |

## Accessing User Management

1. Log in to the admin panel: `/admin`
2. Navigate to: **Settings → Users**
3. You'll see the user management interface with:
   - List of all users
   - Create new user button
   - Edit/Delete actions
   - Activate/Deactivate toggles
   - Role filters

## Default Roles

| Role | Description | Permissions |
|------|-------------|-------------|
| **admin** | Full system access | All permissions |
| **editor** | Content management | Create/edit entities, attributes, review changes |
| **viewer** | Read-only access | View entities and attributes only |

## Adding a New Role

Edit `/database/seeders/RoleSeeder.php`:

```php
$customRole = Role::firstOrCreate(['name' => 'custom_role', 'guard_name' => 'web']);
$customRole->syncPermissions([
    'view entities',
    'edit entities',
    // Add more permissions...
]);
```

Then run:
```bash
docker exec spim_app bash -c "php artisan db:seed --class=RoleSeeder"
```

## User Management Features

✅ Create, edit, and delete users  
✅ Assign multiple roles to users  
✅ Set/reset passwords  
✅ **Users can change their own password via profile page**  
✅ Activate/deactivate accounts  
✅ Bulk operations  
✅ Filter by role and status  
✅ Prevent users from deleting themselves  
✅ Auto-logout inactive users  

## Files Created

- **User Model**: `app/Models/User.php` (updated with HasRoles trait)
- **User Resource**: `app/Filament/Resources/UserResource.php`
- **User Policy**: `app/Policies/UserPolicy.php`
- **Role Seeder**: `database/seeders/RoleSeeder.php`
- **Middleware**: `app/Http/Middleware/CheckUserIsActive.php`
- **Artisan Command**: `app/Console/Commands/CreateAdminUser.php`
- **Migration**: `database/migrations/2025_10_02_120000_add_is_active_to_users_table.php`

## Testing

Log in as admin (admin@example.com / password) and:
1. Go to Settings → Users
2. Create a test viewer user
3. Log in as that viewer
4. Verify you only see view permissions (no create/edit buttons)
5. Click your name in top-right → Profile
6. Change your password to test self-service password changes

## Next Steps

- Add more custom roles as needed
- Define resource-specific permissions
- Extend the UserPolicy for custom authorization logic
- Add more permissions to the RoleSeeder

