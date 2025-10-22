SilvertreePIM
-------------

A lean, AI‑assisted Product Information Manager (PIM) built on Laravel + Filament.

Useful URLs:
- The app: http://spim.test:8080/admin
- Horizon queue monitor (for background tasks): http://spim.test:8080/horizon/dashboard

Documentation
- High‑level architecture: see `docs/architecture.md`.
- Iterative implementation plans:
  - `docs/phase2.md` — UI & infrastructure for attributes
  - `docs/phase3.md` — Entity browsing
  - `docs/phase4.md` — Approval workflow
  - `docs/phase5.md` — Magento sync

Requirements
- PHP 8.2+
- Composer
- Node 20+ (for frontend tooling)
- MySQL 8.x (required for window functions and JSON aggs used by views)

Quick start (local dev)Type of App\Filament\Resources\PipelineResource\RelationManagers\PipelineEvalsRelationManager::$icon must be BackedEnum|string|null (as in class Filament\Resources\RelationManagers\RelationManager)

1) Install dependencies
   ```bash
   composer install
   npm ci
   ```
2) Copy env and set app key
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
3) Configure database in `.env` (dev DB, e.g., `spim_dev`). Ensure MySQL 8.
4) Migrate and (optionally) seed dev data
   ```bash
   php artisan migrate
   # optional: php artisan db:seed --class=SampleDevDatasetSeeder
   ```
5) Run app
   ```bash
   php artisan serve
   ```

Testing strategy
- Separate database for tests. Do NOT run tests against your dev DB.
- Real MySQL 8 is required for views; we do not mock the DB for EAV/view logic.
- Unit tests mock pure logic and external services; feature tests hit the DB.

Test environment setup
1) Create `.env.testing` and configure a dedicated test DB (e.g., `spim_test`). Example:
   ```dotenv
   APP_ENV=testing
   APP_KEY=
   APP_DEBUG=true
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=spim_test
   DB_USERNAME=root
   DB_PASSWORD=
   CACHE_STORE=array
   QUEUE_CONNECTION=sync
   SESSION_DRIVER=array
   MAIL_MAILER=array
   ```
2) Generate a testing key
   ```bash
   php artisan key:generate --env=testing
   ```
3) Run fresh migrations for tests
   ```bash
   php artisan migrate:fresh --env=testing
   ```
4) Run tests
   ```bash
   php artisan test
   # or parallel
   php artisan test --parallel
   ```

Makefile (optional)
See `Makefile` for common tasks:
- `make setup` — install deps, generate keys
- `make migrate` — migrate dev DB
- `make test-setup` — prepare test DB (fresh + key)
- `make test` / `make test-parallel`

Developer notes
- EAV schema and views:
  - Versioned/input/timeseries tables with MySQL views to resolve latest and aggregate JSON bags.
- Model ergonomics:
  - `App\Models\Entity` implements Laravel‑like fallbacks so `$entity->name` reads via EAV and `$entity->name = 'X'` writes via the writer.
  - Scopes: `whereAttr`, `orderByAttr` for attribute queries.
- Casting:
  - `App\Support\AttributeCaster` normalizes types for dynamic attributes.
- Writer:
  - `App\Services\EavWriter` handles approval semantics and persistence.

Troubleshooting
- Views require MySQL 8; ensure tests run with MySQL, not sqlite. See `.env.testing`.
- If tests fail on missing views, re‑run: `php artisan migrate:fresh --env=testing`.
