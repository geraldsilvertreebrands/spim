# Panel Access Matrix

Quick reference for who can access what in the Silvertree Platform.

## Panel Access by Role

|  | PIM Panel | Supply Portal | Pricing Tool |
|--|:---------:|:-------------:|:------------:|
| **admin** | ✅ | ✅ | ✅ |
| **pim-editor** | ✅ | ❌ | ❌ |
| **supplier-basic** | ❌ | ✅ | ❌ |
| **supplier-premium** | ❌ | ✅ | ❌ |
| **pricing-analyst** | ❌ | ❌ | ✅ |

## Panel URLs

| Panel | URL | Login URL |
|-------|-----|-----------|
| PIM | `/pim` | `/pim/login` |
| Supply | `/supply` | `/supply/login` |
| Pricing | `/pricing` | `/pricing/login` |

## Feature Access Matrix

### PIM Panel Features

| Feature | admin | pim-editor |
|---------|:-----:|:----------:|
| View Products | ✅ | ✅ |
| Edit Products | ✅ | ✅ |
| Manage Attributes | ✅ | ✅ |
| Run Pipelines | ✅ | ✅ |
| Magento Sync | ✅ | ✅ |
| Manage Users | ✅ | ❌ |
| View Queue Monitor | ✅ | ❌ |
| Switch Panels | ✅ | ❌ |

### Supply Portal Features

| Feature | admin | supplier-basic | supplier-premium |
|---------|:-----:|:--------------:|:----------------:|
| View Dashboard | ✅ | ✅ | ✅ |
| Sales Data | ✅ | ✅ | ✅ |
| Inventory Alerts | ✅ | ✅ | ✅ |
| Basic Charts | ✅ | ✅ | ✅ |
| Competitor Comparison | ✅ | 🔒 | ✅ |
| Advanced Analytics | ✅ | 🔒 | ✅ |
| Historical Trends | ✅ | 🔒 | ✅ |
| Export Reports | ✅ | 🔒 | ✅ |
| Switch Panels | ✅ | ❌ | ❌ |

🔒 = Premium feature (locked for basic tier)

### Pricing Tool Features

| Feature | admin | pricing-analyst |
|---------|:-----:|:---------------:|
| View Dashboard | ✅ | ✅ |
| Price Monitoring | ✅ | ✅ |
| Margin Analysis | ✅ | ✅ |
| Configure Alerts | ✅ | ✅ |
| Export Reports | ✅ | ✅ |
| Switch Panels | ✅ | ❌ |

## Data Access Scope

### Brand-Level Access

| Role | Data Scope |
|------|------------|
| admin | All brands |
| pim-editor | All entities (no brand filter) |
| supplier-basic | Assigned brands only |
| supplier-premium | Assigned brands only |
| pricing-analyst | All pricing data |

### Homepage Redirect Logic

When users visit `/`:

| User State | Redirect To |
|------------|-------------|
| Not logged in | `/pim/login` |
| Admin | `/pim` |
| PIM Editor | `/pim` |
| Supplier (any) | `/supply` |
| Pricing Analyst | `/pricing` |

## Access Denied Behavior

When a user tries to access a panel they don't have permission for:

1. Middleware intercepts the request
2. User is redirected to their appropriate panel
3. Error notification is displayed

### Error Messages

| Attempted Panel | User Role | Message |
|-----------------|-----------|---------|
| PIM | supplier-* | "You do not have access to the PIM panel" |
| Supply | pim-editor | "You do not have access to the Supply portal" |
| Pricing | supplier-* | "You do not have access to the Pricing tool" |

## Panel Branding

Each panel has distinct visual branding:

| Panel | Primary Color | Brand Name |
|-------|---------------|------------|
| PIM | Green (#006654) | Silvertree PIM |
| Supply | Blue | Supplier Portal |
| Pricing | Indigo (#4f46e5) | Pricing Tool |

## Checking Access Programmatically

### Check Panel Access

```php
// Check if user can access PIM
$user->hasAnyRole(['admin', 'pim-editor']);

// Check if user can access Supply
$user->hasAnyRole(['admin', 'supplier-basic', 'supplier-premium']);

// Check if user can access Pricing
$user->hasAnyRole(['admin', 'pricing-analyst']);
```

### Check Premium Features

```php
// Check if user has premium supply features
$user->hasPermissionTo('view-premium-features');

// Or check role directly
$user->hasAnyRole(['admin', 'supplier-premium']);
```

### Check Brand Access

```php
// Check if user can access specific brand
$canAccess = $user->canAccessBrand($brand);

// Get all accessible brands
$brands = Brand::whereIn('id', $user->accessibleBrandIds())->get();
```

## Quick Reference Card

```
┌─────────────────────────────────────────────────────────────┐
│                    PANEL ACCESS QUICK REF                    │
├─────────────────────────────────────────────────────────────┤
│  ADMIN          → All panels (PIM, Supply, Pricing)         │
│  PIM-EDITOR     → PIM only                                  │
│  SUPPLIER-*     → Supply only (premium gets more features)  │
│  PRICING-ANALYST → Pricing only                             │
├─────────────────────────────────────────────────────────────┤
│  /           → Auto-redirect based on role                  │
│  /pim        → PIM Panel                                    │
│  /supply     → Supply Portal                                │
│  /pricing    → Pricing Tool                                 │
└─────────────────────────────────────────────────────────────┘
```
