# Silvertree Platform

A multi-panel SaaS platform for managing product information, supply chain insights, and pricing across Silvertree Brands' e-commerce properties.

## Silvertree Brands

[Silvertree Brands](https://silvertreebrands.com) is a holding company that owns and operates multiple e-commerce businesses in South Africa:

| Brand | Focus | Website |
|-------|-------|---------|
| **Faithful to Nature (FtN)** | Health, organic, eco-friendly products | faithfultonature.co.za |
| **Pet Heaven (PH)** | Pet supplies and accessories | petheaven.co.za |
| **UCOOK** | Meal kit delivery | ucook.co.za |

## Platform Overview

The Silvertree Platform consists of three specialized panels:

### PIM Panel (Product Information Management)
- **Purpose**: Centralized product data management
- **Users**: Internal PIM editors and administrators
- **Features**: Entity management, attribute editing, AI-powered content pipelines, Magento synchronization

### Supply Insights Panel (Coming Soon)
- **Purpose**: Supplier portal for brand performance visibility
- **Users**: External suppliers (basic and premium tiers)
- **Features**: Sales dashboards, inventory alerts, market comparisons

### Pricing Panel (Coming Soon)
- **Purpose**: Competitive pricing analysis and optimization
- **Users**: Internal pricing analysts
- **Features**: Price monitoring, margin analysis, automated recommendations

## Tech Stack

| Component | Technology |
|-----------|------------|
| Framework | Laravel 12 |
| Admin Panel | Filament 4 |
| Language | PHP 8.2+ |
| Database | MySQL 8 |
| Data Warehouse | BigQuery |
| Queue | Redis + Horizon |
| Containerization | Docker |

## Quick Start

### Prerequisites

- Docker Desktop (latest)
- Composer 2.x
- Node.js 20+
- PHP 8.2+ (for local tooling)

### Setup (5 minutes)

```bash
# 1. Clone the repository
git clone <repo-url>
cd spim

# 2. Install dependencies
composer install
npm ci

# 3. Configure environment
cp .env.example .env
php artisan key:generate

# 4. Start Docker containers
docker-compose up -d

# 5. Run migrations and seed
docker exec spim_app php artisan migrate
docker exec spim_app php artisan db:seed --class=RoleSeeder

# 6. Create admin user
docker exec spim_app php artisan tinker --execute="
\$user = \App\Models\User::firstOrCreate(
    ['email' => 'admin@silvertreebrands.com'],
    ['name' => 'Admin', 'password' => bcrypt('password'), 'is_active' => true]
);
\$user->assignRole('admin');
echo 'Admin user ready';
"
```

### Access the Application

| URL | Purpose |
|-----|---------|
| http://localhost:8080/ | Homepage (redirects to appropriate panel) |
| http://localhost:8080/pim | PIM Panel (Product Information Management) |
| http://localhost:8080/supply | Supply Portal (Supplier Insights) |
| http://localhost:8080/pricing | Pricing Tool (Competitive Analysis) |

**Test Users** (password: `password` for all):

| Email | Role | Panel Access |
|-------|------|--------------|
| admin@silvertreebrands.com | admin | All panels |
| pim@silvertreebrands.com | pim-editor | PIM |
| supplier-basic@test.com | supplier-basic | Supply (basic) |
| supplier-premium@test.com | supplier-premium | Supply (premium) |
| pricing@silvertreebrands.com | pricing-analyst | Pricing |

To create test users:
```bash
docker exec spim_app php artisan db:seed --class=TestUserSeeder
```

## Development

### Daily Workflow

```bash
# Start containers
docker-compose up -d

# Start development servers (Laravel + Vite + Queue)
composer run dev
```

### Running Tests

```bash
# All tests
docker exec spim_app php artisan test

# Specific test
docker exec spim_app php artisan test --filter=TestName

# With coverage
docker exec spim_app php artisan test --coverage
```

### Code Quality

```bash
# Auto-fix formatting (Laravel Pint)
composer run format

# Static analysis (PHPStan)
php -d memory_limit=512M vendor/bin/phpstan analyse

# Check formatting without fixing
composer run format:check
```

### Database Operations

```bash
# Run migrations
docker exec spim_app php artisan migrate

# Fresh database
docker exec spim_app php artisan migrate:fresh

# Open MySQL CLI
docker exec -it spim_db mysql -u spim -pspim spim
```

## Architecture

### Entity-Attribute-Value (EAV) System

The platform uses a flexible EAV architecture for product data:

- **Entities**: Products, Categories (extensible to other types)
- **Attributes**: Dynamically defined fields with types, validation, and UI configuration
- **Values**: Versioned storage with approval workflows

### AI Pipelines

Modular pipeline system for automated content processing:

- Configurable pipeline modules (AI prompts, calculations, attribute mapping)
- Dependency resolution between pipelines
- Evaluation tracking for AI-generated content

### Magento Integration

Bidirectional sync with Magento e-commerce platform:

- Product data synchronization
- Attribute option management
- Conflict detection and resolution

## Project Structure

```
spim/
├── app/
│   ├── Filament/
│   │   ├── PimPanel/      # PIM panel resources and pages
│   │   ├── SupplyPanel/   # Supply portal resources
│   │   ├── PricingPanel/  # Pricing tool resources
│   │   └── Shared/        # Shared components across panels
│   ├── Models/            # Eloquent models
│   ├── Pipelines/         # AI pipeline modules
│   ├── Policies/          # Authorization policies
│   └── Services/          # Business logic
├── config/                # Configuration files
├── database/
│   ├── factories/         # Model factories
│   ├── migrations/        # Database migrations
│   └── seeders/           # Database seeders
├── docs/                  # Documentation
├── ops/                   # DevOps configuration
├── resources/             # Views and assets
├── routes/                # Route definitions
├── secrets/               # Credentials (gitignored)
├── storage/               # Logs, cache, uploads
└── tests/                 # Test files
```

## Documentation

### Setup & Configuration
- [Local Development Setup](docs/local-development-setup.md) - Complete setup guide
- [Environment Setup](docs/environment-setup.md) - Environment variables reference
- [Security Audit](docs/security-audit.md) - Security configuration review

### Architecture & Design
- [Architecture Overview](docs/architecture.md) - System architecture
- [Multi-Panel Architecture](docs/multi-panel-architecture-overview.md) - Panel design
- [Database Schema](docs/database-schema.md) - ER diagram and table documentation
- [Entity Abstraction Layer](docs/entity-abstraction-layer.md) - EAV system design

### Features
- [Magento Sync Implementation](docs/magento-sync-implementation.md) - Sync architecture
- [Magento Sync UI](docs/magento-sync-ui.md) - Sync interface guide
- [User Management](docs/user-management.md) - User workflows
- [Role & Permission Reference](docs/role-permission-reference.md) - All roles and permissions
- [Panel Access Matrix](docs/panel-access-matrix.md) - Who can access what

### Project Management
- [PROJECT-TRACKER.md](PROJECT-TRACKER.md) - Master ticket tracker
- [PHASE-D-TICKETS.md](PHASE-D-TICKETS.md) - Supply Insights tickets
- [PHASE-E-F-TICKETS.md](PHASE-E-F-TICKETS.md) - Pricing & Production tickets

## Environment Variables

Key variables to configure in `.env`:

```env
# Company ID (3=FtN, 5=PH, 9=UCOOK)
COMPANY_ID=3

# Magento Integration
MAGENTO_BASE_URL=https://your-magento-store.com
MAGENTO_ACCESS_TOKEN=your-access-token

# BigQuery (Phase B+)
GOOGLE_APPLICATION_CREDENTIALS=./secrets/google-credentials.json
BIGQUERY_PROJECT_ID=silvertree-poc
BIGQUERY_DATASET=sh_output

# AI Features
OPENAI_API_KEY=sk-your-api-key
```

See [Environment Setup Guide](docs/environment-setup.md) for complete reference.

## Docker Services

| Container | Purpose | Port |
|-----------|---------|------|
| `spim_web` | Nginx web server | 8080 |
| `spim_app` | PHP-FPM application | - |
| `spim_db` | MySQL 8.0 database | 3307 |
| `spim_redis` | Redis cache | 6379 |
| `spim_queue` | Queue worker | - |

## Contributing

### Development Rules

1. **Test after every change** - Run `php artisan test` after modifications
2. **Follow ticket specifications** - See PROJECT-TRACKER.md for requirements
3. **Maintain code quality** - Run `composer run format` and `composer run analyse`
4. **Security first** - Never commit secrets, always validate input

### Code Standards

- PSR-12 coding style (enforced by Laravel Pint)
- Type hints on all methods
- DocBlocks for public methods
- Eloquent relationships properly defined

### Commit Messages

```
[TICKET-ID] Brief description

- Detail 1
- Detail 2
```

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
3. Wait for MySQL to initialize after starting containers

### Tests failing

```bash
# Clear caches
docker exec spim_app php artisan optimize:clear

# Fresh test database
docker exec spim_app php artisan migrate:fresh --env=testing
```

### Class not found errors

```bash
composer dump-autoload
docker exec spim_app php artisan optimize:clear
```

## License

Proprietary - Silvertree Brands

## Contact

For questions or support, contact the Silvertree Brands development team.
