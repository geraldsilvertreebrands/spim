# Local Development Setup

> Complete guide for setting up the Silvertree Platform for local development.
> Estimated setup time: ~20-30 minutes

## Prerequisites

Before starting, ensure you have the following installed:

| Tool | Version | Check Command |
|------|---------|---------------|
| PHP | 8.2+ (8.3 recommended) | `php -v` |
| Composer | 2.x | `composer --version` |
| Node.js | 20+ | `node -v` |
| npm | 10+ | `npm -v` |
| Docker Desktop | Latest | `docker --version` |

### Optional (for BigQuery features)
- Google Cloud SDK (`gcloud` CLI)

## First-Time Setup

### Step 1: Clone Repository

```bash
git clone <repo-url>
cd spim
```

### Step 2: Install PHP Dependencies

```bash
composer install
```

> **Note:** If you see PHP 8.5 deprecation warnings, these are safe to ignore. The application runs in Docker with PHP 8.3.

### Step 3: Install Node Dependencies

```bash
npm ci
```

### Step 4: Configure Environment

```bash
# Copy example environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

Edit `.env` and configure:
- `COMPANY_ID=3` (Faithful to Nature) or your target company
- See [Environment Setup Guide](./environment-setup.md) for all variables

### Step 5: Start Docker Services

```bash
# Start all containers in background
docker-compose up -d

# Verify containers are running
docker ps
```

You should see 5 containers:
| Container | Purpose | Port |
|-----------|---------|------|
| `spim_web` | Nginx web server | 8080 |
| `spim_app` | PHP-FPM application | - |
| `spim_db` | MySQL 8.0 database | 3307 |
| `spim_redis` | Redis cache | 6379 |
| `spim_queue` | Queue worker | - |

### Step 6: Run Migrations

```bash
docker exec spim_app php artisan migrate
```

### Step 7: Seed Database

```bash
# Seed roles and permissions
docker exec spim_app php artisan db:seed --class=RoleSeeder

# Create test users for all roles
docker exec spim_app php artisan db:seed --class=TestUserSeeder
```

### Step 8: Access Application

Open your browser and navigate to:

| URL | Purpose |
|-----|---------|
| http://localhost:8080/ | Homepage (redirects based on role) |
| http://localhost:8080/pim | PIM Panel |
| http://localhost:8080/supply | Supply Portal |
| http://localhost:8080/pricing | Pricing Tool |

**Test Users** (all use password: `password`):

| Email | Role | Panel Access |
|-------|------|--------------|
| admin@silvertreebrands.com | admin | All panels |
| pim@silvertreebrands.com | pim-editor | PIM |
| supplier-basic@test.com | supplier-basic | Supply (basic) |
| supplier-premium@test.com | supplier-premium | Supply (premium) |
| pricing@silvertreebrands.com | pricing-analyst | Pricing |

## Daily Workflow

### Starting Your Day

```bash
# Start Docker containers
docker-compose up -d

# Start development servers (in a new terminal)
composer run dev
```

The `composer run dev` command starts:
- Laravel development server
- Queue listener
- Log viewer (Pail)
- Vite dev server (for frontend assets)

### Running Tests

```bash
# Run all tests
docker exec spim_app php artisan test

# Run specific test file
docker exec spim_app php artisan test --filter=ApplicationLoadsTest

# Run tests with coverage
docker exec spim_app php artisan test --coverage
```

### Code Quality

```bash
# Auto-fix code formatting (Laravel Pint)
composer run format

# Check formatting without fixing
composer run format:check

# Run static analysis (PHPStan)
php -d memory_limit=512M vendor/bin/phpstan analyse
```

### Database Operations

```bash
# Run new migrations
docker exec spim_app php artisan migrate

# Rollback last migration
docker exec spim_app php artisan migrate:rollback

# Fresh database (drops all tables)
docker exec spim_app php artisan migrate:fresh

# Open database CLI
docker exec -it spim_db mysql -u spim -pspim spim
```

### Artisan Tinker

```bash
# Interactive PHP shell with Laravel context
docker exec -it spim_app php artisan tinker
```

### View Logs

```bash
# Application logs
docker exec spim_app tail -f storage/logs/laravel.log

# Or use Pail (included in composer run dev)
docker exec spim_app php artisan pail
```

### Ending Your Day

```bash
# Stop development servers
Ctrl+C

# Stop Docker containers (optional - they auto-restart)
docker-compose stop

# Or remove containers completely
docker-compose down
```

## Docker Commands Reference

| Command | Description |
|---------|-------------|
| `docker-compose up -d` | Start all containers |
| `docker-compose stop` | Stop containers (keep data) |
| `docker-compose down` | Stop and remove containers |
| `docker-compose down -v` | Stop, remove containers AND volumes (deletes DB) |
| `docker-compose logs -f app` | Follow app container logs |
| `docker exec spim_app <cmd>` | Run command in app container |
| `docker exec -it spim_app bash` | Open shell in app container |

## Composer Scripts Reference

| Script | Description |
|--------|-------------|
| `composer run dev` | Start all development servers |
| `composer run test` | Run test suite |
| `composer run format` | Auto-fix code style |
| `composer run format:check` | Check code style |
| `composer run analyse` | Run PHPStan analysis |

## Troubleshooting

### Docker containers won't start

```bash
# Check for port conflicts
lsof -i :8080
lsof -i :3307

# Rebuild containers
docker-compose build --no-cache
docker-compose up -d
```

### Database connection refused

1. Ensure containers are running: `docker ps`
2. Check `.env` has `DB_HOST=db` (not localhost)
3. Wait a few seconds after starting containers for MySQL to initialize

```bash
# Check database logs
docker-compose logs db
```

### Permission denied errors

```bash
# Fix storage permissions
docker exec spim_app chmod -R 775 storage bootstrap/cache
docker exec spim_app chown -R www-data:www-data storage bootstrap/cache
```

### Class not found errors

```bash
# Regenerate autoload files
composer dump-autoload

# Clear all caches
docker exec spim_app php artisan optimize:clear
```

### Tests failing with database errors

```bash
# Ensure test database is set up
docker exec spim_app php artisan migrate:fresh --env=testing
```

### PHP 8.5 deprecation warnings

These warnings appear when running composer on the host machine with PHP 8.5:
- Safe to ignore - the application runs in Docker with PHP 8.3
- The warnings are from vendor packages and will be fixed upstream

### Out of memory errors

```bash
# Increase PHP memory limit for specific command
php -d memory_limit=512M vendor/bin/phpstan analyse
```

## IDE Setup

### VS Code

Recommended extensions:
- PHP Intelephense
- Laravel Blade Snippets
- Tailwind CSS IntelliSense
- Docker

### PHPStorm

1. Configure PHP interpreter to use Docker
2. Set up remote debugging with Xdebug (already configured in docker-compose)
3. Configure database connection to `localhost:3307`

## Project Structure

```
spim/
├── app/                    # Application code
│   ├── Filament/
│   │   ├── PimPanel/      # PIM panel resources
│   │   ├── SupplyPanel/   # Supply portal resources
│   │   ├── PricingPanel/  # Pricing tool resources
│   │   └── Shared/        # Shared components
│   ├── Models/            # Eloquent models
│   ├── Services/          # Business logic
│   └── Pipelines/         # AI pipeline system
├── config/                 # Configuration files
├── database/
│   ├── factories/         # Model factories
│   ├── migrations/        # Database migrations
│   └── seeders/           # Database seeders
├── docs/                   # Documentation
├── ops/                    # DevOps configuration
├── public/                 # Web root
├── resources/              # Views, assets
├── routes/                 # Route definitions
├── secrets/                # Credentials (gitignored)
├── storage/                # Logs, cache, uploads
├── tests/                  # Test files
├── .env                    # Environment config (gitignored)
├── .env.example            # Environment template
├── docker-compose.yaml     # Docker configuration
└── composer.json           # PHP dependencies
```

## Getting Help

- Check existing documentation in `/docs/`
- Review `CLAUDE.md` for development rules
- Check `PROJECT-TRACKER.md` for current tasks
