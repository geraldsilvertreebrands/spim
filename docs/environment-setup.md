# Environment Setup Guide

> This guide explains how to configure environment variables for the Silvertree Platform.

## Quick Start

1. Copy the example environment file:
   ```bash
   cp .env.example .env
   ```

2. Generate an application key:
   ```bash
   php artisan key:generate
   ```

3. Configure the required variables (see sections below)

4. Run migrations:
   ```bash
   php artisan migrate
   ```

## Environment Variables Reference

### Core Application

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APP_NAME` | Yes | spim | Application name |
| `APP_ENV` | Yes | local | Environment (local/staging/production) |
| `APP_KEY` | Yes | - | Auto-generated encryption key |
| `APP_DEBUG` | Yes | true | Enable debug mode (false in production) |
| `APP_URL` | Yes | http://localhost:8080 | Base application URL |

### Database (Docker)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `DB_CONNECTION` | Yes | mysql | Database driver |
| `DB_HOST` | Yes | db | Docker container name or IP |
| `DB_PORT` | Yes | 3306 | MySQL port |
| `DB_DATABASE` | Yes | spim | Database name |
| `DB_USERNAME` | Yes | spim | Database user |
| `DB_PASSWORD` | Yes | spim | Database password |

### Silvertree Configuration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `COMPANY_ID` | Yes | 3 | Company filter (3=FtN, 5=PH, 9=UCOOK) |

### Magento Integration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `MAGENTO_BASE_URL` | No* | - | Magento store base URL |
| `MAGENTO_ACCESS_TOKEN` | No* | - | Magento REST API token |

*Required for sync features

### BigQuery Integration (Phase B+)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `GOOGLE_APPLICATION_CREDENTIALS` | No* | - | Path to service account JSON |
| `BIGQUERY_PROJECT_ID` | No* | silvertree-poc | GCP project ID |
| `BIGQUERY_DATASET` | No* | sh_output | BigQuery dataset name |

*Required for BigQuery features

### AI/OpenAI Integration

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `OPENAI_API_KEY` | No* | - | OpenAI API key for AI pipelines |

*Required for AI pipeline features

## Setting Up Credentials

### 1. Company ID

Set `COMPANY_ID` based on which brand you're working with:

```env
# Faithful to Nature
COMPANY_ID=3

# Pet Heaven
COMPANY_ID=5

# UCOOK
COMPANY_ID=9
```

### 2. Magento Credentials

1. Log into Magento Admin
2. Go to **System → Integrations**
3. Create or find an integration with API access
4. Copy the Access Token

```env
MAGENTO_BASE_URL=https://your-magento-store.com
MAGENTO_ACCESS_TOKEN=your-access-token-here
```

### 3. Google Cloud / BigQuery

1. Go to [Google Cloud Console](https://console.cloud.google.com)
2. Navigate to **IAM & Admin → Service Accounts**
3. Create a new service account or use existing
4. Download the JSON key file
5. Save it to `/secrets/google-credentials.json`

```env
GOOGLE_APPLICATION_CREDENTIALS=./secrets/google-credentials.json
BIGQUERY_PROJECT_ID=silvertree-poc
BIGQUERY_DATASET=sh_output
```

**Required BigQuery Permissions:**
- `bigquery.datasets.get`
- `bigquery.tables.list`
- `bigquery.tables.get`
- `bigquery.tables.getData`

### 4. OpenAI API Key

1. Go to [OpenAI Platform](https://platform.openai.com)
2. Navigate to **API Keys**
3. Create a new key

```env
OPENAI_API_KEY=sk-your-api-key-here
```

## Security Notes

1. **Never commit `.env` to version control** - it's already in `.gitignore`
2. **Store credentials in `/secrets/`** - this directory is gitignored
3. **Use different keys for each environment** - don't share production keys
4. **Rotate keys regularly** - especially if they may have been exposed

## Docker Configuration

When using Docker, the `.env` file is automatically loaded. For the database, use:

```env
# Docker container name (not localhost)
DB_HOST=db
```

## Verifying Configuration

Test your configuration:

```bash
# Test database connection
php artisan db:show

# Test application loads
php artisan about

# Run tests
php artisan test
```

## Troubleshooting

### Database Connection Failed
- Ensure Docker containers are running: `docker ps`
- Check DB_HOST matches container name (usually `db`)
- Verify credentials match docker-compose.yml

### BigQuery Authentication Failed
- Ensure JSON file exists at specified path
- Check file permissions are readable
- Verify service account has required permissions

### Magento API Errors
- Verify base URL doesn't have trailing slash
- Check access token is active in Magento
- Ensure integration has required API permissions
