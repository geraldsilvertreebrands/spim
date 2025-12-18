# Test Strategy Document
## Silvertree Multi-Panel Platform

**Document Version**: 1.0
**Last Updated**: December 2025
**Author**: Engineering Team

---

## 1. Introduction

### 1.1 Purpose
This document defines the testing strategy for the Silvertree multi-panel platform, ensuring quality across:
- **PIM Panel** (existing functionality preservation)
- **Supply Insights Panel** (new development)
- **Pricing Panel** (new development)
- **Shared infrastructure** (authentication, authorization, BigQuery)

### 1.2 Scope
| In Scope | Out of Scope |
|----------|--------------|
| Unit tests | Load testing (separate phase) |
| Integration tests | Penetration testing (separate phase) |
| Feature tests | Mobile device testing |
| API tests | Browser compatibility (IE11) |
| Authorization tests | Performance benchmarking |
| BigQuery mock tests | |

### 1.3 Test Environment
```
Test Database: MySQL 8.x (spim_test database)
Test Runner: PHPUnit 11.5+
PHP Version: 8.2+
Laravel Version: 12.x
Filament Version: 4.0
```

---

## 2. Testing Pyramid

```
                    /\
                   /  \
                  / E2E \           <- Minimal (Critical paths only)
                 /______\
                /        \
               / Feature  \          <- Moderate (User journeys)
              /____________\
             /              \
            / Integration    \       <- Substantial (Service interactions)
           /__________________\
          /                    \
         /        Unit          \    <- Foundation (Business logic)
        /________________________\
```

### 2.1 Test Distribution Targets
| Level | Target Coverage | Description |
|-------|----------------|-------------|
| Unit | 80%+ | Individual classes, methods, pure functions |
| Integration | 60%+ | Service interactions, database queries |
| Feature | Critical paths | User workflows via HTTP |
| E2E | Smoke tests | Critical panel navigation |

---

## 3. Test Categories

### 3.1 Unit Tests

#### 3.1.1 Model Tests
Test Eloquent models, relationships, scopes, and accessors.

**Existing Tests to Preserve** (`tests/Unit/Models/`):
```
EntityTest.php          - EAV loading, attribute access
AttributeTest.php       - Validation rules, configuration
PipelineTest.php        - Version bumping, stats
PipelineRunTest.php     - Status transitions, duration
SyncRunTest.php         - Status management, counters
```

**New Tests Required**:
```
BrandTest.php           - Brand model, relationships
BrandCompetitorTest.php - Competitor mappings
SupplierBrandAccessTest.php - User-brand associations
PriceScrapeTest.php     - Price scrape model
```

#### 3.1.2 Service Tests
Test business logic services in isolation.

**Existing Tests to Preserve**:
```
EavWriterTest.php
PipelineExecutionServiceTest.php
PipelineModuleRegistryTest.php
```

**New Tests Required**:
```
BigQueryClientTest.php          - Query building, caching
SupplyAnalyticsServiceTest.php  - Analytics calculations
BrandSyncServiceTest.php        - BigQuery sync logic
PriceScrapingServiceTest.php    - Price data management
```

#### 3.1.3 Pipeline Module Tests
Test individual pipeline modules.

```
AttributesSourceModuleTest.php
AiPromptProcessorModuleTest.php
CalculationProcessorModuleTest.php
```

### 3.2 Integration Tests

#### 3.2.1 Database Integration
Test actual database interactions with the test database.

```php
// tests/Integration/EavWriterIntegrationTest.php
class EavWriterIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_versioned_creates_record(): void
    {
        $entity = Entity::factory()->create();
        $attribute = Attribute::factory()->create();

        $writer = app(EavWriter::class);
        $writer->upsertVersioned($entity->id, $attribute->id, 'test value');

        $this->assertDatabaseHas('eav_versioned', [
            'entity_id' => $entity->id,
            'attribute_id' => $attribute->id,
            'value_current' => 'test value',
        ]);
    }
}
```

#### 3.2.2 BigQuery Integration (Mocked)
Test BigQuery service with mocked responses.

```php
// tests/Integration/BigQueryClientTest.php
class BigQueryClientTest extends TestCase
{
    public function test_get_brands_returns_collection(): void
    {
        $mockBigQuery = $this->mock(BigQueryClient::class);
        $mockBigQuery->shouldReceive('query')
            ->andReturn([
                ['brand' => 'Brand A'],
                ['brand' => 'Brand B'],
            ]);

        $result = $mockBigQuery->getBrands(companyId: 3);

        $this->assertCount(2, $result);
    }
}
```

#### 3.2.3 Sync Integration
Test sync services with actual database.

```php
// tests/Integration/Sync/ProductSyncIntegrationTest.php
class ProductSyncIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pull_creates_new_products(): void
    {
        // Mock Magento API response
        $this->mockMagentoProducts([
            ['sku' => 'TEST-001', 'name' => 'Test Product'],
        ]);

        $sync = app(ProductSync::class);
        $run = SyncRun::factory()->create();

        $sync->execute($run);

        $this->assertDatabaseHas('entities', [
            'entity_id' => 'TEST-001',
        ]);
    }
}
```

### 3.3 Feature Tests

#### 3.3.1 Authentication Tests
```php
// tests/Feature/Auth/AuthenticationTest.php
class AuthenticationTest extends TestCase
{
    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/pim/login');
        $response->assertStatus(200);
    }

    public function test_users_can_authenticate(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/pim/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/pim');
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/pim/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }
}
```

#### 3.3.2 Panel Access Tests
```php
// tests/Feature/Panels/PanelAccessTest.php
class PanelAccessTest extends TestCase
{
    public function test_admin_can_access_all_panels(): void
    {
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)
            ->get('/pim')
            ->assertStatus(200);

        $this->actingAs($admin)
            ->get('/supply')
            ->assertStatus(200);

        $this->actingAs($admin)
            ->get('/pricing')
            ->assertStatus(200);
    }

    public function test_supplier_cannot_access_pim_panel(): void
    {
        $supplier = User::factory()->create()->assignRole('supplier-basic');

        $this->actingAs($supplier)
            ->get('/pim')
            ->assertStatus(403);
    }

    public function test_supplier_can_access_supply_panel(): void
    {
        $supplier = User::factory()->create()->assignRole('supplier-basic');

        $this->actingAs($supplier)
            ->get('/supply')
            ->assertStatus(200);
    }
}
```

#### 3.3.3 Brand Scope Tests
```php
// tests/Feature/Supply/BrandScopeTest.php
class BrandScopeTest extends TestCase
{
    public function test_supplier_only_sees_assigned_brands(): void
    {
        $brand1 = Brand::factory()->create(['name' => 'My Brand']);
        $brand2 = Brand::factory()->create(['name' => 'Other Brand']);

        $supplier = User::factory()->create()->assignRole('supplier-basic');
        SupplierBrandAccess::create([
            'user_id' => $supplier->id,
            'brand_id' => $brand1->id,
        ]);

        $response = $this->actingAs($supplier)
            ->get('/supply/overview');

        $response->assertSee('My Brand');
        $response->assertDontSee('Other Brand');
    }
}
```

#### 3.3.4 Premium Feature Tests
```php
// tests/Feature/Supply/PremiumFeaturesTest.php
class PremiumFeaturesTest extends TestCase
{
    public function test_basic_tier_sees_locked_premium_features(): void
    {
        $supplier = User::factory()->create()->assignRole('supplier-basic');
        $brand = Brand::factory()->create(['access_level' => 'basic']);
        $supplier->brands()->attach($brand);

        $response = $this->actingAs($supplier)
            ->get('/supply/forecasting');

        $response->assertSee('Upgrade to Premium');
        $response->assertSee('locked');
    }

    public function test_premium_tier_sees_premium_features(): void
    {
        $supplier = User::factory()->create()->assignRole('supplier-premium');
        $brand = Brand::factory()->create(['access_level' => 'premium']);
        $supplier->brands()->attach($brand);

        $response = $this->actingAs($supplier)
            ->get('/supply/forecasting');

        $response->assertDontSee('Upgrade to Premium');
        $response->assertStatus(200);
    }
}
```

#### 3.3.5 API Tests
```php
// tests/Feature/Api/ChartDataTest.php
class ChartDataTest extends TestCase
{
    public function test_sales_trend_api_returns_valid_structure(): void
    {
        $supplier = User::factory()->create()->assignRole('supplier-basic');
        $brand = Brand::factory()->create();
        $supplier->brands()->attach($brand);

        $response = $this->actingAs($supplier, 'sanctum')
            ->getJson('/api/supply/charts/sales-trend', [
                'brand_id' => $brand->id,
                'period' => '12m',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'labels',
                'datasets' => [
                    '*' => ['label', 'data'],
                ],
            ]);
    }

    public function test_sales_trend_api_respects_brand_scope(): void
    {
        $supplier = User::factory()->create()->assignRole('supplier-basic');
        $brand = Brand::factory()->create();
        $otherBrand = Brand::factory()->create();
        $supplier->brands()->attach($brand);

        $response = $this->actingAs($supplier, 'sanctum')
            ->getJson('/api/supply/charts/sales-trend', [
                'brand_id' => $otherBrand->id,  // Not assigned
            ]);

        $response->assertStatus(403);
    }
}
```

### 3.4 Filament Resource Tests

#### 3.4.1 Resource CRUD Tests
```php
// tests/Feature/Filament/PimPanel/ProductResourceTest.php
use Livewire\Livewire;

class ProductResourceTest extends TestCase
{
    public function test_can_list_products(): void
    {
        $user = User::factory()->create()->assignRole('admin');
        Entity::factory()->count(5)->create(['entity_type_id' => 1]);

        Livewire::actingAs($user)
            ->test(ProductResource\Pages\ListProducts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(Entity::all());
    }

    public function test_can_create_product(): void
    {
        $user = User::factory()->create()->assignRole('admin');

        Livewire::actingAs($user)
            ->test(ProductResource\Pages\CreateProduct::class)
            ->fillForm([
                'entity_id' => 'NEW-SKU-001',
                // ... other fields
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('entities', [
            'entity_id' => 'NEW-SKU-001',
        ]);
    }
}
```

#### 3.4.2 Widget Tests
```php
// tests/Feature/Filament/SupplyPanel/Widgets/SalesKpiWidgetTest.php
class SalesKpiWidgetTest extends TestCase
{
    public function test_widget_displays_kpis(): void
    {
        $supplier = User::factory()->create()->assignRole('supplier-basic');
        $brand = Brand::factory()->create();
        $supplier->brands()->attach($brand);

        // Mock BigQuery response
        $this->mockBigQuerySalesData($brand->id);

        Livewire::actingAs($supplier)
            ->test(SalesKpiWidget::class, ['brandId' => $brand->id])
            ->assertSee('Revenue')
            ->assertSee('Orders')
            ->assertSee('AOV');
    }
}
```

### 3.5 Policy Tests
```php
// tests/Unit/Policies/BrandPolicyTest.php
class BrandPolicyTest extends TestCase
{
    public function test_admin_can_view_any_brand(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $brand = Brand::factory()->create();

        $this->assertTrue($admin->can('view', $brand));
    }

    public function test_supplier_can_only_view_assigned_brands(): void
    {
        $supplier = User::factory()->create()->assignRole('supplier-basic');
        $assignedBrand = Brand::factory()->create();
        $otherBrand = Brand::factory()->create();

        $supplier->brands()->attach($assignedBrand);

        $this->assertTrue($supplier->can('view', $assignedBrand));
        $this->assertFalse($supplier->can('view', $otherBrand));
    }

    public function test_supplier_cannot_update_brand(): void
    {
        $supplier = User::factory()->create()->assignRole('supplier-basic');
        $brand = Brand::factory()->create();
        $supplier->brands()->attach($brand);

        $this->assertFalse($supplier->can('update', $brand));
    }
}
```

---

## 4. Test Data Strategy

### 4.1 Factories

**Existing Factories** (`database/factories/`):
```
UserFactory.php
EntityFactory.php
EntityTypeFactory.php
AttributeFactory.php
PipelineFactory.php
PipelineRunFactory.php
SyncRunFactory.php
```

**New Factories Required**:
```php
// database/factories/BrandFactory.php
class BrandFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'company_id' => 3,
            'access_level' => 'basic',
            'synced_at' => now(),
        ];
    }

    public function premium(): static
    {
        return $this->state(['access_level' => 'premium']);
    }
}

// database/factories/SupplierBrandAccessFactory.php
class SupplierBrandAccessFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'brand_id' => Brand::factory(),
        ];
    }
}

// database/factories/PriceScrapeFactory.php
class PriceScrapeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Entity::factory(),
            'competitor_name' => $this->faker->company(),
            'competitor_url' => $this->faker->url(),
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => 'ZAR',
            'scraped_at' => $this->faker->dateTimeBetween('-30 days'),
        ];
    }
}
```

### 4.2 Seeders

```php
// database/seeders/TestDataSeeder.php
class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'pim-editor']);
        Role::create(['name' => 'supplier-basic']);
        Role::create(['name' => 'supplier-premium']);
        Role::create(['name' => 'pricing-analyst']);

        // Create test users for each role
        User::factory()->create(['email' => 'admin@test.com'])
            ->assignRole('admin');

        User::factory()->create(['email' => 'supplier@test.com'])
            ->assignRole('supplier-basic');

        // Create test brands
        $brand = Brand::factory()->create(['name' => 'Test Brand']);

        // Assign brand to supplier
        SupplierBrandAccess::create([
            'user_id' => User::where('email', 'supplier@test.com')->first()->id,
            'brand_id' => $brand->id,
        ]);
    }
}
```

### 4.3 BigQuery Mock Data

```php
// tests/Support/BigQueryMock.php
trait BigQueryMock
{
    protected function mockBigQuerySalesData(int $brandId): void
    {
        $mock = $this->mock(BigQueryClient::class);

        $mock->shouldReceive('getSalesTrend')
            ->with($brandId, Mockery::any())
            ->andReturn([
                'labels' => ['Jan', 'Feb', 'Mar'],
                'datasets' => [
                    ['label' => 'Revenue', 'data' => [10000, 12000, 11500]],
                ],
            ]);

        $mock->shouldReceive('getKpis')
            ->with($brandId, Mockery::any())
            ->andReturn([
                'revenue' => 33500,
                'orders' => 450,
                'aov' => 74.44,
                'units' => 1200,
            ]);
    }

    protected function mockBigQueryBrands(): void
    {
        $mock = $this->mock(BigQueryClient::class);

        $mock->shouldReceive('getBrands')
            ->andReturn(collect([
                ['brand' => 'Brand A'],
                ['brand' => 'Brand B'],
                ['brand' => 'Brand C'],
            ]));
    }
}
```

---

## 5. Regression Testing

### 5.1 Critical Path Tests

Tests that MUST pass before any deployment:

```php
// tests/Feature/RegressionTest.php
class RegressionTest extends TestCase
{
    /** @group critical */
    public function test_pim_login_works(): void { ... }

    /** @group critical */
    public function test_pim_product_list_loads(): void { ... }

    /** @group critical */
    public function test_pim_product_edit_works(): void { ... }

    /** @group critical */
    public function test_magento_sync_runs(): void { ... }

    /** @group critical */
    public function test_pipeline_execution_works(): void { ... }

    /** @group critical */
    public function test_supply_login_works(): void { ... }

    /** @group critical */
    public function test_supply_dashboard_loads(): void { ... }
}
```

Run critical tests:
```bash
php artisan test --group=critical
```

### 5.2 Migration Regression Tests

Ensure existing PIM functionality works after restructure:

```php
// tests/Feature/Migration/PimMigrationRegressionTest.php
class PimMigrationRegressionTest extends TestCase
{
    /** @group migration */
    public function test_existing_entity_types_preserved(): void { ... }

    /** @group migration */
    public function test_existing_attributes_preserved(): void { ... }

    /** @group migration */
    public function test_existing_entities_preserved(): void { ... }

    /** @group migration */
    public function test_existing_eav_data_preserved(): void { ... }

    /** @group migration */
    public function test_existing_pipelines_preserved(): void { ... }

    /** @group migration */
    public function test_existing_sync_history_preserved(): void { ... }
}
```

---

## 6. Test Automation

### 6.1 CI/CD Pipeline

```yaml
# .github/workflows/tests.yml
name: Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  tests:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: spim_test
          MYSQL_ROOT_PASSWORD: password
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, pdo, pdo_mysql
          coverage: xdebug

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Run PHPStan
        run: composer run analyse

      - name: Run tests
        run: php artisan test --parallel --coverage-clover=coverage.xml
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: spim_test
          DB_USERNAME: root
          DB_PASSWORD: password

      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
```

### 6.2 Pre-commit Hooks

```bash
# .husky/pre-commit
#!/bin/sh

# Run PHP CS Fixer
composer run format:check

# Run PHPStan
composer run analyse

# Run critical tests
php artisan test --group=critical
```

### 6.3 Test Commands

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run in parallel
php artisan test --parallel

# Run specific group
php artisan test --group=critical

# Run specific file
php artisan test tests/Feature/Supply/BrandScopeTest.php

# Run specific method
php artisan test --filter=test_supplier_only_sees_assigned_brands
```

---

## 7. Test Coverage Goals

### 7.1 Coverage Targets by Module

| Module | Line Coverage | Branch Coverage |
|--------|--------------|-----------------|
| Models | 90% | 85% |
| Services | 85% | 80% |
| Policies | 95% | 90% |
| Controllers | 80% | 75% |
| Filament Resources | 70% | 65% |

### 7.2 Coverage Enforcement

```xml
<!-- phpunit.xml -->
<coverage>
    <report>
        <clover outputFile="coverage.xml"/>
        <html outputDirectory="coverage-html"/>
    </report>
    <include>
        <directory suffix=".php">app</directory>
    </include>
    <exclude>
        <directory>app/Console</directory>
        <directory>app/Providers</directory>
    </exclude>
</coverage>
```

---

## 8. Test Documentation

### 8.1 Test Naming Convention

```
test_[action]_[condition]_[expected_result]
```

Examples:
- `test_supplier_cannot_access_pim_panel`
- `test_admin_can_update_any_brand`
- `test_sales_api_returns_valid_json_structure`
- `test_premium_features_locked_for_basic_tier`

### 8.2 Test Organization

```
tests/
├── Unit/
│   ├── Models/
│   │   ├── BrandTest.php
│   │   ├── EntityTest.php
│   │   └── ...
│   ├── Services/
│   │   ├── BigQueryClientTest.php
│   │   └── ...
│   └── Policies/
│       └── BrandPolicyTest.php
├── Integration/
│   ├── BigQueryIntegrationTest.php
│   ├── EavWriterIntegrationTest.php
│   └── Sync/
│       └── ProductSyncIntegrationTest.php
├── Feature/
│   ├── Auth/
│   │   └── AuthenticationTest.php
│   ├── Panels/
│   │   └── PanelAccessTest.php
│   ├── Filament/
│   │   ├── PimPanel/
│   │   │   └── ProductResourceTest.php
│   │   └── SupplyPanel/
│   │       └── BrandScopeTest.php
│   ├── Api/
│   │   └── ChartDataTest.php
│   └── Migration/
│       └── PimMigrationRegressionTest.php
└── Support/
    ├── BigQueryMock.php
    └── TestCase.php
```

---

## 9. Quality Gates

### 9.1 Definition of Done (Testing)
- [ ] Unit tests written for new business logic
- [ ] Integration tests for service interactions
- [ ] Feature tests for user-facing workflows
- [ ] Policy tests for authorization rules
- [ ] All tests passing locally
- [ ] CI pipeline green
- [ ] No decrease in coverage percentage
- [ ] Critical tests passing

### 9.2 Pull Request Requirements
1. All new code has corresponding tests
2. No flaky tests introduced
3. PHPStan passes with no errors
4. Code formatting passes (Pint)
5. Coverage percentage maintained or improved

---

## 10. Risk Mitigation

### 10.1 Testing Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| BigQuery rate limits in tests | Test failures | Use mocked BigQuery client |
| Database state pollution | Flaky tests | Use `RefreshDatabase` trait |
| Slow tests | Developer friction | Run fast tests first, parallel execution |
| External API failures | Unstable CI | Mock all external services |
| Data seeder drift | Inconsistent results | Version seed data, use factories |

### 10.2 Test Debt Management
- Review test coverage weekly
- Address flaky tests immediately
- Refactor complex test setups
- Document testing patterns
- Regular test cleanup sprints

---

## 11. Appendices

### Appendix A: Test Helper Classes

```php
// tests/Support/TestCase.php
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;
    use BigQueryMock;

    protected function setUpPimUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('pim-editor');
        return $user;
    }

    protected function setUpSupplierUser(Brand $brand = null): User
    {
        $user = User::factory()->create();
        $user->assignRole('supplier-basic');

        if ($brand) {
            SupplierBrandAccess::create([
                'user_id' => $user->id,
                'brand_id' => $brand->id,
            ]);
        }

        return $user;
    }
}
```

### Appendix B: Common Test Patterns

```php
// Testing Livewire components
Livewire::test(Component::class)
    ->assertSee('Expected text')
    ->call('methodName')
    ->assertEmitted('event-name');

// Testing API endpoints
$response = $this->actingAs($user, 'sanctum')
    ->getJson('/api/endpoint')
    ->assertStatus(200)
    ->assertJsonStructure(['key']);

// Testing database state
$this->assertDatabaseHas('table', ['column' => 'value']);
$this->assertDatabaseMissing('table', ['column' => 'value']);
$this->assertSoftDeleted('table', ['id' => 1]);

// Testing authorization
$this->assertTrue($user->can('action', $model));
$this->assertFalse($user->cannot('action', $model));
```

### Appendix C: Useful Testing Commands

```bash
# Generate test coverage report
php artisan test --coverage-html=coverage-report

# Run tests matching pattern
php artisan test --filter="Supply"

# Run tests in specific directory
php artisan test tests/Feature/Supply

# List all available test groups
php artisan test --list-groups

# Profile slow tests
php artisan test --profile

# Stop on first failure
php artisan test --stop-on-failure
```
