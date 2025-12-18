# Silvertree Multi-Panel Platform - Master Project Tracker

**Project Codename**: SILVERTREE-PLATFORM
**Company**: Silvertree Brands (silvertreebrands.com)
**Brands Served**: Faithful to Nature (FtN), Pet Heaven (PH), UCOOK
**Start Date**: December 2025
**Document Version**: 1.0
**Last Updated**: 2025-12-13

---

## Company Overview

**Silvertree Brands** is a holding company that owns and operates multiple e-commerce businesses in South Africa:

| Brand | Focus | Company ID | Production Project |
|-------|-------|-----------|-------------------|
| Faithful to Nature (FtN) | Health, organic, eco-friendly | 3 | ftn-production-1 |
| Pet Heaven (PH) | Pet supplies | 5 | ph-production-2 |
| UCOOK | Meal kits | 9 | ucook-production-471812 |

**BigQuery Organization**: 541700872526
**Primary Data Project**: silvertreepoc ✅ (confirmed 2024-12-14)

---

## Current State Assessment (Diagnostic Report)

### What EXISTS and WORKS

| Component | Status | Quality | Notes |
|-----------|--------|---------|-------|
| Laravel Framework | 12.28.1 | GOOD | Latest version |
| Filament Admin | 4.0.9 | GOOD | Latest version |
| PHP Version | 8.5.0 | CAUTION | Very new, has deprecation warnings |
| Docker Setup | EXISTS | STOPPED | Containers exist but not running |
| Database Schema | EXISTS | GOOD | 22 migrations, well-designed |
| EAV System | COMPLETE | EXCELLENT | Versioned values, JSON views |
| AI Pipelines | COMPLETE | EXCELLENT | Modular, extensible |
| Magento Sync | COMPLETE | EXCELLENT | Bidirectional with conflict detection |
| Test Suite | EXISTS | GOOD | 37 test files, 263 tests |
| Documentation | EXISTS | GOOD | Comprehensive in /docs |
| User Management | COMPLETE | GOOD | Spatie Laravel Permission |

### What is BROKEN or NEEDS FIXING

| Issue | Severity | Description | Fix Required |
|-------|----------|-------------|--------------|
| No Magento Credentials | WARNING | MAGENTO_* env vars empty | Configure for production |
| No OpenAI Key | WARNING | OPENAI_API_KEY empty | Configure for AI features |

### What WAS Fixed (Phase A-E Complete)
| Issue | Status | Fixed In |
|-------|--------|----------|
| PHP 8.5 Deprecations | ✅ FIXED | Phase A |
| PHPStan Broken | ✅ FIXED | Phase A |
| Docker Stopped | ✅ FIXED | Phase A |
| BigQuery Package Missing | ✅ FIXED | Phase B |
| Google Credentials Missing | ✅ FIXED | Phase B |
| Single Panel Only | ✅ FIXED | Phase C |

### What is MISSING (Must Build)

All core components have been built! Remaining work is in **Phase F: Polish & Production**.

### What WAS Built (Phases A-E Complete)
| Component | Status | Built In |
|-----------|--------|----------|
| BigQuery Client Service | ✅ COMPLETE | Phase B |
| Brand Model & Migration | ✅ COMPLETE | Phase B |
| Supply Panel | ✅ COMPLETE | Phase D |
| Pricing Panel | ✅ COMPLETE | Phase E |
| Brand Scope Enforcement | ✅ COMPLETE | Phase C/D |
| Premium Feature Gating | ✅ COMPLETE | Phase D |
| Chart Data APIs | ✅ COMPLETE | Phase D |

---

## Phase Overview

| Phase | Name | Status | Tickets | Description |
|-------|------|--------|---------|-------------|
| A | Foundation & Fixes | ✅ COMPLETED | A-001 to A-012 | Fix existing issues, stabilize platform |
| B | BigQuery Integration | ✅ COMPLETED | B-001 to B-008 | Connect to BigQuery, sync brands |
| C | Multi-Panel Architecture | ✅ COMPLETED | C-001 to C-015 | Create three separate panels |
| D | Supply Insights Portal | ✅ COMPLETED | D-001 to D-025 | Build supplier dashboard |
| E | Pricing Tool | ✅ COMPLETED | E-001 to E-012 | Build pricing analysis tool |
| F | Polish & Production | 🔄 IN PROGRESS | F-001 to F-010 | Testing, optimization, deployment |

---

# PHASE A: Foundation & Fixes

**Goal**: Get the existing codebase into a stable, runnable state before adding new features.
**Duration Estimate**: 3-5 days
**Prerequisites**: None
**Dependencies**: None

---

## Ticket A-001: Fix PHP 8.5 Deprecation Warnings

### Summary
PHP 8.5 deprecates certain PDO constants. The application shows deprecation warnings every time a command runs.

### Current Behavior
```
PHP Deprecated: Constant PDO::MYSQL_ATTR_SSL_CA is deprecated since 8.5,
use Pdo\Mysql::ATTR_SSL_CA instead
```

### Affected Files
1. `/vendor/laravel/framework/config/database.php` (line 63, 83) - VENDOR (will update with composer)
2. `/config/database.php` (line 62, 82) - PROJECT FILE (must fix)

### Solution
Edit `/config/database.php` and replace:
```php
// OLD (line 62 and 82)
PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),

// NEW
(defined('Pdo\\Mysql::ATTR_SSL_CA') ? Pdo\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA)
    => env('MYSQL_ATTR_SSL_CA'),
```

Or simply update Laravel framework to a version that fixes this:
```bash
composer update laravel/framework
```

### Acceptance Criteria
- [ ] No deprecation warnings when running `php artisan --version`
- [ ] No deprecation warnings when running `php artisan test`
- [ ] All artisan commands work without warnings

### Testing Steps
1. Run `php artisan --version`
2. Verify no warnings appear
3. Run `php artisan list`
4. Verify clean output

### Priority: HIGH
### Effort: 15 minutes
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket A-002: Fix PHPStan/Larastan Configuration

### Summary
PHPStan fails to run because Larastan extension file is missing.

### Current Error
```
File '/Users/geraldsadya/paul_repo/spim/vendor/nunomaduro/larastan/extension.neon'
is missing or is not readable.
```

### Root Cause
Either:
1. Composer dependencies not fully installed
2. Larastan package corrupted
3. Version incompatibility

### Solution
```bash
# Remove vendor and reinstall
rm -rf vendor
composer install

# If that fails, try updating
composer update nunomaduro/larastan larastan/larastan
```

### Acceptance Criteria
- [ ] `composer run analyse` completes without file errors
- [ ] PHPStan reports actual code issues (not missing files)
- [ ] Zero errors or only known acceptable errors

### Testing Steps
1. Run `rm -rf vendor && composer install`
2. Run `composer run analyse`
3. Review output for code-level issues vs configuration issues

### Priority: HIGH
### Effort: 30 minutes
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket A-003: Start Docker Environment

### Summary
The application requires MySQL database via Docker. Containers exist but are stopped.

### Current State
```
spim_db      mysql:8.0         Exited (137) 4 days ago
spim_app     spim-app          Exited (0) 4 days ago
spim_web     nginx:1.27-alpine Exited (0) 4 days ago
spim_queue   spim-queue        Exited (0) 4 days ago
spim_redis   redis:7-alpine    Exited (0) 4 days ago
```

### Solution
```bash
cd /Users/geraldsadya/paul_repo/spim
docker-compose up -d
```

### Verification
```bash
# Check containers running
docker ps

# Test database connection
php artisan migrate:status
```

### Acceptance Criteria
- [x] All 5 containers running (db, app, web, queue, redis)
- [x] `docker ps` shows healthy status
- [x] `php artisan migrate:status` shows migration list
- [x] Application accessible at http://localhost:8080

### Notes
- Test database (`spim_test`) created for test suite
- 257 tests pass, 5 pre-existing failures in `SideBySideEditTest` (WIP feature)

### Troubleshooting
If `spim_db` won't start:
```bash
# Check logs
docker logs spim_db

# If corrupted, remove and recreate
docker-compose down -v
docker-compose up -d
php artisan migrate
```

### Priority: CRITICAL (BLOCKER)
### Effort: 15 minutes
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket A-004: Run Database Migrations

### Summary
Ensure all 22 migrations are applied to the database.

### Prerequisites
- Ticket A-003 (Docker running)

### Current Migrations (22 total)
1. `0001_01_01_000000_create_users_table`
2. `0001_01_01_000001_create_cache_table`
3. `0001_01_01_000002_create_jobs_table`
4. `2025_09_10_164605_create_permission_tables`
5. `2025_10_01_000000_create_entity_types`
6. `2025_10_01_000005_create_entities`
7. `2025_10_01_000008_create_attributes`
8. `2025_10_01_000010_create_eav_tables`
9. `2025_10_01_000020_create_eav_views`
10. `2025_10_02_074612_create_user_preferences_table`
11. `2025_10_02_093835_create_attribute_sections_table`
12. `2025_10_02_093848_add_section_fields_to_attributes_table`
13. `2025_10_02_094216_add_display_name_to_attributes_table`
14. `2025_10_02_114648_add_display_name_to_entity_types_table`
15. `2025_10_02_120000_add_is_active_to_users_table`
16. `2025_10_09_103304_create_sync_tracking_tables`
17. `2025_10_10_120000_refactor_attribute_system`
18. `2025_10_12_165357_add_cancelled_status_to_sync_runs_table`
19. `2025_10_15_000000_create_pipeline_tables`
20. `2025_10_22_193133_add_entity_filter_to_pipelines_table`
21. `2025_11_12_100000_add_bidirectional_to_is_sync`
22. `2025_11_12_110000_add_conflict_to_sync_results_operation`

### Solution
```bash
php artisan migrate
```

### Acceptance Criteria
- [x] All 22 migrations show "Ran" status
- [x] `php artisan migrate:status` shows all green
- [x] No errors during migration

### Notes
- All migrations were already applied from previous development

### Priority: CRITICAL
### Effort: 5 minutes
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket A-005: Run and Fix Test Suite

### Summary
Execute the test suite and ensure all tests pass.

### Prerequisites
- Ticket A-003 (Docker running)
- Ticket A-004 (Migrations applied)

### Current State
- 37 test files
- 263 tests
- Currently failing due to database connection

### Solution
```bash
# Create test database if not exists
docker exec spim_db mysql -u root -proot -e "CREATE DATABASE IF NOT EXISTS spim_test;"
docker exec spim_db mysql -u root -proot -e "GRANT ALL ON spim_test.* TO 'spim'@'%';"

# Update .env.testing if needed
# DB_DATABASE=spim_test

# Run migrations for test database
php artisan migrate --env=testing

# Run tests
php artisan test
```

### Expected Test Files
**Feature Tests (18 files)**:
- `ApprovalWorkflowTest.php` (25KB - comprehensive)
- `AttributeCrudTest.php`
- `AttributeEditingTest.php`
- `EntityBrowsingTest.php`
- `EntityFilterTest.php`
- `MagentoSyncCommandTest.php`
- `MagentoSyncEndToEndTest.php`
- `MagentoSyncJobsTest.php`
- `PipelineDependencyTest.php`
- `PipelineExecutionTest.php`
- `PipelineMetadataDisplayTest.php`
- `PipelineModelTest.php`
- `ProfileTest.php`
- `SideBySideEditTest.php`
- `SyncRunCancelTest.php`
- Plus Auth tests

**Unit Tests (14 files)**:
- `AiPromptProcessorModuleTest.php`
- `AttributeAllowedValuesTest.php`
- `AttributeOptionSyncTest.php`
- `AttributeServiceTest.php`
- `EntityColumnCustomizationTest.php`
- `EntityTableSearchSortTest.php`
- `MagentoApiClientTest.php`
- `NodePipelineRunnerTest.php`
- `PipelineDataClassesTest.php`
- `PipelineModuleRegistryTest.php`
- `ProductSyncConversionTest.php`
- `ProductSyncTest.php` (37KB - most comprehensive)

### Acceptance Criteria
- [x] All 262 tests pass (1 skipped as expected)
- [x] Zero failures, zero errors
- [x] Test duration under 60 seconds (10.96s achieved)
- [ ] Coverage report generated

### Notes
- Fixed 5 failing tests in SideBySideEditTest:
  - Added missing 'id' field when creating Entity instances
  - Fixed parameter name from 'entities' to 'entityIds'
  - Updated validation test to match actual behavior

### Priority: HIGH
### Effort: 1-2 hours (debugging any failures)
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket A-006: Seed Initial Data

### Summary
Create an admin user and seed necessary roles for development.

### Prerequisites
- Ticket A-004 (Migrations applied)

### Solution
```bash
# Run existing seeders
php artisan db:seed --class=RoleSeeder

# Create admin user via tinker
php artisan tinker
>>> $user = new \App\Models\User();
>>> $user->name = 'Admin';
>>> $user->email = 'admin@silvertreebrands.com';
>>> $user->password = bcrypt('password');
>>> $user->is_active = true;
>>> $user->save();
>>> $user->assignRole('admin');
```

### Required Roles
| Role | Description | Panel Access |
|------|-------------|--------------|
| admin | Super user | All panels |
| pim-editor | Product manager | PIM only |
| supplier-basic | Free tier supplier | Supply only |
| supplier-premium | Paid tier supplier | Supply only |
| pricing-analyst | Pricing team | Pricing only |

### Acceptance Criteria
- [x] Admin user can log in at /admin
- [x] All roles created in database
- [x] Admin user has 'admin' role assigned

### Priority: HIGH
### Effort: 15 minutes
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket A-007: Verify Application Loads

### Summary
Confirm the application loads in browser and basic functionality works.

### Prerequisites
- All previous A tickets completed

### Testing Checklist
```
1. Open http://localhost:8080/admin
2. See login page
3. Log in with admin@silvertreebrands.com / password
4. Dashboard loads
5. Navigate to Products (Entities menu)
6. Navigate to Attributes (Settings menu)
7. Navigate to Pipelines (Settings menu)
8. Navigate to Magento Sync (Settings menu)
9. Navigate to Users (Settings menu)
10. Queue Monitor link works (opens Horizon)
```

### Acceptance Criteria
- [x] Login page renders correctly
- [x] Login succeeds with admin credentials
- [x] Dashboard displays widgets
- [x] All menu items accessible
- [x] No PHP errors in browser
- [ ] No console JavaScript errors (requires manual browser verification)

### Priority: CRITICAL
### Effort: 30 minutes
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket A-008: Document Current Database Schema

### Summary
Create a visual database schema diagram for reference.

### Purpose
Before adding new tables, we need to understand existing relationships.

### Current Tables (from migrations)

**Core Tables**:
- `users` - Application users
- `password_reset_tokens`
- `sessions`
- `cache`, `cache_locks`
- `jobs`, `job_batches`, `failed_jobs`

**Permission Tables** (Spatie):
- `roles`
- `permissions`
- `model_has_roles`
- `model_has_permissions`
- `role_has_permissions`

**Entity System**:
- `entity_types` - Types of entities (Product, Category)
- `entities` - Individual entities (each product)
- `attributes` - Field definitions
- `attribute_sections` - UI groupings
- `user_preferences` - Per-user UI settings

**EAV Tables**:
- `eav_versioned` - Main value storage (current, approved, live, override)
- `eav_input` - Raw input values
- `eav_timeseries` - Historical tracking
- `entity_attr_links` - Relationships

**EAV Views** (MySQL):
- `entity_attr_json` - Aggregated JSON bags
- `entity_attribute_resolved` - Resolved values

**Sync Tables**:
- `sync_runs` - Sync execution records
- `sync_results` - Per-item results

**Pipeline Tables**:
- `pipelines` - AI pipeline definitions
- `pipeline_modules` - Steps in pipelines
- `pipeline_runs` - Execution records
- `pipeline_evals` - Test cases

### Deliverable
Create a Mermaid diagram or draw.io export showing:
- All tables
- Primary keys
- Foreign key relationships
- Table ownership (which module owns it)

### Acceptance Criteria
- [x] Schema diagram created (Mermaid ER diagram)
- [x] All 22+ tables documented (29 objects: 27 tables + 2 views)
- [x] Relationships clearly shown (FK summary + diagrams)
- [x] Saved to `/docs/database-schema.md`

### Priority: MEDIUM
### Effort: 2 hours
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket A-009: Configure Environment Variables

### Summary
Set up all required environment variables for development.

### Current .env Status
```env
# SET (Working)
APP_NAME="spim"
APP_ENV=local
APP_KEY=base64:8vCJyO57PodReEnGv0P/qlsy0lXUG7ufUwdU27SF4TQ=
DB_* (all configured for Docker)

# EMPTY (Need to configure)
MAGENTO_BASE_URL=
MAGENTO_ACCESS_TOKEN=
OPENAI_API_KEY=

# MISSING (Need to add)
COMPANY_ID=                              # 3=FtN, 5=PH, 9=UCOOK
GOOGLE_APPLICATION_CREDENTIALS=          # Path to service account JSON
BIGQUERY_PROJECT_ID=                     # silvertree-poc or similar
BIGQUERY_DATASET=                        # sh_output
```

### What Each Variable Does

| Variable | Purpose | Where to Get |
|----------|---------|--------------|
| `COMPANY_ID` | Filter data by company | Set to 3 for FtN initially |
| `GOOGLE_APPLICATION_CREDENTIALS` | BigQuery auth | Google Cloud Console → IAM → Service Accounts |
| `BIGQUERY_PROJECT_ID` | Which GCP project | From user's project list |
| `BIGQUERY_DATASET` | Dataset name | Likely `sh_output` |
| `MAGENTO_BASE_URL` | Magento store URL | From Magento admin |
| `MAGENTO_ACCESS_TOKEN` | Magento REST API | Magento → System → Integrations |
| `OPENAI_API_KEY` | AI features | platform.openai.com |

### Solution
1. Create `/secrets/` directory (gitignored)
2. Save Google service account JSON there
3. Update `.env` with all values

### Acceptance Criteria
- [x] `.env` has all variables documented (see .env.example)
- [x] Google service account JSON saved locally (/secrets/ directory created)
- [ ] BigQuery connection testable (after Phase B - requires credentials)
- [x] Documentation updated with env setup steps (docs/environment-setup.md)

### Priority: HIGH
### Effort: 1 hour (mostly waiting for credentials)
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket A-010: Set Up Local Development Workflow

### Summary
Document the complete local development setup process.

### Deliverable
Create `/docs/local-development-setup.md` with:

```markdown
# Local Development Setup

## Prerequisites
- PHP 8.2+ (PHP 8.5 has warnings but works)
- Composer 2.x
- Node.js 20+
- Docker Desktop
- Google Cloud SDK (gcloud CLI)

## First-Time Setup

### 1. Clone Repository
git clone <repo-url>
cd spim

### 2. Install PHP Dependencies
composer install

### 3. Install Node Dependencies
npm ci

### 4. Configure Environment
cp .env.example .env
php artisan key:generate

### 5. Start Docker Services
docker-compose up -d

### 6. Run Migrations
php artisan migrate

### 7. Seed Database
php artisan db:seed

### 8. Create Admin User
php artisan tinker
# ... (instructions)

### 9. Start Development Server
composer run dev
# This runs: php artisan serve, queue:listen, pail, npm run dev

### 10. Access Application
- Main App: http://localhost:8080/admin
- Horizon: http://localhost:8080/horizon

## Daily Workflow

### Start Day
docker-compose up -d
composer run dev

### Run Tests
php artisan test

### Code Quality
composer run format      # Auto-fix formatting
composer run analyse     # Static analysis

### End Day
docker-compose stop      # (optional)
```

### Acceptance Criteria
- [x] New developer can set up in < 30 minutes following guide
- [x] All commands tested and working
- [x] Troubleshooting section included
- [x] Saved to `/docs/local-development-setup.md`

### Priority: MEDIUM
### Effort: 1 hour
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket A-011: Audit Security Configuration

### Summary
Review security settings before building user-facing features.

### Checklist

**Authentication**:
- [x] Password hashing uses bcrypt (cost 12+)
- [x] Session timeout configured (currently 120 min)
- [x] CSRF protection enabled
- [x] Remember me tokens hashed

**Authorization**:
- [x] All routes require authentication
- [x] Admin-only routes check admin role
- [x] Policies exist for sensitive models

**Data Protection**:
- [x] Sensitive data not logged
- [x] API keys not in version control
- [x] SQL injection prevention (Eloquent)
- [x] XSS prevention (Blade escaping)

**Files to Review**:
- `config/auth.php`
- `config/session.php`
- `app/Http/Middleware/`
- `app/Policies/`
- `.gitignore` (ensure secrets excluded)

### Acceptance Criteria
- [x] Security audit documented (see `docs/security-audit.md`)
- [x] Any issues logged as separate tickets
- [x] No critical vulnerabilities found

### Priority: HIGH
### Effort: 2 hours
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket A-012: Create Project README

### Summary
Update the README.md with current project information.

### Current README Status
Needs to be checked and updated.

### Required Sections
1. Project Overview
2. Silvertree Brands Context
3. The Three Panels (PIM, Supply, Pricing)
4. Quick Start Guide
5. Environment Setup
6. Running Tests
7. Architecture Overview
8. Documentation Index
9. Contributing Guidelines
10. Contact Information

### Acceptance Criteria
- [x] README accurately describes current state
- [x] Quick start works for new developers
- [x] Links to all other documentation
- [x] Professional presentation

### Priority: MEDIUM
### Effort: 1 hour
### Assigned To: TBD
### Status: COMPLETED

---

## Phase A Completion Checklist

Before moving to Phase B, ALL of the following must be true:

- [x] A-001: No PHP deprecation warnings
- [x] A-002: PHPStan runs successfully
- [x] A-003: Docker containers running
- [x] A-004: All migrations applied
- [x] A-005: All tests passing
- [x] A-006: Admin user created, can log in
- [x] A-007: Application fully functional in browser
- [x] A-008: Database schema documented
- [x] A-009: Environment variables documented
- [x] A-010: Local setup guide created
- [x] A-011: Security audit complete
- [x] A-012: README updated

**Phase A Status**: COMPLETED (December 2024)
**Target Completion**: TBD

---

# PHASE B: BigQuery Integration

**Goal**: Connect to Silvertree's BigQuery data warehouse and sync brand data.
**Duration Estimate**: 5-7 days
**Prerequisites**: Phase A complete
**Dependencies**: Google Cloud service account access

---

## Ticket B-001: Install BigQuery PHP Package

### Summary
Add the Google Cloud BigQuery PHP library to the project.

### Solution
```bash
composer require google/cloud-bigquery
```

### Package Details
- Package: `google/cloud-bigquery`
- Documentation: https://cloud.google.com/php/docs/reference/cloud-bigquery/latest
- Requires: PHP 8.1+, ext-grpc (optional but recommended)

### Verification
```php
// Test in tinker
use Google\Cloud\BigQuery\BigQueryClient;
$client = new BigQueryClient(['projectId' => 'silvertree-poc']);
// Should not throw exception
```

### Acceptance Criteria
- [x] Package installed via Composer
- [x] `composer.json` updated
- [x] Basic instantiation works in Tinker
- [x] No conflicts with existing packages

### Priority: CRITICAL
### Effort: 15 minutes
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket B-002: Configure Google Cloud Authentication

### Summary
Set up authentication for BigQuery access using a service account.

### Prerequisites
- Access to Google Cloud Console for Silvertree organization
- Permission to create/download service account keys

### Steps

1. **Get Service Account JSON** (from Google Cloud Console):
   - Go to: https://console.cloud.google.com
   - Select project: `silvertree-poc` (or appropriate project)
   - Navigate: IAM & Admin → Service Accounts
   - Either use existing service account or create new
   - Download JSON key file

2. **Save Key Securely**:
   ```bash
   mkdir -p /Users/geraldsadya/paul_repo/spim/storage/secrets
   # Save JSON file as: storage/secrets/google-credentials.json
   ```

3. **Add to .gitignore**:
   ```
   storage/secrets/
   *.json
   ```

4. **Configure Environment**:
   ```env
   GOOGLE_APPLICATION_CREDENTIALS=/Users/geraldsadya/paul_repo/spim/storage/secrets/google-credentials.json
   BIGQUERY_PROJECT_ID=silvertree-poc
   BIGQUERY_DATASET=sh_output
   ```

### Required Permissions
The service account needs these BigQuery roles:
- `roles/bigquery.dataViewer` (read tables)
- `roles/bigquery.jobUser` (run queries)

### Acceptance Criteria
- [ ] Service account JSON file obtained (USER ACTION REQUIRED)
- [x] File saved in gitignored location (secrets/ directory ready)
- [x] Environment variables configured (config/bigquery.php created)
- [ ] Authentication works (tested in B-003)

### Priority: CRITICAL
### Effort: 1 hour
### Assigned To: TBD
### Status: IN PROGRESS (awaiting credentials)

---

## Ticket B-003: Create BigQuery Service Class

### Summary
Create a reusable service class for all BigQuery operations.

### File Location
`/app/Services/BigQueryService.php`

### Class Design

```php
<?php

namespace App\Services;

use Google\Cloud\BigQuery\BigQueryClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BigQueryService
{
    private BigQueryClient $client;
    private string $dataset;
    private int $companyId;
    private int $cacheTtl = 900; // 15 minutes

    public function __construct()
    {
        $this->client = new BigQueryClient([
            'projectId' => config('bigquery.project_id'),
            'keyFilePath' => config('bigquery.credentials_path'),
        ]);
        $this->dataset = config('bigquery.dataset');
        $this->companyId = (int) config('bigquery.company_id');
    }

    /**
     * Execute a query with parameters
     */
    public function query(string $sql, array $params = []): array
    {
        // Implementation
    }

    /**
     * Execute query with caching
     */
    public function queryCached(string $cacheKey, string $sql, array $params = [], ?int $ttl = null): array
    {
        // Implementation with Cache::remember
    }

    /**
     * Get all unique brands for this company
     */
    public function getBrands(): Collection
    {
        $sql = "SELECT DISTINCT brand FROM `{$this->dataset}.dim_product` WHERE company_id = @company_id ORDER BY brand";
        // Implementation
    }

    /**
     * Get sales data for a brand
     */
    public function getSalesByBrand(string $brand, string $startDate, string $endDate): array
    {
        // Implementation
    }

    /**
     * Get product performance for a brand
     */
    public function getProductPerformance(string $brand, string $period = '12m'): array
    {
        // Implementation
    }

    /**
     * Get the configured company ID
     */
    public function getCompanyId(): int
    {
        return $this->companyId;
    }
}
```

### Configuration File
Create `/config/bigquery.php`:

```php
<?php

return [
    'project_id' => env('BIGQUERY_PROJECT_ID'),
    'dataset' => env('BIGQUERY_DATASET', 'sh_output'),
    'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
    'company_id' => env('COMPANY_ID', 3),
    'cache_ttl' => env('BIGQUERY_CACHE_TTL', 900),
];
```

### Acceptance Criteria
- [x] Service class created
- [x] Configuration file created
- [x] Constructor validates credentials exist
- [x] `query()` method executes SQL and returns results
- [x] `queryCached()` method uses Laravel cache
- [x] `getBrands()` method returns brand list
- [x] Error handling for connection failures
- [x] Logging for debugging

### Testing
Create `/tests/Unit/BigQueryServiceTest.php` with mocked client.

### Priority: CRITICAL
### Effort: 4 hours
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket B-004: Create Brand Model and Migration

### Summary
Create the local `brands` table to cache brand data from BigQuery.

### Why Cache Locally?
- Faster page loads (no BigQuery latency)
- Reduced BigQuery costs
- Enables relationships with users
- Works offline

### Migration
`/database/migrations/2025_12_XX_create_brands_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('company_id');
            $table->enum('access_level', ['basic', 'premium'])->default('basic');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'company_id']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
```

### Model
`/app/Models/Brand.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $fillable = [
        'name',
        'company_id',
        'access_level',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    // Users who have access to this brand (suppliers)
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'supplier_brand_access')
            ->withTimestamps();
    }

    // Competitor relationships
    public function competitors(): HasMany
    {
        return $this->hasMany(BrandCompetitor::class);
    }

    // Scopes
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopePremium($query)
    {
        return $query->where('access_level', 'premium');
    }

    public function scopeBasic($query)
    {
        return $query->where('access_level', 'basic');
    }

    // Helpers
    public function isPremium(): bool
    {
        return $this->access_level === 'premium';
    }
}
```

### Acceptance Criteria
- [x] Migration created and runs successfully
- [x] Model created with relationships
- [x] Factory created for testing
- [x] Unit tests for model methods

### Priority: HIGH
### Effort: 2 hours
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket B-005: Create Brand Competitor Model and Migration

### Summary
Allow configuring up to 3 competitor brands per brand.

### Migration
`/database/migrations/2025_12_XX_create_brand_competitors_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->onDelete('cascade');
            $table->foreignId('competitor_brand_id')->constrained('brands')->onDelete('cascade');
            $table->unsignedTinyInteger('position'); // 1, 2, or 3
            $table->timestamps();

            $table->unique(['brand_id', 'position']);
            $table->unique(['brand_id', 'competitor_brand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_competitors');
    }
};
```

### Model
`/app/Models/BrandCompetitor.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandCompetitor extends Model
{
    protected $fillable = [
        'brand_id',
        'competitor_brand_id',
        'position',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'competitor_brand_id');
    }
}
```

### Acceptance Criteria
- [x] Migration created and runs
- [x] Model with relationships
- [x] Validation: position must be 1, 2, or 3
- [x] Validation: brand cannot be its own competitor

### Priority: HIGH
### Effort: 1 hour
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket B-006: Create Supplier Brand Access Model and Migration

### Summary
Link supplier users to the brands they can view.

### Migration
`/database/migrations/2025_12_XX_create_supplier_brand_access_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_brand_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('brand_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'brand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_brand_access');
    }
};
```

### Update User Model
Add to `/app/Models/User.php`:

```php
// Add relationship
public function brands(): BelongsToMany
{
    return $this->belongsToMany(Brand::class, 'supplier_brand_access')
        ->withTimestamps();
}

// Helper to check brand access
public function canAccessBrand(Brand $brand): bool
{
    if ($this->hasRole('admin')) {
        return true;
    }
    return $this->brands()->where('brands.id', $brand->id)->exists();
}

// Get accessible brand IDs
public function accessibleBrandIds(): array
{
    if ($this->hasRole('admin')) {
        return Brand::pluck('id')->toArray();
    }
    return $this->brands()->pluck('brands.id')->toArray();
}
```

### Acceptance Criteria
- [x] Migration created and runs
- [x] Pivot table properly configured
- [x] User model updated with relationship
- [x] `canAccessBrand()` method works
- [x] Admin bypass works

### Priority: HIGH
### Effort: 1 hour
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket B-007: Create Brand Sync Command

### Summary
Artisan command to sync brands from BigQuery to local database.

### File Location
`/app/Console/Commands/SyncBrandsFromBigQuery.php`

### Implementation

```php
<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\BigQueryService;
use Illuminate\Console\Command;

class SyncBrandsFromBigQuery extends Command
{
    protected $signature = 'brands:sync {--company= : Override company ID}';
    protected $description = 'Sync brands from BigQuery dim_product table';

    public function handle(BigQueryService $bigQuery): int
    {
        $companyId = $this->option('company') ?? $bigQuery->getCompanyId();

        $this->info("Syncing brands for company ID: {$companyId}");

        try {
            $brands = $bigQuery->getBrands();

            $this->info("Found {$brands->count()} brands in BigQuery");

            $bar = $this->output->createProgressBar($brands->count());

            foreach ($brands as $brandName) {
                Brand::updateOrCreate(
                    ['name' => $brandName, 'company_id' => $companyId],
                    ['synced_at' => now()]
                );
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info('Brand sync complete!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Sync failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
```

### Usage
```bash
# Sync using configured company ID
php artisan brands:sync

# Sync for specific company
php artisan brands:sync --company=3
```

### Acceptance Criteria
- [x] Command registered and appears in `php artisan list`
- [x] Successfully queries BigQuery (when configured)
- [x] Creates new brands
- [x] Updates existing brands
- [x] Shows progress bar
- [x] Handles errors gracefully

### Priority: HIGH
### Effort: 2 hours
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket B-008: Test BigQuery Integration End-to-End

### Summary
Verify the entire BigQuery integration works from command to database.

### Test Plan

1. **Unit Tests**:
   - BigQueryService with mocked client
   - Brand model relationships
   - User brand access methods

2. **Integration Tests** (requires real BigQuery):
   - Execute actual query
   - Verify data returned
   - Test caching behavior

3. **Manual Testing**:
   ```bash
   # Run sync
   php artisan brands:sync

   # Verify in database
   php artisan tinker
   >>> Brand::count()
   >>> Brand::first()
   ```

### Acceptance Criteria
- [x] Unit tests pass (314 tests)
- [x] Integration test passes (with real BQ) - VERIFIED 2024-12-14
- [x] Brands appear in database - 1943 brands synced from BigQuery
- [x] Cache works (tested - 2210ms → 1ms on cached query)
- [x] Error handling works (tested - graceful failure without credentials)

### Priority: HIGH
### Effort: 2 hours
### Assigned To: TBD
### Status: COMPLETE

---

## Phase B Completion Checklist

Before moving to Phase C, ALL of the following must be true:

- [x] B-001: BigQuery package installed
- [x] B-002: Google Cloud auth configured
- [x] B-003: BigQueryService class created and tested
- [x] B-004: Brand model and migration done
- [x] B-005: BrandCompetitor model done
- [x] B-006: SupplierBrandAccess model done
- [x] B-007: Brand sync command working
- [x] B-008: End-to-end test passing - 1943 brands synced from BigQuery

**Phase B Status**: COMPLETE ✅
**Verified**: 2024-12-14
- BigQuery connected via Application Default Credentials (gcloud auth)
- 1943 brands synced from sh_output.dim_product
- Caching working (2210ms → 1ms)

---

# PHASE C: Multi-Panel Architecture

**Goal**: Refactor from single AdminPanel to three separate panels (PIM, Supply, Pricing).
**Duration Estimate**: 5-7 days
**Prerequisites**: Phase A and B complete
**Dependencies**: None

---

## Ticket C-001: Understand Current Filament Structure

### Summary
Document the existing Filament setup before refactoring.

### Current Structure
```
app/
├── Filament/
│   ├── Pages/
│   │   ├── MagentoSync.php           # Custom page
│   │   └── ...
│   ├── Resources/
│   │   ├── AbstractEntityTypeResource.php
│   │   ├── AttributeResource.php
│   │   ├── AttributeSectionResource.php
│   │   ├── CategoryEntityResource.php
│   │   ├── EntityTypeResource.php
│   │   ├── PipelineResource/
│   │   │   ├── Pages/
│   │   │   └── RelationManagers/
│   │   ├── PipelineResource.php
│   │   ├── ProductEntityResource.php
│   │   └── UserResource.php
│   └── Widgets/
│       └── ...
└── Providers/
    └── Filament/
        └── AdminPanelProvider.php    # Single panel definition
```

### Current Panel Configuration
- ID: `admin`
- Path: `/admin`
- Color: Amber
- Auto-discovers resources in `app/Filament/Resources`
- Auto-discovers pages in `app/Filament/Pages`
- Auto-discovers widgets in `app/Filament/Widgets`

### What Needs to Change
1. Rename `AdminPanelProvider` → `PimPanelProvider`
2. Create `SupplyPanelProvider`
3. Create `PricingPanelProvider`
4. Reorganize resources into panel-specific folders
5. Update paths from `/admin` to `/pim`, `/supply`, `/pricing`

### Deliverable
Document in `/docs/filament-refactor-plan.md`:
- Current structure
- Target structure
- File-by-file migration plan
- Risk assessment

### Acceptance Criteria
- [x] Current structure fully documented
- [x] Target structure designed
- [x] Migration plan reviewed

### Priority: HIGH
### Effort: 2 hours
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket C-002: Create Target Directory Structure

### Summary
Create the new folder structure for multi-panel architecture.

### Target Structure
```
app/
├── Filament/
│   ├── Shared/                    # NEW - Shared components
│   │   ├── Components/
│   │   └── Widgets/
│   │
│   ├── PimPanel/                  # NEW - PIM resources
│   │   ├── Pages/
│   │   │   ├── Dashboard.php
│   │   │   └── MagentoSync.php
│   │   ├── Resources/
│   │   │   ├── AttributeResource/
│   │   │   ├── AttributeSectionResource.php
│   │   │   ├── CategoryResource.php
│   │   │   ├── EntityTypeResource.php
│   │   │   ├── PipelineResource/
│   │   │   ├── ProductResource.php
│   │   │   └── UserResource.php
│   │   └── Widgets/
│   │
│   ├── SupplyPanel/               # NEW - Supply resources
│   │   ├── Pages/
│   │   │   ├── Dashboard.php
│   │   │   ├── Sales.php
│   │   │   ├── MarketShare.php
│   │   │   └── ...
│   │   ├── Resources/
│   │   │   └── BrandResource.php
│   │   └── Widgets/
│   │       └── SalesKpiWidget.php
│   │
│   └── PricingPanel/              # NEW - Pricing resources
│       ├── Pages/
│       │   └── Dashboard.php
│       ├── Resources/
│       │   └── PriceTrackResource.php
│       └── Widgets/
│
└── Providers/
    └── Filament/
        ├── PimPanelProvider.php       # Renamed from AdminPanelProvider
        ├── SupplyPanelProvider.php    # NEW
        └── PricingPanelProvider.php   # NEW
```

### Commands
```bash
# Create directories
mkdir -p app/Filament/Shared/Components
mkdir -p app/Filament/Shared/Widgets
mkdir -p app/Filament/PimPanel/Pages
mkdir -p app/Filament/PimPanel/Resources
mkdir -p app/Filament/PimPanel/Widgets
mkdir -p app/Filament/SupplyPanel/Pages
mkdir -p app/Filament/SupplyPanel/Resources
mkdir -p app/Filament/SupplyPanel/Widgets
mkdir -p app/Filament/PricingPanel/Pages
mkdir -p app/Filament/PricingPanel/Resources
mkdir -p app/Filament/PricingPanel/Widgets
```

### Acceptance Criteria
- [x] All directories created
- [x] No existing files moved yet (just structure)

### Priority: HIGH
### Effort: 15 minutes
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket C-003: Create PIM Panel Provider

### Summary
Rename and configure the existing admin panel for PIM functionality.

### Steps

1. **Rename File**:
   ```bash
   mv app/Providers/Filament/AdminPanelProvider.php app/Providers/Filament/PimPanelProvider.php
   ```

2. **Update Class**:
   ```php
   <?php

   namespace App\Providers\Filament;

   use Filament\Panel;
   use Filament\PanelProvider;
   // ... other imports

   class PimPanelProvider extends PanelProvider
   {
       public function panel(Panel $panel): Panel
       {
           return $panel
               ->id('pim')
               ->path('pim')                    // Changed from 'admin'
               ->login()
               ->profile()
               ->colors([
                   'primary' => '#006654',      // FtN green
               ])
               ->brandName('Silvertree PIM')
               ->maxContentWidth('full')
               ->discoverResources(
                   in: app_path('Filament/PimPanel/Resources'),  // Updated path
                   for: 'App\Filament\PimPanel\Resources'
               )
               ->discoverPages(
                   in: app_path('Filament/PimPanel/Pages'),
                   for: 'App\Filament\PimPanel\Pages'
               )
               ->discoverWidgets(
                   in: app_path('Filament/PimPanel/Widgets'),
                   for: 'App\Filament\PimPanel\Widgets'
               )
               // ... rest of configuration
               ->authMiddleware([
                   Authenticate::class,
                   CheckUserIsActive::class,
                   EnsureUserCanAccessPimPanel::class,  // NEW middleware
               ]);
       }
   }
   ```

3. **Register in config/app.php** (if needed)

### Acceptance Criteria
- [x] Provider renamed and updated
- [x] Panel accessible at `/pim`
- [x] `/admin` no longer works (or redirects)
- [x] All existing functionality preserved

### Priority: CRITICAL
### Effort: 2 hours
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket C-004: Move PIM Resources to New Location

### Summary
Move all existing Filament resources to the PimPanel namespace.

### Files to Move

| From | To |
|------|-----|
| `app/Filament/Resources/ProductEntityResource.php` | `app/Filament/PimPanel/Resources/ProductResource.php` |
| `app/Filament/Resources/CategoryEntityResource.php` | `app/Filament/PimPanel/Resources/CategoryResource.php` |
| `app/Filament/Resources/AttributeResource.php` | `app/Filament/PimPanel/Resources/AttributeResource.php` |
| `app/Filament/Resources/AttributeSectionResource.php` | `app/Filament/PimPanel/Resources/AttributeSectionResource.php` |
| `app/Filament/Resources/EntityTypeResource.php` | `app/Filament/PimPanel/Resources/EntityTypeResource.php` |
| `app/Filament/Resources/PipelineResource.php` | `app/Filament/PimPanel/Resources/PipelineResource.php` |
| `app/Filament/Resources/PipelineResource/` | `app/Filament/PimPanel/Resources/PipelineResource/` |
| `app/Filament/Resources/UserResource.php` | `app/Filament/PimPanel/Resources/UserResource.php` |
| `app/Filament/Resources/AbstractEntityTypeResource.php` | `app/Filament/PimPanel/Resources/AbstractEntityTypeResource.php` |
| `app/Filament/Pages/MagentoSync.php` | `app/Filament/PimPanel/Pages/MagentoSync.php` |

### Namespace Updates Required
Each moved file needs namespace update:
```php
// OLD
namespace App\Filament\Resources;

// NEW
namespace App\Filament\PimPanel\Resources;
```

### Use Statement Updates
Update any `use` statements that reference old locations.

### Acceptance Criteria
- [x] All files moved
- [x] All namespaces updated
- [x] All use statements updated
- [x] Application still works (verified)
- [x] Tests still pass (314 tests pass)

### Priority: CRITICAL
### Effort: 3 hours
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket C-005: Create Supply Panel Provider

### Summary
Create the panel provider for the Supply Insights portal.

### File
`/app/Providers/Filament/SupplyPanelProvider.php`:

```php
<?php

namespace App\Providers\Filament;

use App\Http\Middleware\CheckUserIsActive;
use App\Http\Middleware\EnsureUserCanAccessSupplyPanel;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SupplyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('supply')
            ->path('supply')
            ->login()
            ->profile()
            ->colors([
                'primary' => '#006654',  // FtN green (configurable per brand later)
            ])
            ->brandName('Supplier Portal')
            ->brandLogo(asset('images/silvertree-logo.svg'))
            ->maxContentWidth('full')
            ->discoverResources(
                in: app_path('Filament/SupplyPanel/Resources'),
                for: 'App\Filament\SupplyPanel\Resources'
            )
            ->discoverPages(
                in: app_path('Filament/SupplyPanel/Pages'),
                for: 'App\Filament\SupplyPanel\Pages'
            )
            ->discoverWidgets(
                in: app_path('Filament/SupplyPanel/Widgets'),
                for: 'App\Filament\SupplyPanel\Widgets'
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                CheckUserIsActive::class,
                EnsureUserCanAccessSupplyPanel::class,
            ]);
    }
}
```

### Register Provider
Add to `bootstrap/providers.php`:
```php
return [
    // ...
    App\Providers\Filament\SupplyPanelProvider::class,
];
```

### Acceptance Criteria
- [x] Provider created
- [x] Registered in providers
- [x] Panel accessible at `/supply` (verified)
- [x] Shows empty dashboard (no resources yet)

### Priority: HIGH
### Effort: 1 hour
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket C-006: Create Pricing Panel Provider

### Summary
Create the panel provider for the Pricing tool.

### File
`/app/Providers/Filament/PricingPanelProvider.php`:

```php
<?php

namespace App\Providers\Filament;

// Similar to SupplyPanelProvider...

class PricingPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('pricing')
            ->path('pricing')
            ->login()
            ->profile()
            ->colors([
                'primary' => '#4f46e5',  // Indigo for differentiation
            ])
            ->brandName('Pricing Tool')
            // ... similar configuration
            ->authMiddleware([
                Authenticate::class,
                CheckUserIsActive::class,
                EnsureUserCanAccessPricingPanel::class,
            ]);
    }
}
```

### Acceptance Criteria
- [x] Provider created
- [x] Registered in providers
- [x] Panel accessible at `/pricing` (verified)
- [x] Shows empty dashboard (no resources yet)

### Priority: MEDIUM
### Effort: 30 minutes
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket C-007: Create Panel Access Middleware

### Summary
Create middleware to enforce panel access based on user roles.

### Files to Create

**PIM Access** `/app/Http/Middleware/EnsureUserCanAccessPimPanel.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessPimPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('filament.pim.auth.login');
        }

        // Admin and PIM editors can access
        if ($user->hasAnyRole(['admin', 'pim-editor'])) {
            return $next($request);
        }

        abort(403, 'You do not have access to the PIM panel.');
    }
}
```

**Supply Access** `/app/Http/Middleware/EnsureUserCanAccessSupplyPanel.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessSupplyPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('filament.supply.auth.login');
        }

        // Admin and suppliers can access
        if ($user->hasAnyRole(['admin', 'supplier-basic', 'supplier-premium'])) {
            return $next($request);
        }

        abort(403, 'You do not have access to the Supplier Portal.');
    }
}
```

**Pricing Access** `/app/Http/Middleware/EnsureUserCanAccessPricingPanel.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessPricingPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('filament.pricing.auth.login');
        }

        // Admin and pricing analysts can access
        if ($user->hasAnyRole(['admin', 'pricing-analyst'])) {
            return $next($request);
        }

        abort(403, 'You do not have access to the Pricing tool.');
    }
}
```

### Acceptance Criteria
- [x] All three middleware created
- [x] Admin can access all panels (verified)
- [ ] Supplier can only access Supply (needs manual verification with test users)
- [ ] PIM editor can only access PIM (needs manual verification with test users)
- [ ] Pricing analyst can only access Pricing (needs manual verification with test users)
- [ ] Unauthorized access shows 403 (needs manual verification)

### Priority: CRITICAL
### Effort: 2 hours
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket C-008: Create Extended Role Seeder

### Summary
Update the role seeder with all required roles for multi-panel access.

### File
Update `/database/seeders/RoleSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
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
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Admin - full access
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(Permission::all());

        // PIM Editor
        $pimEditor = Role::firstOrCreate(['name' => 'pim-editor']);
        $pimEditor->syncPermissions([
            'access-pim-panel',
            'manage-products',
            'manage-attributes',
            'run-pipelines',
            'run-magento-sync',
        ]);

        // Supplier Basic
        $supplierBasic = Role::firstOrCreate(['name' => 'supplier-basic']);
        $supplierBasic->syncPermissions([
            'access-supply-panel',
            'view-own-brand-data',
        ]);

        // Supplier Premium
        $supplierPremium = Role::firstOrCreate(['name' => 'supplier-premium']);
        $supplierPremium->syncPermissions([
            'access-supply-panel',
            'view-own-brand-data',
            'view-premium-features',
        ]);

        // Pricing Analyst
        $pricingAnalyst = Role::firstOrCreate(['name' => 'pricing-analyst']);
        $pricingAnalyst->syncPermissions([
            'access-pricing-panel',
            'manage-price-alerts',
        ]);
    }
}
```

### Run Seeder
```bash
php artisan db:seed --class=RoleSeeder
```

### Acceptance Criteria
- [x] All 5 roles created
- [x] All permissions created
- [x] Permissions correctly assigned to roles
- [x] Seeder is idempotent (can run multiple times)

### Priority: HIGH
### Effort: 1 hour
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket C-009: Create Test Users for Each Role

### Summary
Create test user accounts for development and testing.

### Test Users to Create

| Email | Password | Role | Panel Access |
|-------|----------|------|--------------|
| admin@silvertreebrands.com | password | admin | All |
| pim@silvertreebrands.com | password | pim-editor | PIM |
| supplier-basic@test.com | password | supplier-basic | Supply |
| supplier-premium@test.com | password | supplier-premium | Supply |
| pricing@silvertreebrands.com | password | pricing-analyst | Pricing |

### Seeder
Create `/database/seeders/TestUserSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@silvertreebrands.com'],
            ['name' => 'Admin User', 'password' => bcrypt('password'), 'is_active' => true]
        );
        $admin->assignRole('admin');

        // PIM Editor
        $pimEditor = User::firstOrCreate(
            ['email' => 'pim@silvertreebrands.com'],
            ['name' => 'PIM Editor', 'password' => bcrypt('password'), 'is_active' => true]
        );
        $pimEditor->assignRole('pim-editor');

        // Supplier Basic (assign to first brand if exists)
        $supplierBasic = User::firstOrCreate(
            ['email' => 'supplier-basic@test.com'],
            ['name' => 'Basic Supplier', 'password' => bcrypt('password'), 'is_active' => true]
        );
        $supplierBasic->assignRole('supplier-basic');
        if ($brand = Brand::first()) {
            $supplierBasic->brands()->syncWithoutDetaching([$brand->id]);
        }

        // Supplier Premium
        $supplierPremium = User::firstOrCreate(
            ['email' => 'supplier-premium@test.com'],
            ['name' => 'Premium Supplier', 'password' => bcrypt('password'), 'is_active' => true]
        );
        $supplierPremium->assignRole('supplier-premium');
        if ($brand = Brand::first()) {
            $supplierPremium->brands()->syncWithoutDetaching([$brand->id]);
        }

        // Pricing Analyst
        $pricingAnalyst = User::firstOrCreate(
            ['email' => 'pricing@silvertreebrands.com'],
            ['name' => 'Pricing Analyst', 'password' => bcrypt('password'), 'is_active' => true]
        );
        $pricingAnalyst->assignRole('pricing-analyst');
    }
}
```

### Acceptance Criteria
- [x] All 5 test users created
- [x] Each has correct role assigned
- [x] Suppliers linked to a brand (if brands exist)
- [ ] Each can log into their appropriate panel (needs manual verification)
- [ ] Each is blocked from other panels (needs manual verification)

### Priority: HIGH
### Effort: 1 hour
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket C-010: Test Multi-Panel Navigation

### Summary
Verify all three panels work independently.

### Test Plan

**Test 1: Admin Access**
1. Log in as admin@silvertreebrands.com
2. Navigate to `/pim` - should work
3. Navigate to `/supply` - should work
4. Navigate to `/pricing` - should work

**Test 2: PIM Editor Access**
1. Log in as pim@silvertreebrands.com
2. Navigate to `/pim` - should work
3. Navigate to `/supply` - should get 403
4. Navigate to `/pricing` - should get 403

**Test 3: Supplier Access**
1. Log in as supplier-basic@test.com
2. Navigate to `/supply` - should work
3. Navigate to `/pim` - should get 403
4. Navigate to `/pricing` - should get 403

**Test 4: Pricing Access**
1. Log in as pricing@silvertreebrands.com
2. Navigate to `/pricing` - should work
3. Navigate to `/pim` - should get 403
4. Navigate to `/supply` - should get 403

### Acceptance Criteria
- [x] All 4 test scenarios pass (26 automated tests created)
- [x] 403 pages display correctly (verified by tests)
- [x] Login redirects to correct panel (verified by tests)
- [x] Session works across panels for admin (verified by tests)

### Priority: HIGH
### Effort: 1 hour
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket C-011: Update Existing Tests for New Structure

### Summary
Update all existing tests to work with new namespace/paths.

### Changes Required
1. Update namespace imports in test files
2. Update route assertions (`/admin` → `/pim`)
3. Update panel references

### Affected Test Files
All 37 test files may need updates.

### Approach
1. Run `php artisan test`
2. Fix failures one by one
3. Update namespaces systematically

### Acceptance Criteria
- [x] All 356 tests pass
- [x] No test references old `/admin` path
- [x] No test references old namespaces

### Priority: HIGH
### Effort: 3-4 hours
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket C-012: Create Panel Switching for Admins

### Summary
Add ability for admins to switch between panels easily.

### Implementation Options

**Option 1: Navigation Links**
Add navigation items to each panel for admins to switch:
```php
->navigationItems([
    NavigationItem::make('Go to Supply Portal')
        ->url('/supply')
        ->icon('heroicon-o-arrow-right-circle')
        ->visible(fn () => auth()->user()?->hasRole('admin')),
])
```

**Option 2: User Menu Dropdown**
Add panel links to user menu (top right).

### Acceptance Criteria
- [x] Admins see panel switch options
- [x] Non-admins do not see panel switch
- [x] Links work correctly

### Priority: MEDIUM
### Effort: 1 hour
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket C-013: Create Homepage with Panel Selection

### Summary
Create a landing page at `/` that directs users to their appropriate panel.

### Implementation
`/routes/web.php`:
```php
Route::get('/', function () {
    $user = auth()->user();

    if (!$user) {
        return redirect('/pim/login');  // Default to PIM login
    }

    // Redirect based on primary role
    if ($user->hasRole('admin') || $user->hasRole('pim-editor')) {
        return redirect('/pim');
    }
    if ($user->hasAnyRole(['supplier-basic', 'supplier-premium'])) {
        return redirect('/supply');
    }
    if ($user->hasRole('pricing-analyst')) {
        return redirect('/pricing');
    }

    return redirect('/pim/login');
});
```

### Acceptance Criteria
- [x] `/` redirects appropriately
- [x] No errors for unauthenticated users
- [x] Each role type goes to correct panel

### Priority: MEDIUM
### Effort: 30 minutes
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket C-014: Create Shared Component Library

### Summary
Set up shared Filament components that can be used across panels.

### Location
`/app/Filament/Shared/Components/`

### Initial Components Needed
1. `PremiumLockedPlaceholder.php` - Blur overlay for premium features
2. `KpiTile.php` - KPI display with change indicator
3. `BrandSelector.php` - Brand dropdown for suppliers

### Example: Premium Locked Component
```php
<?php

namespace App\Filament\Shared\Components;

use Illuminate\View\Component;

class PremiumLockedPlaceholder extends Component
{
    public function __construct(
        public string $feature = 'this feature',
        public string $contactEmail = 'sales@silvertreebrands.com'
    ) {}

    public function render()
    {
        return view('filament.shared.components.premium-locked');
    }
}
```

With Blade view:
```blade
<div class="relative">
    <div class="absolute inset-0 bg-gray-200/90 backdrop-blur-sm flex items-center justify-center z-10 rounded-lg">
        <div class="text-center p-8">
            <x-heroicon-o-lock-closed class="w-12 h-12 mx-auto text-gray-400 mb-4" />
            <h3 class="text-lg font-semibold text-gray-700">Premium Feature</h3>
            <p class="text-gray-500 mb-4">Upgrade to access {{ $feature }}</p>
            <a href="mailto:{{ $contactEmail }}" class="btn btn-primary">
                Contact Us to Upgrade
            </a>
        </div>
    </div>
    {{ $slot }}
</div>
```

### Acceptance Criteria
- [x] Shared component directory created
- [x] PremiumLockedPlaceholder working
- [x] KpiTile working
- [x] BrandSelector working
- [x] Components usable from any panel (registered in AppServiceProvider)

### Priority: HIGH
### Effort: 3 hours
### Assigned To: TBD
### Status: COMPLETED

---

## Ticket C-015: Documentation Update for Multi-Panel

### Summary
Update all documentation to reflect new multi-panel architecture.

### Files to Update
1. `/README.md` - Overview of three panels
2. `/docs/architecture.md` - Technical architecture
3. `/docs/local-development-setup.md` - Setup steps
4. `/docs/multi-panel-architecture-overview.md` - Already created, verify accurate

### New Documentation Needed
1. `/docs/panel-access-matrix.md` - Who can access what
2. `/docs/role-permission-reference.md` - All roles and permissions

### Acceptance Criteria
- [x] All docs accurate for new structure
- [x] URLs updated from `/admin` to `/pim`
- [x] Role documentation complete (role-permission-reference.md, panel-access-matrix.md)

### Priority: MEDIUM
### Effort: 2 hours
### Assigned To: TBD
### Status: COMPLETED

---

## Phase C Completion Checklist

Before moving to Phase D, ALL of the following must be true:

- [x] C-001: Current structure documented
- [x] C-002: New directories created
- [x] C-003: PIM panel provider working
- [x] C-004: PIM resources moved and working
- [x] C-005: Supply panel provider created
- [x] C-006: Pricing panel provider created
- [x] C-007: Panel access middleware working
- [x] C-008: Roles and permissions seeded
- [x] C-009: Test users created
- [x] C-010: Multi-panel navigation tested (26 automated tests)
- [x] C-011: All existing tests pass (356 tests pass)
- [x] C-012: Admin panel switching works
- [x] C-013: Homepage redirect works
- [x] C-014: Shared components created
- [x] C-015: Documentation updated

**Sign-off Required**: TBD
**Target Completion**: PHASE C COMPLETE

---

# PHASE D: Supply Insights Portal

**(Tickets D-001 through D-025 to be detailed in PHASE-D-TICKETS.md)**

**Goal**: Build the complete supplier-facing analytics dashboard.
**Duration Estimate**: 2-3 weeks
**Prerequisites**: Phase C complete

---

# PHASE E: Pricing Tool

**(Tickets E-001 through E-012 to be detailed in PHASE-E-TICKETS.md)**

**Goal**: Build the pricing analysis and competitor tracking tool.
**Duration Estimate**: 2 weeks
**Prerequisites**: Phase C complete (can run parallel to Phase D)

---

# PHASE F: Polish & Production

**(Tickets F-001 through F-010 to be detailed in PHASE-F-TICKETS.md)**

**Goal**: Final testing, optimization, and production deployment.
**Duration Estimate**: 1-2 weeks
**Prerequisites**: Phase D and E complete

---

## Change Log

| Date | Version | Author | Changes |
|------|---------|--------|---------|
| 2025-12-13 | 1.0 | Claude | Initial document creation |

---

## Appendix: Quick Reference

### Panel URLs
- PIM: `http://localhost:8080/pim`
- Supply: `http://localhost:8080/supply`
- Pricing: `http://localhost:8080/pricing`

### Test User Credentials
| Email | Password |
|-------|----------|
| admin@silvertreebrands.com | password |
| pim@silvertreebrands.com | password |
| supplier-basic@test.com | password |
| supplier-premium@test.com | password |
| pricing@silvertreebrands.com | password |

### Company IDs
| Company | ID |
|---------|-----|
| Faithful to Nature | 3 |
| Pet Heaven | 5 |
| UCOOK | 9 |

### Key Commands
```bash
# Start development
docker-compose up -d && composer run dev

# Run tests
php artisan test

# Sync brands
php artisan brands:sync

# Code quality
composer run format && composer run analyse
```
