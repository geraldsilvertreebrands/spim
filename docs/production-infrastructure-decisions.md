# Production Infrastructure Decisions

> **Status**: Awaiting decisions from Paul
> **Created**: December 2025
> **Purpose**: Document infrastructure choices needed before production deployment

---

## Overview

The Silvertree Multi-Panel Platform (Phases A-F code complete) requires the following infrastructure decisions before going live.

---

## F-003: Error Monitoring Setup

### Decision Required: Choose Error Monitoring Service

| Option | Pros | Cons | Cost |
|--------|------|------|------|
| **Sentry** (Recommended) | Industry standard, excellent Laravel integration, detailed stack traces, performance monitoring | Learning curve for dashboard | Free tier: 5K errors/month, Team: $26/month |
| **Bugsnag** | Good Laravel support, simple setup | Less feature-rich than Sentry | Free tier: 7.5K errors/month, Team: $59/month |
| **Laravel Telescope** | Built into Laravel, no external service | Not suitable for production alerting, local only | Free |

### What's Needed Once Decided
1. Create account with chosen service
2. Get DSN/API key
3. Add to `.env`: `SENTRY_LARAVEL_DSN=https://xxx@sentry.io/xxx`
4. Install package: `composer require sentry/sentry-laravel`

### Additional Monitoring Needs
- [ ] JavaScript error tracking (Sentry JS SDK)
- [ ] Uptime monitoring (UptimeRobot, Pingdom, or built into hosting)
- [ ] BigQuery error alerting (Cloud Monitoring if on GCP)

---

## F-004: Production Environment Setup

### Decision Required: Choose Hosting Provider

| Option | Pros | Cons | Estimated Cost |
|--------|------|------|----------------|
| **Google Cloud (GCP)** | Already using BigQuery, same ecosystem, Cloud Run is simple | More complex than DigitalOcean | ~$50-150/month |
| **AWS** | Scraping project already there, mature ecosystem | Complex, many services to choose | ~$50-200/month |
| **DigitalOcean** | Simple, predictable pricing, App Platform easy | Less enterprise features | ~$30-100/month |

### Recommended Architecture (any provider)

```
┌─────────────────────────────────────────────────────────┐
│                    Load Balancer                         │
│                   (SSL Termination)                      │
└─────────────────────┬───────────────────────────────────┘
                      │
        ┌─────────────┴─────────────┐
        │                           │
┌───────▼───────┐           ┌───────▼───────┐
│  App Server 1  │           │  App Server 2  │
│   (Laravel)    │           │   (Laravel)    │
└───────┬───────┘           └───────┬───────┘
        │                           │
        └─────────────┬─────────────┘
                      │
              ┌───────▼───────┐
              │    MySQL DB    │
              │  (Managed RDS) │
              └───────┬───────┘
                      │
              ┌───────▼───────┐
              │     Redis      │
              │   (Sessions)   │
              └───────────────┘
```

### Per-Provider Setup

#### If GCP:
- Cloud Run for app containers
- Cloud SQL for MySQL
- Memorystore for Redis
- Cloud Load Balancing
- **Advantage**: Same project as BigQuery, simpler auth

#### If AWS:
- ECS or Elastic Beanstalk for app
- RDS for MySQL
- ElastiCache for Redis
- ALB for load balancing

#### If DigitalOcean:
- App Platform or Droplets
- Managed MySQL
- Managed Redis
- Built-in load balancer

### What's Needed Once Decided
1. Create cloud account/project
2. Set up VPC/networking
3. Create managed MySQL database
4. Create Redis instance
5. Configure container/app deployment
6. Set up CI/CD pipeline

---

## F-005: SSL Certificate Configuration

### Decision Required: Domain Names

**Questions for Paul:**
1. What domains will be used?
   - `pim.silvertreebrands.com`?
   - `supply.silvertreebrands.com`?
   - `pricing.silvertreebrands.com`?
   - Or single domain with paths (`app.silvertreebrands.com/pim`)?

2. Separate deployments per company?
   - `ftn.silvertreebrands.com` (COMPANY_ID=3)
   - `petheaven.silvertreebrands.com` (COMPANY_ID=5)
   - `ucook.silvertreebrands.com` (COMPANY_ID=9)

### SSL Options

| Option | Pros | Cons |
|--------|------|------|
| **Let's Encrypt** (Recommended) | Free, auto-renewal, widely supported | 90-day certificates |
| **Cloudflare** | Free, DDoS protection, CDN included | Proxied traffic |
| **AWS ACM / GCP Managed** | Integrated with cloud provider | Vendor lock-in |

### What's Needed Once Decided
1. Register/configure domains
2. Set up DNS records
3. Configure SSL (usually automatic with managed hosting)
4. Set up HTTPS redirect
5. Configure HSTS headers

---

## F-006: Database Backup Strategy

### Decision Required: Backup Approach

**Recommended Strategy:**
- Daily automated backups
- 30-day retention
- Point-in-time recovery enabled
- Weekly backup test verification

### By Provider

| Provider | Backup Solution | Cost |
|----------|-----------------|------|
| GCP Cloud SQL | Automated backups, PITR | Included |
| AWS RDS | Automated backups, PITR | Included |
| DigitalOcean | Managed DB backups | Included |

### What's Needed Once Decided
1. Enable automated backups in managed database
2. Configure retention period (recommend 30 days)
3. Enable point-in-time recovery
4. Document restore procedure
5. Schedule monthly backup test

---

## Deployment Strategy Reminder

Per F-000 decision (already made):

**Separate deployment per company:**
- FtN deployment → `COMPANY_ID=3`
- Pet Heaven deployment → `COMPANY_ID=5`
- UCOOK deployment → `COMPANY_ID=9`

Same codebase, different `.env` files.

---

## Summary: Decisions Needed from Paul

| Item | Decision | Priority |
|------|----------|----------|
| Error monitoring service | Sentry vs Bugsnag | HIGH |
| Hosting provider | GCP vs AWS vs DigitalOcean | HIGH |
| Domain structure | Separate vs single domain | HIGH |
| Per-company domains | What URLs? | HIGH |
| Backup retention | 30 days ok? | MEDIUM |

---

## Next Steps After Decisions

1. **Paul decides** on hosting and monitoring
2. **Gerald creates** cloud resources and configures app
3. **Test deployment** with staging environment
4. **UAT** with real users
5. **Go live** with production deployment

---

## Questions for Paul

1. Is there an existing Silvertree cloud account we should use?
2. Are there IT/DevOps team members who should be involved?
3. What's the target go-live date?
4. Budget constraints for hosting?
5. Any compliance requirements (POPIA, etc.)?
