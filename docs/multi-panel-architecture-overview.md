# Multi-Panel Architecture Overview

## Executive Summary

This document outlines the architectural approach for expanding the existing **SPIM** (Silvertree Product Information Manager) into a **modular monolith** supporting three distinct Filament panels:

1. **PIM** (Product Information Manager) - *Exists*
2. **Supply Insights** - *To be built*
3. **Pricing** - *To be built*

---

## 1. What Already Exists

### Current Application: SPIM (Product Information Manager)

SPIM is a **fully functional** Laravel 12 + Filament 4 application with:

#### Core Infrastructure
- **EAV System**: Flexible Entity-Attribute-Value storage with versioning
  - Four value states: `current`, `approved`, `live`, `override`
  - Supports: integer, text, html, json, select, multiselect, belongs_to relationships
  - MySQL views for JSON aggregation (performance optimization)

- **Entity Types**: Product, Category, Brand (extensible)
- **Attributes**: Fully configurable with validation rules, sync settings, approval workflows
- **Attribute Sections**: UI organization into collapsible groups

#### Magento Integration
- **MagentoApiClient**: REST API client with retry logic, pagination
- **ProductSync**: Bidirectional sync with conflict detection
- **AttributeOptionSync**: Select/multiselect option synchronization
- **SyncRun/SyncResult**: Audit trail for all sync operations

#### AI Pipeline System
- **Modular Architecture**: Source modules, processor modules
- **Available Modules**:
  - `AttributesSourceModule` - Load attribute values as inputs
  - `AiPromptProcessorModule` - OpenAI integration with JSON schemas
  - `CalculationProcessorModule` - JavaScript execution
- **Evaluation System**: Test cases with expected vs actual outputs
- **Change Detection**: Input hash-based skip logic

#### User Management
- **Spatie Laravel Permission**: Roles, permissions
- **User CRUD**: In Filament admin
- **User Preferences**: Stored per-user settings

#### Technical Stack
| Component | Version |
|-----------|---------|
| PHP | 8.2+ |
| Laravel | 12.x |
| Filament | 4.0 |
| MySQL | 8.x (required for window functions, JSON aggregation) |
| Tailwind CSS | 4.1 |
| Alpine.js | 3.4 |
| Vite | 7.0 |

---

## 2. What Needs To Be Built

### Panel 1: Supply Insights Dashboard

**Purpose**: Provide brand/supplier partners with insights into their product performance in exchange for additional rebates.

**Key Features**:
- Brand-scoped data views (suppliers see only their brands)
- Sales analytics (revenue, competitor comparisons)
- Market share visualization (category tree with percentages)
- Customer engagement metrics (reorder %, frequency, promo intensity)
- Stock and supply tracking (sell-in, sell-out, closing stock)
- Purchase order management with drill-down

**Data Source**: BigQuery (`sh_output` tables)

**Access Levels**:
- **Basic**: Limited metrics (free tier)
- **Premium**: Full analytics suite (paid tier)

**Premium Lock/Blur**: Grey out unavailable features with upgrade CTA

### Panel 2: Pricing Tool

**Purpose**: Analyze scraped competitor prices for pricing intelligence.

**Key Features**:
- Competitor price tracking
- Price history visualization
- Price alerts/notifications
- Pricing recommendations
- Margin analysis

**Data Source**: Scraped price data (likely BigQuery or dedicated tables)

### Panel 3: PIM (Existing - Enhanced)

**Purpose**: Manage product information with AI-assisted attribute generation.

**Enhancements Needed**:
- Integration with Supply Insights (brand product info collection)
- Potential data sharing with Pricing tool

---

## 3. Architectural Decision: Single App vs Multiple Apps

### Option A: Multiple Filament Panels in One Laravel App (RECOMMENDED)

```
/spim
├── app/
│   ├── Filament/
│   │   ├── PimPanel/           # Panel 1: PIM
│   │   │   ├── Resources/
│   │   │   └── Pages/
│   │   ├── SupplyPanel/        # Panel 2: Supply Insights
│   │   │   ├── Resources/
│   │   │   └── Pages/
│   │   └── PricingPanel/       # Panel 3: Pricing
│   │       ├── Resources/
│   │       └── Pages/
│   ├── Models/                 # Shared domain models
│   ├── Services/               # Shared business logic
│   └── ...
├── config/
│   └── filament.php            # Panel configuration
└── routes/
    └── web.php                 # Panel routing
```

**Advantages**:
- Single codebase, single deployment
- Shared domain layer (models, services)
- No migration collisions (one migration set)
- Easier code sharing between panels
- Single database connection
- Unified user management

**Disadvantages**:
- Requires discipline to maintain separation
- Larger codebase
- All panels down if app fails

### Option B: Separate Laravel Apps with Shared Database

```
/silvertree-apps
├── spim/                       # PIM app
├── supply-insights/            # Supply Insights app
├── pricing/                    # Pricing app
└── shared-models/              # Shared Eloquent models package
```

**Advantages**:
- Complete code isolation
- Independent deployments
- Smaller, focused codebases

**Disadvantages**:
- Migration coordination nightmare
- Code duplication or package management complexity
- Multiple deployment pipelines
- Harder to share business logic
- Risk of table name collisions

### Recommendation: Option A (Single App, Multiple Panels)

Filament 3/4 is designed for this exact use case. Each panel can have:
- Unique URL prefix (`/pim`, `/supply`, `/pricing`)
- Separate authentication guards
- Different authorization policies
- Independent menu structures
- Distinct themes/branding
- Panel-specific middleware

---

## 4. Proposed Panel Architecture

### URL Structure
```
https://app.example.com/pim/*        # PIM Panel
https://app.example.com/supply/*     # Supply Insights Panel
https://app.example.com/pricing/*    # Pricing Panel
```

### Panel Configuration

Each panel defined in `app/Providers/Filament/`:

```php
// PimPanelProvider.php
class PimPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('pim')
            ->path('pim')
            ->login()
            ->colors(['primary' => '#006654'])
            ->brandLogo(asset('images/ftn-logo.svg'))
            ->navigation(...)
            ->resources([...])
            ->pages([...])
            ->middleware([...])
            ->authMiddleware([Authenticate::class]);
    }
}
```

### User Types and Roles

| User Type | Panel Access | Description |
|-----------|-------------|-------------|
| Admin | PIM, Supply, Pricing | Full access to all panels |
| PIM Editor | PIM only | Manage products and attributes |
| Supplier | Supply only | View brand-scoped insights |
| Pricing Analyst | Pricing only | View pricing data |

### Database Schema Strategy

**Shared Tables** (all panels):
- `users`, `roles`, `permissions`
- `brands` (read-only cache from BigQuery)
- `entity_types`, `entities`, `attributes`
- `eav_*` tables

**PIM-Specific Tables**:
- `pipelines`, `pipeline_modules`, `pipeline_runs`, `pipeline_evals`
- `sync_runs`, `sync_results`

**Supply Insights Tables** (new):
- `supplier_brand_access` (user ↔ brand)
- `brand_competitors` (brand ↔ competitor brands)
- BigQuery views/cached tables for analytics

**Pricing Tables** (new):
- `price_scrapes` (competitor price data)
- `price_alerts` (user-configured alerts)
- `pricing_rules` (recommendation rules)

### BigQuery Integration

The existing architecture has **no BigQuery connector** yet. This needs to be added:

```php
// app/Services/BigQueryClient.php
class BigQueryClient
{
    private BigQueryClient $client;

    public function query(string $sql, array $params = []): array
    {
        // Execute query and return results
    }

    public function getBrands(int $companyId): Collection
    {
        // SELECT DISTINCT brand FROM sh_output.dim_product WHERE company_id = ?
    }

    public function getSalesByBrand(int $companyId, string $brand, DateRange $range): array
    {
        // Sales analytics query
    }
}
```

**Environment Configuration**:
```env
COMPANY_ID=3                    # FtN=3, UCOOK=5, Pet Heaven=9
GOOGLE_APPLICATION_CREDENTIALS=/path/to/service-account.json
BIGQUERY_PROJECT_ID=your-project
BIGQUERY_DATASET=sh_output
```

---

## 5. Module Ownership

### Clear Table Ownership

| Module | Owned Tables | Shared Access |
|--------|-------------|---------------|
| PIM | `pipelines`, `pipeline_*`, `sync_*` | `entities`, `attributes`, `eav_*` |
| Supply | `supplier_*`, `brand_competitors` | `brands`, `entities` (read-only) |
| Pricing | `price_*`, `pricing_*` | `entities` (read-only) |
| Shared | `users`, `roles`, `brands`, `entities`, `attributes` | - |

### Service Ownership

| Service | Module | Purpose |
|---------|--------|---------|
| `PipelineExecutionService` | PIM | Execute AI pipelines |
| `MagentoApiClient` | PIM | Magento sync |
| `BigQueryClient` | Shared | Analytics queries |
| `SupplyAnalyticsService` | Supply | Brand analytics |
| `PriceScrapingService` | Pricing | Price data management |

---

## 6. Implementation Phases

### Phase 1: Foundation (Week 1-2)
1. Refactor existing Filament setup for multi-panel support
2. Move current resources to `PimPanel` namespace
3. Create panel providers for PIM, Supply, Pricing
4. Implement user type/role structure
5. Test existing PIM functionality in new structure

### Phase 2: BigQuery Integration (Week 2-3)
1. Install Google Cloud BigQuery PHP package
2. Create `BigQueryClient` service
3. Implement brand sync from BigQuery
4. Add `COMPANY_ID` environment configuration
5. Create brand model and caching logic

### Phase 3: Supply Insights MVP (Week 3-5)
1. Create Supply panel with basic navigation
2. Implement brand dropdown (supplier brand scope)
3. Build sales overview page (KPI tiles + chart)
4. Build products table with BigQuery data
5. Implement premium feature blur/lock

### Phase 4: Full Supply Insights (Week 5-8)
1. Market share category tree
2. Customer engagement metrics
3. Stock and supply tables
4. Purchase order management
5. Admin brand management

### Phase 5: Pricing Tool (Week 8-10)
1. Price scraping data model
2. Competitor price tracking UI
3. Price history charts
4. Alert system

### Phase 6: Integration & Polish (Week 10-12)
1. Cross-panel data sharing (Supply → PIM)
2. Performance optimization
3. Testing and documentation
4. Production deployment

---

## 7. Key Technical Decisions

### Chart Library
**Recommendation**: Start with Filament Charts (Chart.js based), migrate to Apache ECharts if limitations appear.

Chart data should be loaded via AJAX endpoints:
```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/supply/charts/sales', [SalesChartController::class, 'data']);
});
```

### Dynamic Tables
**Recommendation**: Filament Tables with server-side pagination and filtering. Already implemented in PIM.

### Premium Feature Blur
**Implementation**:
```php
// In Blade view
@can('view-premium-features')
    <x-chart :data="$data" />
@else
    <x-premium-locked-placeholder feature="Sales Analytics" />
@endcan
```

### Caching Strategy
- BigQuery results cached for 15 minutes (configurable)
- Brand list synced on-demand via admin action
- User preferences cached per-session

---

## 8. Risk Mitigation

| Risk | Mitigation |
|------|------------|
| BigQuery cost overruns | Query caching, result size limits, scheduled pre-aggregation |
| Panel coupling | Strict namespace separation, code reviews |
| Migration conflicts | Single migration timeline, clear ownership |
| Performance degradation | Lazy loading, pagination, query optimization |
| Security (multi-tenant) | Policy-based authorization, brand scope enforcement |

---

## 9. Success Criteria

1. **PIM continues working** throughout migration
2. **Clear URL separation** for each panel
3. **User roles** correctly restrict panel access
4. **BigQuery integration** operational with caching
5. **Supply Insights** delivers core metrics to suppliers
6. **Premium lock** prevents unauthorized feature access
7. **Documentation** updated for all panels

---

## 10. Next Steps

1. **Review this document** with stakeholders
2. **Approve architectural approach** (single app, multiple panels)
3. **Set up BigQuery credentials** locally
4. **Begin Phase 1** refactoring
5. **Create detailed PRD** for Supply Insights
6. **Define test strategy** for multi-panel architecture

---

## Appendix A: Directory Structure (Target State)

```
/spim
├── app/
│   ├── Console/
│   ├── Contracts/
│   ├── Filament/
│   │   ├── Shared/                      # Shared widgets, components
│   │   │   ├── Widgets/
│   │   │   └── Components/
│   │   ├── PimPanel/                    # PIM Panel
│   │   │   ├── Resources/
│   │   │   │   ├── ProductResource.php
│   │   │   │   ├── CategoryResource.php
│   │   │   │   ├── AttributeResource.php
│   │   │   │   ├── PipelineResource.php
│   │   │   │   └── UserResource.php
│   │   │   └── Pages/
│   │   │       ├── Dashboard.php
│   │   │       └── MagentoSync.php
│   │   ├── SupplyPanel/                 # Supply Insights Panel
│   │   │   ├── Resources/
│   │   │   │   ├── BrandResource.php
│   │   │   │   └── PurchaseOrderResource.php
│   │   │   ├── Pages/
│   │   │   │   ├── Dashboard.php
│   │   │   │   ├── Sales.php
│   │   │   │   ├── MarketShare.php
│   │   │   │   ├── Customers.php
│   │   │   │   └── StockSupply.php
│   │   │   └── Widgets/
│   │   │       └── SalesKpiWidget.php
│   │   └── PricingPanel/                # Pricing Panel
│   │       ├── Resources/
│   │       │   └── PriceTrackResource.php
│   │       └── Pages/
│   │           ├── Dashboard.php
│   │           └── CompetitorAnalysis.php
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           └── ChartDataController.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Brand.php                    # NEW
│   │   ├── BrandCompetitor.php          # NEW
│   │   ├── SupplierBrandAccess.php      # NEW
│   │   ├── PriceScrape.php              # NEW
│   │   └── ... (existing models)
│   ├── Policies/
│   │   └── BrandPolicy.php              # NEW
│   ├── Providers/
│   │   ├── Filament/
│   │   │   ├── PimPanelProvider.php     # Refactored
│   │   │   ├── SupplyPanelProvider.php  # NEW
│   │   │   └── PricingPanelProvider.php # NEW
│   │   └── AppServiceProvider.php
│   └── Services/
│       ├── BigQueryClient.php           # NEW
│       ├── SupplyAnalyticsService.php   # NEW
│       ├── PriceScrapingService.php     # NEW
│       └── ... (existing services)
├── config/
│   ├── bigquery.php                     # NEW
│   └── filament.php                     # Updated
├── database/
│   └── migrations/
│       ├── 2025_XX_XX_create_brands_table.php      # NEW
│       ├── 2025_XX_XX_create_supply_tables.php     # NEW
│       └── 2025_XX_XX_create_pricing_tables.php    # NEW
├── docs/
│   ├── pim/                             # PIM-specific docs
│   ├── supply/                          # Supply-specific docs
│   ├── pricing/                         # Pricing-specific docs
│   └── architecture.md                  # Overall architecture
└── .env
    # New entries:
    # COMPANY_ID=3
    # GOOGLE_APPLICATION_CREDENTIALS=...
    # BIGQUERY_PROJECT_ID=...
```

---

## Appendix B: User Type Matrix

| Permission | Admin | PIM Editor | Supplier Basic | Supplier Premium | Pricing Analyst |
|------------|-------|------------|----------------|------------------|-----------------|
| Access PIM Panel | Yes | Yes | No | No | No |
| Access Supply Panel | Yes | No | Yes | Yes | No |
| Access Pricing Panel | Yes | No | No | No | Yes |
| Manage Users | Yes | No | No | No | No |
| Manage Brands | Yes | No | No | No | No |
| View Premium Features | Yes | Yes | No | Yes | Yes |
| Edit Products | Yes | Yes | No | No | No |
| Run Pipelines | Yes | Yes | No | No | No |
| View Own Brand Data | Yes | Yes | Yes | Yes | No |
| View Competitor Names | Yes | Yes | No | No | No |
