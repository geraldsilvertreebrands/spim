# PHASE E: Pricing Tool - Ticket Outline

**Phase Goal**: Build the pricing analysis and competitor price tracking tool
**Duration Estimate**: 2 weeks
**Prerequisites**: Phase C complete (can run parallel to Phase D)

---

## Ticket Overview

| ID | Title | Priority | Effort |
|----|-------|----------|--------|
| E-001 | Create Price Scrape Model and Migration | HIGH | 2h |
| E-002 | Create Pricing Panel Dashboard | HIGH | 6h |
| E-003 | Create Competitor Price List Page | HIGH | 4h |
| E-004 | Create Price History Chart | HIGH | 4h |
| E-005 | Create Price Comparison Matrix | MEDIUM | 4h |
| E-006 | Create Price Alerts System | MEDIUM | 6h |
| E-007 | Create Price Alert Notifications | LOW | 4h |
| E-008 | Create Price Import Service | HIGH | 4h |
| E-009 | Create Margin Analysis Page | MEDIUM | 4h |
| E-010 | Add Pricing Data to BigQuery Queries | HIGH | 4h |
| E-011 | Mobile Responsive Design | MEDIUM | 2h |
| E-012 | Pricing Tool E2E Testing | HIGH | 4h |

---

## E-001: Create Price Scrape Model and Migration

### Migration
```php
Schema::create('price_scrapes', function (Blueprint $table) {
    $table->id();
    $table->char('product_id', 26);  // ULID - references entities
    $table->string('competitor_name');
    $table->text('competitor_url')->nullable();
    $table->string('competitor_sku')->nullable();
    $table->decimal('price', 10, 2);
    $table->string('currency', 3)->default('ZAR');
    $table->boolean('in_stock')->default(true);
    $table->timestamp('scraped_at');
    $table->timestamps();

    $table->index(['product_id', 'scraped_at']);
    $table->index(['competitor_name', 'scraped_at']);
});
```

### Model Features
- Relationships to Entity (product)
- Scopes for date ranges
- Price change detection

---

## E-002: Create Pricing Panel Dashboard

### KPIs
- Products tracked
- Avg price position (vs market)
- Recent price changes
- Active alerts

### Charts
- Price position histogram
- Price changes this week

---

## E-003: Create Competitor Price List Page

### Table Columns
- Product Name
- Our Price
- Competitor 1 Price
- Competitor 2 Price
- Competitor 3 Price
- Price Position (Cheapest/Middle/Most Expensive)

### Features
- Filter by category
- Sort by price difference
- Highlight where we're more expensive

---

## E-004: Create Price History Chart

### Features
- Select product
- Line chart showing price over time
- Multiple competitor lines
- Our price as reference line

---

## E-005: Create Price Comparison Matrix

### Format
```
                    Us      WW     Takealot   Checkers
Organic Coconut    R89     R85      R92       R95
Manuka Honey      R350    R345     R360      R355
...
```

### Color coding
- Green: We're cheapest
- Red: We're most expensive
- Yellow: Mid-range

---

## E-006: Create Price Alerts System

### Alert Types
- Price drops below X
- Competitor beats our price
- Price changes by more than X%
- Out of stock

### Storage
```php
Schema::create('price_alerts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->char('product_id', 26)->nullable();
    $table->string('competitor_name')->nullable();
    $table->enum('alert_type', ['price_below', 'competitor_beats', 'price_change', 'out_of_stock']);
    $table->decimal('threshold', 10, 2)->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_triggered_at')->nullable();
    $table->timestamps();
});
```

---

## E-007: Create Price Alert Notifications

### Channels
- Email (primary)
- In-app notification badge
- Future: Slack/Teams webhook

---

## E-008: Create Price Import Service

### Purpose
Import scraped price data from external source (CSV, API, etc.)

### Note
Actual scraping is out of scope - this just imports pre-scraped data.

---

## E-009: Create Margin Analysis Page

### Features
- Cost vs selling price
- Margin per product
- Margin vs competitors

### Requires
- Product cost data (may need additional data source)

---

## E-010: Add Pricing Data to BigQuery Queries

**STATUS: N/A - NOT APPLICABLE**

~~If pricing data lives in BigQuery rather than local DB, add queries:~~
- ~~`getPriceHistory()`~~
- ~~`getCompetitorPrices()`~~
- ~~`getPriceAlertTriggers()`~~

**Reason:** Competitor pricing data does not exist in BigQuery. The `fact_competitor_prices` table was expected but doesn't exist. Competitor pricing is handled via local database (price_scrapes table) with CSV/API imports. Our own pricing data IS in BigQuery (dim_product, fact_order_item) but that's already used by other methods.

**Verified:** 2024-12-14

---

# PHASE F: Polish & Production - Ticket Outline

**Phase Goal**: Final testing, optimization, and production deployment
**Duration Estimate**: 1-2 weeks
**Prerequisites**: Phase D and E complete

---

## Ticket F-000: Multi-Company Brand Sync Decision (BLOCKING)

**Priority**: HIGH - Blocks final testing
**Status**: ✅ COMPLETED

### DECISION: Option A - Separate Deployment Per Company

**Rationale:**
- Zero code changes required
- Complete data isolation between companies
- Simpler access control and security
- Independent scaling per company
- Lower risk for V1 launch

### Answers to Open Questions

1. **Deployment Model**: ✅ **Option A** - Once per company
   - `ftn.silvertree.com` → COMPANY_ID=3
   - `petheaven.silvertree.com` → COMPANY_ID=5
   - `ucook.silvertree.com` → COMPANY_ID=9

2. **Brand Sync Scope**: Each deployment syncs its own brands
   - FtN deployment syncs 1943 brands (company_id=3)
   - PH deployment syncs 327 brands (company_id=5)
   - UCOOK deployment syncs 237 brands (company_id=9)
   - Automatic via `COMPANY_ID` in each deployment's `.env`

3. **BigQuery Queries**: Continue using COMPANY_ID from .env
   - No changes needed to BigQueryService
   - Each deployment queries only its company's data
   - Simple, fast, secure

### Implementation Notes

**Company ID Reference:**
- 3 = Faithful to Nature (FtN)
- 5 = Pet Heaven (PH)
- 9 = UCOOK

**Deployment Strategy:**
- Deploy same codebase 3 times
- Each deployment has different `.env` with unique `COMPANY_ID`
- Each deployment has its own database (or shared DB with proper scoping)
- Brand sync runs automatically on first deployment via migrations/seeders

**No Code Changes Required:**
- ✅ BigQueryService filters by `$this->companyId` (from config)
- ✅ Brand model has `company_id` field
- ✅ User brand access table ready
- ✅ All queries already filter by company_id

### Decided By
- Developer (2025-12-14)

### Status: ✅ COMPLETED

---

## Ticket Overview

| ID | Title | Priority | Effort |
|----|-------|----------|--------|
| F-001 | Performance Optimization | HIGH | 8h |
| F-002 | Security Audit | CRITICAL | 4h |
| F-003 | Error Monitoring Setup | HIGH | 4h |
| F-004 | Production Environment Setup | CRITICAL | 8h |
| F-005 | SSL Certificate Configuration | CRITICAL | 2h |
| F-006 | Database Backup Strategy | HIGH | 4h |
| F-007 | User Acceptance Testing | CRITICAL | 8h |
| F-008 | Documentation Finalization | MEDIUM | 4h |
| F-009 | Training Materials | MEDIUM | 4h |
| F-010 | Go-Live Checklist and Deployment | CRITICAL | 8h |

---

## F-001: Performance Optimization

### Tasks
- BigQuery query optimization
- Laravel query optimization (N+1)
- Redis caching for sessions
- CDN for static assets
- Image optimization
- Lazy loading implementation

### Targets
- Dashboard load: < 2 seconds
- Chart data API: < 1 second
- Page transitions: < 500ms

---

## F-002: Security Audit

### Checklist
- [ ] All routes require authentication
- [ ] Brand scope enforced everywhere
- [ ] CSRF protection enabled
- [ ] SQL injection prevention verified
- [ ] XSS prevention verified
- [ ] Rate limiting on APIs
- [ ] Secure headers configured
- [ ] Secrets not in code
- [ ] Admin actions logged

---

## F-003: Error Monitoring Setup

### Tools
- Sentry or Bugsnag for PHP errors
- JavaScript error tracking
- BigQuery error alerting
- Uptime monitoring

---

## F-004: Production Environment Setup

### Tasks
- Cloud hosting setup (AWS/GCP/DigitalOcean)
- Docker production configuration
- Environment variables (secrets manager)
- Domain configuration
- Load balancer setup
- Auto-scaling rules

---

## F-005: SSL Certificate Configuration

### Tasks
- Obtain SSL certificate (Let's Encrypt)
- Configure HTTPS redirect
- HSTS headers
- Test SSL Labs rating (target A+)

---

## F-006: Database Backup Strategy

### Strategy
- Daily automated backups
- 30-day retention
- Point-in-time recovery
- Backup testing monthly
- Disaster recovery plan

---

## F-007: User Acceptance Testing

### Test Groups
- Internal PIM team
- Selected suppliers (beta)
- Pricing team

### Feedback Collection
- Bug reports
- UX feedback
- Performance feedback

---

## F-008: Documentation Finalization

### Documents
- User guides per panel
- Admin guide
- API documentation
- Troubleshooting guide
- FAQ

---

## F-009: Training Materials

### Materials
- Video walkthrough per panel
- Quick start guides
- Help tooltips in app
- Support contact information

---

## F-010: Go-Live Checklist and Deployment

### Pre-Launch
- [ ] All tests passing
- [ ] Security audit complete
- [ ] Performance targets met
- [ ] Backups configured
- [ ] Monitoring active
- [ ] Support team briefed
- [ ] Rollback plan documented

### Launch Day
- [ ] Database backup taken
- [ ] Deploy code
- [ ] Run migrations
- [ ] Verify all panels accessible
- [ ] Verify data accuracy
- [ ] Monitor error rates
- [ ] Announce to users

### Post-Launch
- [ ] Monitor for 24 hours
- [ ] Address critical issues immediately
- [ ] Collect feedback
- [ ] Plan first iteration

---

## Project Completion Criteria

The Silvertree Multi-Panel Platform is complete when:

1. **PIM Panel**: All existing features work at new `/pim` path
2. **Supply Panel**: All Basic and Premium features live
3. **Pricing Panel**: All pricing features live
4. **BigQuery**: Integrated and performant
5. **Security**: Audit passed
6. **Testing**: All tests passing
7. **Documentation**: Complete and accurate
8. **Users**: Can self-serve with minimal support

---

## Risk Register

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| BigQuery access denied | HIGH | LOW | Work with IT early |
| BigQuery performance | MEDIUM | MEDIUM | Aggressive caching |
| Supplier adoption | MEDIUM | MEDIUM | Training, good UX |
| Scope creep | HIGH | HIGH | Strict phase gates |
| Staff availability | MEDIUM | MEDIUM | Document everything |
| Data accuracy issues | HIGH | MEDIUM | Validation, testing |

---

## Support Plan

### Level 1 (Self-Service)
- In-app help tooltips
- FAQ documentation
- Video tutorials

### Level 2 (Support Team)
- Email support
- Response within 24 hours
- Handle account issues

### Level 3 (Development)
- Bug fixes
- Data issues
- System errors

---

## Future Roadmap (Out of Scope for V1)

1. **AI Insights**: "Your sales spike in November" type insights
2. **Push Notifications**: Real-time alerts
3. **Automated PO Generation**: From forecasts
4. **Google Ads Integration**: Marketing ROI
5. **Mobile App**: Native iOS/Android
6. **Multi-language**: Afrikaans support
7. **White-label**: Per-company branding
