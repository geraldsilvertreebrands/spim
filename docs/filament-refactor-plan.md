# Filament Multi-Panel Refactoring Plan

> Documentation for migrating from single AdminPanel to three separate panels (PIM, Supply, Pricing)
> Created: December 2024

## Current Structure

### Panel Provider
Single panel provider at `app/Providers/Filament/AdminPanelProvider.php`:
- **ID**: `admin`
- **Path**: `/admin`
- **Color**: Amber
- **Theme**: `resources/css/filament/admin/theme.css`
- **Auto-discovers**: Resources, Pages, Widgets from `app/Filament/`

### Current File Tree
```
app/
├── Filament/
│   ├── Pages/
│   │   ├── MagentoSync.php              # Magento sync dashboard
│   │   └── ReviewQueue.php              # Approval workflow queue
│   │
│   ├── Resources/
│   │   ├── AbstractEntityTypeResource.php    # Base class for entity resources
│   │   │
│   │   ├── AttributeResource.php             # Attribute CRUD
│   │   └── AttributeResource/
│   │       └── Pages/
│   │           ├── CreateAttribute.php
│   │           ├── EditAttribute.php
│   │           └── ListAttributes.php
│   │
│   │   ├── AttributeSectionResource.php      # Attribute sections CRUD
│   │   └── AttributeSectionResource/
│   │       └── Pages/
│   │           ├── CreateAttributeSection.php
│   │           ├── EditAttributeSection.php
│   │           └── ListAttributeSections.php
│   │
│   │   ├── CategoryEntityResource.php        # Category management
│   │   └── CategoryEntityResource/
│   │       └── Pages/
│   │           ├── CreateCategories.php
│   │           ├── EditCategories.php
│   │           ├── ListCategories.php
│   │           └── SideBySideEditCategories.php
│   │
│   │   ├── EntityTypeResource.php            # Entity type config
│   │   └── EntityTypeResource/
│   │       └── Pages/
│   │           ├── CreateEntityType.php
│   │           ├── EditEntityType.php
│   │           └── ListEntityTypes.php
│   │
│   │   ├── Pages/                            # Shared abstract pages
│   │   │   ├── AbstractCreateEntityRecord.php
│   │   │   ├── AbstractEditEntityRecord.php
│   │   │   ├── AbstractListEntityRecords.php
│   │   │   └── AbstractSideBySideEdit.php
│   │
│   │   ├── PipelineResource.php              # AI Pipeline management
│   │   └── PipelineResource/
│   │       ├── Pages/
│   │       │   ├── CreatePipeline.php
│   │       │   ├── EditPipeline.php
│   │       │   └── ListPipelines.php
│   │       └── RelationManagers/
│   │           └── PipelineEvalsRelationManager.php
│   │
│   │   ├── ProductEntityResource.php         # Product management
│   │   └── ProductEntityResource/
│   │       └── Pages/
│   │           ├── CreateProduct.php
│   │           ├── EditProduct.php
│   │           ├── ListProducts.php
│   │           └── SideBySideEditProducts.php
│   │
│   │   └── UserResource.php                  # User management
│   │       └── UserResource/
│   │           └── Pages/
│   │               ├── CreateUser.php
│   │               ├── EditUser.php
│   │               └── ListUsers.php
│   │
│   └── Widgets/
│       └── MagentoSyncStats.php              # Sync statistics widget
│
└── Providers/
    └── Filament/
        └── AdminPanelProvider.php            # Single panel definition

resources/
├── css/
│   └── filament/
│       └── admin/
│           └── theme.css                     # Panel theme
│
└── views/
    └── filament/
        ├── components/
        │   ├── attribute-label-wrapper.blade.php
        │   ├── attribute-label.blade.php
        │   ├── attribute-overridable-value.blade.php
        │   ├── attribute-verification-results.blade.php
        │   ├── no-errors.blade.php
        │   ├── pipeline-metadata.blade.php
        │   ├── sync-details.blade.php
        │   └── sync-errors.blade.php
        │
        └── pages/
            ├── entity-browser.blade.php
            ├── magento-sync.blade.php
            ├── review-queue.blade.php
            └── side-by-side-edit.blade.php
```

### File Counts
| Category | Count |
|----------|-------|
| Panel Providers | 1 |
| Pages | 2 |
| Resources | 8 |
| Resource Page Files | 23 |
| Relation Managers | 1 |
| Widgets | 1 |
| Blade Components | 8 |
| Blade Pages | 4 |
| **Total PHP Files** | **39** |

---

## Target Structure

### Three Panels

| Panel | ID | Path | Color | Users |
|-------|-----|------|-------|-------|
| PIM | `pim` | `/pim` | Green (#006654) | admin, pim-editor |
| Supply | `supply` | `/supply` | Blue | admin, supplier-basic, supplier-premium |
| Pricing | `pricing` | `/pricing` | Purple | admin, pricing-analyst |

### Target File Tree
```
app/
├── Filament/
│   ├── Shared/                              # NEW - Cross-panel components
│   │   ├── Components/
│   │   │   └── (shared form components)
│   │   ├── Pages/
│   │   │   ├── AbstractCreateEntityRecord.php   # MOVE from Resources/Pages
│   │   │   ├── AbstractEditEntityRecord.php
│   │   │   ├── AbstractListEntityRecords.php
│   │   │   └── AbstractSideBySideEdit.php
│   │   └── Widgets/
│   │       └── (shared widgets)
│   │
│   ├── PimPanel/                            # REORGANIZED - PIM resources
│   │   ├── Pages/
│   │   │   ├── MagentoSync.php              # MOVE from Pages/
│   │   │   └── ReviewQueue.php              # MOVE from Pages/
│   │   ├── Resources/
│   │   │   ├── AttributeResource.php        # MOVE + update namespace
│   │   │   ├── AttributeResource/
│   │   │   ├── AttributeSectionResource.php
│   │   │   ├── AttributeSectionResource/
│   │   │   ├── CategoryResource.php         # RENAME from CategoryEntityResource
│   │   │   ├── CategoryResource/
│   │   │   ├── EntityTypeResource.php
│   │   │   ├── EntityTypeResource/
│   │   │   ├── PipelineResource.php
│   │   │   ├── PipelineResource/
│   │   │   ├── ProductResource.php          # RENAME from ProductEntityResource
│   │   │   ├── ProductResource/
│   │   │   ├── UserResource.php
│   │   │   └── UserResource/
│   │   └── Widgets/
│   │       └── MagentoSyncStats.php         # MOVE from Widgets/
│   │
│   ├── SupplyPanel/                         # NEW - Supplier portal
│   │   ├── Pages/
│   │   │   ├── Dashboard.php                # Supplier dashboard
│   │   │   ├── SalesOverview.php            # Sales charts
│   │   │   └── MarketShare.php              # Market position
│   │   ├── Resources/
│   │   │   └── BrandResource.php            # Brand-specific views
│   │   └── Widgets/
│   │       ├── SalesKpiWidget.php
│   │       └── InventoryAlertWidget.php
│   │
│   └── PricingPanel/                        # NEW - Pricing tool
│       ├── Pages/
│       │   └── Dashboard.php                # Pricing dashboard
│       ├── Resources/
│       │   └── PriceTrackResource.php       # Price tracking
│       └── Widgets/
│           └── MarginWidget.php
│
└── Providers/
    └── Filament/
        ├── PimPanelProvider.php             # RENAME from AdminPanelProvider
        ├── SupplyPanelProvider.php          # NEW
        └── PricingPanelProvider.php         # NEW

resources/
├── css/
│   └── filament/
│       ├── pim/
│       │   └── theme.css                    # RENAME from admin/
│       ├── supply/
│       │   └── theme.css                    # NEW
│       └── pricing/
│           └── theme.css                    # NEW
│
└── views/
    └── filament/
        ├── components/                       # KEEP - shared across panels
        │   └── (existing components)
        │
        └── pages/                            # KEEP - shared page views
            └── (existing page views)
```

---

## Migration Plan

### Phase 1: Create Directory Structure (C-002)
**Risk: LOW** - Just creating empty directories

```bash
mkdir -p app/Filament/Shared/{Components,Pages,Widgets}
mkdir -p app/Filament/PimPanel/{Pages,Resources,Widgets}
mkdir -p app/Filament/SupplyPanel/{Pages,Resources,Widgets}
mkdir -p app/Filament/PricingPanel/{Pages,Resources,Widgets}
mkdir -p resources/css/filament/{pim,supply,pricing}
```

### Phase 2: Create Panel Providers (C-003, C-005, C-006)
**Risk: MEDIUM** - Core panel configuration

1. **Rename** `AdminPanelProvider.php` → `PimPanelProvider.php`
2. **Update** class name, panel ID, path, colors
3. **Create** empty `SupplyPanelProvider.php`
4. **Create** empty `PricingPanelProvider.php`
5. **Register** all providers in `config/app.php`

### Phase 3: Move PIM Resources (C-004)
**Risk: HIGH** - Most complex, touches all existing code

| Source | Destination | Namespace Change |
|--------|-------------|------------------|
| `Filament/Resources/*.php` | `Filament/PimPanel/Resources/*.php` | `App\Filament\Resources` → `App\Filament\PimPanel\Resources` |
| `Filament/Pages/*.php` | `Filament/PimPanel/Pages/*.php` | `App\Filament\Pages` → `App\Filament\PimPanel\Pages` |
| `Filament/Widgets/*.php` | `Filament/PimPanel/Widgets/*.php` | `App\Filament\Widgets` → `App\Filament\PimPanel\Widgets` |
| `Filament/Resources/Pages/*.php` | `Filament/Shared/Pages/*.php` | `App\Filament\Resources\Pages` → `App\Filament\Shared\Pages` |

**File-by-File Migration Checklist:**

- [ ] Move `AbstractEntityTypeResource.php` → `PimPanel/Resources/`
- [ ] Move `AttributeResource.php` + folder → `PimPanel/Resources/`
- [ ] Move `AttributeSectionResource.php` + folder → `PimPanel/Resources/`
- [ ] Move `CategoryEntityResource.php` + folder → `PimPanel/Resources/` (rename to CategoryResource)
- [ ] Move `EntityTypeResource.php` + folder → `PimPanel/Resources/`
- [ ] Move `PipelineResource.php` + folder → `PimPanel/Resources/`
- [ ] Move `ProductEntityResource.php` + folder → `PimPanel/Resources/` (rename to ProductResource)
- [ ] Move `UserResource.php` + folder → `PimPanel/Resources/`
- [ ] Move `Resources/Pages/Abstract*.php` → `Shared/Pages/`
- [ ] Move `Pages/MagentoSync.php` → `PimPanel/Pages/`
- [ ] Move `Pages/ReviewQueue.php` → `PimPanel/Pages/`
- [ ] Move `Widgets/MagentoSyncStats.php` → `PimPanel/Widgets/`

### Phase 4: Update Imports (C-004 continued)
**Risk: HIGH** - Broken imports will cause immediate failures

Every moved file needs:
1. Update `namespace` declaration
2. Update all `use` statements referencing moved classes
3. Update any class references in other files

### Phase 5: Create Panel Access Middleware (C-007)
**Risk: MEDIUM** - Security-critical

New middleware to enforce:
- PIM Panel: `admin` or `pim-editor` role
- Supply Panel: `admin` or `supplier-basic` or `supplier-premium` role
- Pricing Panel: `admin` or `pricing-analyst` role

### Phase 6: Update Tests (C-011)
**Risk: HIGH** - 314 tests that may reference old namespaces

- Update namespace references in test files
- Update route assertions (`/admin` → `/pim`)
- Add tests for new panels

---

## Risk Assessment

### High Risk Items

| Risk | Impact | Mitigation |
|------|--------|------------|
| Broken namespace imports | App crashes | Run tests after each file move |
| Route changes break bookmarks | User confusion | Add redirects from `/admin` → `/pim` |
| Tests fail after refactor | CI broken | Update tests incrementally |
| Abstract class references break | Multiple resources fail | Move abstract classes first |

### Medium Risk Items

| Risk | Impact | Mitigation |
|------|--------|------------|
| Panel provider registration order | Wrong default panel | Explicitly set default |
| Middleware not applied correctly | Security holes | Test each role type |
| Theme CSS not loading | Broken styling | Verify viteTheme paths |

### Low Risk Items

| Risk | Impact | Mitigation |
|------|--------|------------|
| Directory structure changes | Confusion | Clear documentation |
| New empty panels | No functionality | Expected, resources added later |

---

## Testing Strategy

### Before Starting
```bash
php artisan test  # Verify 314 tests pass
```

### After Each Major Change
```bash
php artisan test --filter=<relevant>  # Quick check
php artisan route:list | grep filament  # Verify routes
```

### After Complete Migration
```bash
php artisan test  # All tests must pass
php artisan serve  # Manual verification of all panels
```

---

## Rollback Plan

If migration fails:

1. **Git reset**: `git checkout -- app/Filament app/Providers`
2. **Clear caches**: `php artisan optimize:clear`
3. **Re-run migrations**: `php artisan migrate:fresh --seed`
4. **Verify**: `php artisan test`

---

## Dependencies & Prerequisites

### Must Complete First
- [x] Phase A: Foundation & Fixes
- [x] Phase B: BigQuery Integration (code complete)

### External Dependencies
- None - this is purely internal refactoring

### Package Requirements
- Filament 4.x (already installed)
- Spatie Permissions (already installed)

---

## Estimated Timeline

| Ticket | Effort | Notes |
|--------|--------|-------|
| C-001 | 2 hours | This document |
| C-002 | 15 min | Create directories |
| C-003 | 2 hours | PIM Panel Provider |
| C-004 | 3 hours | Move resources (most work) |
| C-005 | 1 hour | Supply Panel Provider |
| C-006 | 30 min | Pricing Panel Provider |
| C-007 | 2 hours | Access middleware |
| C-008 | 1 hour | Role seeder |
| C-009 | 1 hour | Test users |
| C-010 | 1 hour | Navigation testing |
| C-011 | 3-4 hours | Update tests |
| C-012-015 | 6 hours | Polish & docs |

**Total Estimate**: 20-24 hours of development time
