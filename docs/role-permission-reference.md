# Role & Permission Reference

Complete reference for all roles and permissions in the Silvertree Platform.

## Roles Overview

| Role | Panel Access | Description |
|------|--------------|-------------|
| `admin` | All panels | Full system access |
| `pim-editor` | PIM | Internal product data editors |
| `supplier-basic` | Supply | External suppliers (basic tier) |
| `supplier-premium` | Supply | External suppliers (premium tier) |
| `pricing-analyst` | Pricing | Internal pricing team |

## Permissions by Category

### PIM Panel Permissions

| Permission | Description | Roles |
|------------|-------------|-------|
| `access-pim-panel` | Access to PIM panel | admin, pim-editor |
| `manage-products` | Create, edit, delete products | admin, pim-editor |
| `manage-attributes` | Manage attribute definitions | admin, pim-editor |
| `run-pipelines` | Execute AI pipelines | admin, pim-editor |
| `run-magento-sync` | Trigger Magento synchronization | admin, pim-editor |

### Supply Panel Permissions

| Permission | Description | Roles |
|------------|-------------|-------|
| `access-supply-panel` | Access to Supply portal | admin, supplier-basic, supplier-premium |
| `view-own-brand-data` | View data for assigned brands | supplier-basic, supplier-premium |
| `view-premium-features` | Access premium analytics | admin, supplier-premium |

### Pricing Panel Permissions

| Permission | Description | Roles |
|------------|-------------|-------|
| `access-pricing-panel` | Access to Pricing tool | admin, pricing-analyst |
| `manage-price-alerts` | Configure pricing alerts | admin, pricing-analyst |

### Administrative Permissions

| Permission | Description | Roles |
|------------|-------------|-------|
| `manage-users` | Create, edit, delete users | admin |
| `manage-brands` | Manage brand records | admin |

### Legacy Permissions

These permissions are maintained for backward compatibility:

| Permission | Description |
|------------|-------------|
| `view users`, `create users`, `edit users`, `delete users` | User CRUD |
| `view entities`, `create entities`, `edit entities`, `delete entities` | Entity CRUD |
| `view attributes`, `create attributes`, `edit attributes`, `delete attributes` | Attribute CRUD |
| `review changes`, `approve changes` | Approval workflow |
| `manage settings`, `manage syncs` | System settings |

## Role Details

### Admin (`admin`)

Full access to all features across all panels.

**Capabilities:**
- Access all three panels (PIM, Supply, Pricing)
- Manage users and roles
- Configure system settings
- Run all sync operations
- View all brands and suppliers
- Switch between panels via navigation

### PIM Editor (`pim-editor`)

Internal team members who manage product information.

**Capabilities:**
- Full access to PIM panel
- Manage products and categories
- Edit attributes and attribute sections
- Run AI pipelines
- Trigger Magento sync
- Review and approve changes

**Restrictions:**
- No access to Supply or Pricing panels
- Cannot manage users or system settings

### Supplier Basic (`supplier-basic`)

External suppliers with limited portal access.

**Capabilities:**
- Access Supply portal
- View sales data for assigned brands
- View inventory alerts
- Access basic dashboards

**Restrictions:**
- Cannot see competitor comparisons
- Cannot access premium analytics
- Limited to assigned brands only

### Supplier Premium (`supplier-premium`)

External suppliers with full portal access.

**Capabilities:**
- All Supplier Basic capabilities, plus:
- Competitor comparison tools
- Advanced analytics dashboards
- Historical trend analysis
- Premium support features

**Restrictions:**
- Limited to assigned brands only
- No access to PIM or Pricing panels

### Pricing Analyst (`pricing-analyst`)

Internal pricing team members.

**Capabilities:**
- Access Pricing tool
- Configure price alerts
- View competitive pricing data
- Generate pricing reports

**Restrictions:**
- No access to PIM or Supply panels
- Cannot manage users

## Brand Access Control

Suppliers are restricted to viewing data only for their assigned brands.

### Assigning Brands to Suppliers

```php
// Assign a brand to a supplier user
$user = User::find($userId);
$user->brands()->attach($brandId);

// Check if user can access a brand
$canAccess = $user->canAccessBrand($brand);

// Get all accessible brand IDs
$brandIds = $user->accessibleBrandIds();
```

### Database Structure

The `supplier_brand_access` pivot table links users to brands:

```sql
CREATE TABLE supplier_brand_access (
    user_id BIGINT UNSIGNED,
    brand_id BIGINT UNSIGNED,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    PRIMARY KEY (user_id, brand_id)
);
```

## Managing Roles

### Via Seeder (Recommended)

```bash
php artisan db:seed --class=RoleSeeder
```

### Programmatically

```php
// Assign role to user
$user->assignRole('supplier-basic');

// Check if user has role
$user->hasRole('admin');

// Check for any of multiple roles
$user->hasAnyRole(['admin', 'pim-editor']);

// Remove role
$user->removeRole('supplier-basic');

// Sync roles (replace all)
$user->syncRoles(['supplier-premium']);
```

## Test Users

For development and testing, seed test users:

```bash
php artisan db:seed --class=TestUserSeeder
```

This creates:

| Email | Password | Role |
|-------|----------|------|
| admin@silvertreebrands.com | password | admin |
| pim@silvertreebrands.com | password | pim-editor |
| supplier-basic@test.com | password | supplier-basic |
| supplier-premium@test.com | password | supplier-premium |
| pricing@silvertreebrands.com | password | pricing-analyst |

## Middleware Configuration

Panel access is enforced via middleware:

| Panel | Middleware Class |
|-------|-----------------|
| PIM | `EnsureUserCanAccessPimPanel` |
| Supply | `EnsureUserCanAccessSupplyPanel` |
| Pricing | `EnsureUserCanAccessPricingPanel` |

These middleware classes check the user's role and redirect unauthorized users to their appropriate panel with an error message.
