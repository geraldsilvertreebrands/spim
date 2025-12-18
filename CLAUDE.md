# CLAUDE.md - Silvertree Platform Development Rules

> These rules are automatically loaded for every Claude Code session in this project.
> Last Updated: 2025-12-13

---

## Project Context

**Project**: Silvertree Multi-Panel Platform
**Company**: Silvertree Brands (Faithful to Nature, Pet Heaven, UCOOK)
**Stack**: Laravel 12, Filament 4, PHP 8.2+, MySQL 8, BigQuery
**Ticket System**: See `PROJECT-TRACKER.md`, `PHASE-D-TICKETS.md`, `PHASE-E-F-TICKETS.md`

---

## CRITICAL RULES (Always Follow)

### Rule 0: BigQuery is READ-ONLY (ABSOLUTE)

**THIS IS NON-NEGOTIABLE. VIOLATION = TERMINATION RISK.**

BigQuery (`silvertreepoc` project) contains **PRODUCTION DATA** for Silvertree Brands.

**NEVER, UNDER ANY CIRCUMSTANCES:**
- Run INSERT, UPDATE, DELETE, DROP, TRUNCATE, or ALTER queries
- Modify any table structure
- Create or delete tables/datasets
- Run any DDL or DML statements
- Execute anything that could modify data

**ONLY ALLOWED:**
- SELECT queries (read-only)
- DESCRIBE/schema inspection
- Query metadata

If you're ever unsure whether a query modifies data, **DO NOT RUN IT**.

This database belongs to the company. The developer is a new hire. Any data modification could result in job loss. When in doubt, ask first.

---

### Rule 1: Test After Every Code Change

After ANY code modification, you MUST run tests:

```bash
php artisan test --filter=<RelevantTest>
```

If tests fail:
1. DO NOT move on
2. Fix the failing tests
3. Re-run tests until ALL pass
4. Only then consider the change complete

For significant changes, run the full test suite:
```bash
php artisan test
```

### Rule 2: Ticket-Driven Development

When I reference a ticket (e.g., "do A-003" or "implement D-005"):

1. **Read the ticket** from the appropriate file:
   - Phase A-C: `PROJECT-TRACKER.md`
   - Phase D: `PHASE-D-TICKETS.md`
   - Phase E-F: `PHASE-E-F-TICKETS.md`

2. **Follow the ticket exactly**:
   - Implement the solution as specified
   - Meet ALL acceptance criteria
   - Don't skip steps

3. **Iterate until perfect**:
   - Run tests after each change
   - Fix any issues
   - Verify acceptance criteria
   - Only mark complete when everything passes

4. **Update ticket status**:
   - Change status from `NOT STARTED` to `IN PROGRESS` when starting
   - Change to `COMPLETED` only when ALL acceptance criteria met

### Rule 3: Code Quality Standards

All code MUST meet these standards:

**PHP/Laravel:**
- PSR-12 coding style (enforced by Pint)
- Type hints on all methods
- DocBlocks for public methods
- No `@ts-ignore` or `@phpstan-ignore` without justification
- No hardcoded credentials or secrets
- Use dependency injection, not facades where possible
- Eloquent relationships properly defined
- Database queries optimized (no N+1)

**Before committing:**
```bash
composer run format        # Auto-fix style
composer run analyse       # Static analysis
php artisan test          # All tests pass
```

All three MUST pass before any change is considered complete.

### Rule 4: Security First

- NEVER commit secrets, API keys, or credentials
- ALWAYS use parameterized queries (Eloquent handles this)
- ALWAYS validate user input
- ALWAYS check authorization (policies, middleware)
- NEVER trust client-side data
- ALWAYS escape output (Blade does this by default)

### Rule 5: Iterative Perfection

When implementing a feature:

```
Loop:
  1. Write/modify code
  2. Run relevant tests
  3. If tests fail → fix and goto 1
  4. Run static analysis (composer run analyse)
  5. If analysis fails → fix and goto 1
  6. Run code formatter (composer run format)
  7. Manually verify feature works
  8. If not working → fix and goto 1
  9. Check ALL acceptance criteria from ticket
  10. If any criteria not met → fix and goto 1
  11. DONE - feature complete
```

---

## Development Workflow

### Starting a Ticket

When I say "do ticket X-NNN" or "implement X-NNN":

1. Read the full ticket from the appropriate document
2. Announce: "Starting ticket X-NNN: [title]"
3. List the acceptance criteria
4. Implement step by step
5. Test after each significant change
6. When complete, verify ALL acceptance criteria
7. Announce: "Ticket X-NNN complete. All acceptance criteria met."

### Code Changes

For ANY code change:

```
1. Make the change
2. Run: php artisan test --filter=<relevant>
3. If fail → fix → retest
4. Run: composer run analyse
5. If errors → fix → rerun
6. Run: composer run format
7. Verify manually if applicable
```

### Creating New Files

When creating new files:

1. **Models**: Place in `app/Models/`, add to factory if needed
2. **Migrations**: Use `php artisan make:migration`
3. **Services**: Place in `app/Services/`
4. **Filament Resources**: Place in correct panel folder:
   - PIM: `app/Filament/PimPanel/Resources/`
   - Supply: `app/Filament/SupplyPanel/Resources/`
   - Pricing: `app/Filament/PricingPanel/Resources/`
5. **Tests**: Mirror the `app/` structure in `tests/`
6. **Always** add corresponding tests for new code

### Database Changes

For any database change:

1. Create migration: `php artisan make:migration <name>`
2. Write migration up() and down() methods
3. Run: `php artisan migrate`
4. Verify: `php artisan migrate:status`
5. If model needed, create and add relationships
6. Update relevant factories
7. Run tests

---

## Testing Requirements

### When to Run Tests

| Action | Test Command |
|--------|--------------|
| Any PHP file change | `php artisan test --filter=<Relevant>` |
| Model change | `php artisan test --filter=<ModelName>` |
| Service change | `php artisan test --filter=<ServiceName>` |
| Migration added | `php artisan test` (full suite) |
| Before committing | `php artisan test` (full suite) |
| Ticket completion | `php artisan test` (full suite) |

### Test File Locations

```
tests/
├── Unit/           # Pure logic tests (no DB)
│   ├── Models/
│   ├── Services/
│   └── ...
├── Feature/        # HTTP tests (with DB)
│   ├── Auth/
│   ├── Filament/
│   └── ...
└── Integration/    # External service tests
```

### Writing Tests

Every new feature MUST have tests:

```php
// Minimum test structure
public function test_feature_works_correctly(): void
{
    // Arrange
    $user = User::factory()->create();

    // Act
    $result = $this->actingAs($user)->get('/route');

    // Assert
    $result->assertStatus(200);
    $this->assertDatabaseHas('table', ['column' => 'value']);
}
```

---

## File Structure Reference

```
/spim
├── app/
│   ├── Filament/
│   │   ├── PimPanel/          # PIM panel resources
│   │   ├── SupplyPanel/       # Supply panel resources
│   │   ├── PricingPanel/      # Pricing panel resources
│   │   └── Shared/            # Shared components
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Middleware/
│   ├── Models/
│   ├── Policies/
│   ├── Providers/
│   │   └── Filament/          # Panel providers
│   └── Services/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── docs/                       # Documentation
├── resources/
│   └── views/
│       └── filament/          # Blade views per panel
├── routes/
├── tests/
├── CLAUDE.md                  # This file
├── PROJECT-TRACKER.md         # Master ticket tracker
├── PHASE-D-TICKETS.md         # Supply Insights tickets
└── PHASE-E-F-TICKETS.md       # Pricing + Production tickets
```

---

## Common Commands

```bash
# Development
composer run dev                    # Start all dev servers
docker-compose up -d                # Start Docker services
php artisan serve                   # Start Laravel server only

# Testing
php artisan test                    # Run all tests
php artisan test --filter=Name      # Run specific test
php artisan test --parallel         # Run tests in parallel

# Code Quality
composer run format                 # Fix code style (Pint)
composer run format:check           # Check without fixing
composer run analyse                # PHPStan static analysis

# Database
php artisan migrate                 # Run migrations
php artisan migrate:status          # Check migration status
php artisan migrate:rollback        # Undo last migration
php artisan db:seed                 # Run seeders
php artisan tinker                  # Interactive REPL

# Filament
php artisan make:filament-resource  # Create resource
php artisan make:filament-page      # Create page
php artisan make:filament-widget    # Create widget

# Cache
php artisan cache:clear             # Clear cache
php artisan config:clear            # Clear config cache
php artisan view:clear              # Clear view cache

# BigQuery (after Phase B)
php artisan brands:sync             # Sync brands from BigQuery
```

---

## Environment Variables

Required in `.env`:

```env
# Application
APP_NAME="Silvertree Platform"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080

# Database (Docker)
DB_CONNECTION=mysql
DB_HOST=db
DB_DATABASE=spim
DB_USERNAME=spim
DB_PASSWORD=spim

# Company (3=FtN, 5=PH, 9=UCOOK)
COMPANY_ID=3

# BigQuery (Phase B)
GOOGLE_APPLICATION_CREDENTIALS=/path/to/credentials.json
BIGQUERY_PROJECT_ID=silvertree-poc
BIGQUERY_DATASET=sh_output

# External Services
MAGENTO_BASE_URL=
MAGENTO_ACCESS_TOKEN=
OPENAI_API_KEY=
```

---

## Panel URLs

| Panel | URL | Roles |
|-------|-----|-------|
| PIM | `/pim` | admin, pim-editor |
| Supply | `/supply` | admin, supplier-basic, supplier-premium |
| Pricing | `/pricing` | admin, pricing-analyst |

---

## Test Users

| Email | Password | Role |
|-------|----------|------|
| admin@silvertreebrands.com | password | admin |
| pim@silvertreebrands.com | password | pim-editor |
| supplier-basic@test.com | password | supplier-basic |
| supplier-premium@test.com | password | supplier-premium |
| pricing@silvertreebrands.com | password | pricing-analyst |

---

## Error Handling

When you encounter an error:

1. **Read the error message carefully**
2. **Check the stack trace** for the actual cause
3. **Search the codebase** for similar patterns
4. **Fix the root cause**, not the symptom
5. **Add a test** to prevent regression
6. **Run all tests** to ensure no side effects

Common fixes:
- `Class not found` → Check namespace, run `composer dump-autoload`
- `Table not found` → Run `php artisan migrate`
- `Permission denied` → Check file permissions, Docker volumes
- `Connection refused` → Check Docker is running

---

## Commit Guidelines

When I ask you to commit:

1. Stage relevant files only
2. Write clear commit message:
   ```
   [TICKET-ID] Brief description

   - Detail 1
   - Detail 2

   🤖 Generated with Claude Code
   ```
3. Never commit:
   - `.env` files
   - `vendor/` directory
   - `node_modules/`
   - Credentials or secrets
   - Debug/test code

---

## Quality Checklist

Before considering ANY work complete:

- [ ] All tests pass (`php artisan test`)
- [ ] Static analysis passes (`composer run analyse`)
- [ ] Code is formatted (`composer run format`)
- [ ] No hardcoded values (use config/env)
- [ ] No security vulnerabilities
- [ ] Documentation updated if needed
- [ ] Acceptance criteria from ticket ALL met

---

## Asking for Clarification

If a ticket is unclear or you need more information:

1. **Ask before implementing** - Don't guess
2. **Reference the specific ticket** - "In ticket D-005, the acceptance criteria says X but I'm unclear about Y"
3. **Propose options** - "I see two approaches: A or B. Which do you prefer?"

---

## Remember

1. **Tests are non-negotiable** - Every change must be tested
2. **Iterate until perfect** - Don't move on until it's right
3. **Follow the tickets** - They contain the requirements
4. **Quality over speed** - Do it right, not fast
5. **Ask if unsure** - Clarification beats assumptions

---

## Quick Reference Card

```
┌─────────────────────────────────────────────────────────────┐
│                    DEVELOPMENT LOOP                          │
├─────────────────────────────────────────────────────────────┤
│  1. Read ticket from PROJECT-TRACKER.md or PHASE-X.md       │
│  2. Implement solution                                       │
│  3. Run: php artisan test                                   │
│  4. If fail → fix → goto 3                                  │
│  5. Run: composer run analyse                               │
│  6. If fail → fix → goto 3                                  │
│  7. Run: composer run format                                │
│  8. Verify ALL acceptance criteria                          │
│  9. If any fail → fix → goto 3                              │
│  10. Mark ticket COMPLETED                                  │
└─────────────────────────────────────────────────────────────┘
```
