# Product Requirements Document (PRD)
## Silvertree Multi-Panel Platform

**Document Version**: 1.0
**Last Updated**: December 2025
**Author**: Engineering Team
**Status**: Draft for Review

---

## 1. Executive Summary

### 1.1 Vision
Transform the existing SPIM (Silvertree Product Information Manager) into a comprehensive **multi-panel SaaS platform** serving three distinct user groups with specialized tools:

1. **PIM Panel**: Internal teams managing product information
2. **Supply Insights Panel**: Suppliers/brands accessing performance analytics
3. **Pricing Panel**: Analysts monitoring competitor pricing

### 1.2 Business Goals
- Provide suppliers with data insights in exchange for rebates
- Enable internal teams to manage products efficiently with AI assistance
- Monitor market pricing for competitive positioning
- Generate revenue through premium feature tiers

### 1.3 Success Metrics
| Metric | Target | Measurement |
|--------|--------|-------------|
| Supplier portal adoption | 80% of active brands | Brand login rate |
| Premium conversion | 25% within 3 months | Free → Premium transitions |
| Time to first insight | < 5 seconds | Page load + KPI render |
| User satisfaction | > 4/5 stars | Survey feedback |
| PIM zero-downtime migration | 100% | Continuous availability |

---

## 2. User Personas

### 2.1 Admin User
**Name**: Sarah (Operations Manager)
**Goals**:
- Manage all users across all panels
- Configure brand access and competitor mappings
- Monitor system health and sync status
- Full access to all features

**Frustrations**:
- Managing multiple disconnected systems
- No unified view of user activities
- Manual brand configuration

### 2.2 PIM Editor
**Name**: James (Product Manager)
**Goals**:
- Maintain accurate product information
- Use AI pipelines to generate attributes
- Sync products with Magento
- Review and approve AI-generated content

**Frustrations**:
- Manual data entry
- Inconsistent product information
- Slow sync processes

### 2.3 Supplier (Basic Tier)
**Name**: Maria (Brand Account Manager)
**Goals**:
- View sales performance for their brand
- Understand market position
- Track stock levels and purchase orders
- Identify growth opportunities

**Frustrations**:
- Limited visibility into retail performance
- No competitor benchmarking
- Manual report requests

### 2.4 Supplier (Premium Tier)
**Name**: David (Brand Director)
**Goals**:
- All basic tier features PLUS:
- Detailed customer cohort analysis
- Predictive analytics
- RFM segmentation
- Marketing campaign insights

**Frustrations**:
- Inability to justify marketing spend
- No predictive capabilities
- Limited customer understanding

### 2.5 Pricing Analyst
**Name**: Lisa (Category Manager)
**Goals**:
- Monitor competitor prices
- Identify pricing opportunities
- Set price alerts
- Analyze pricing trends

**Frustrations**:
- Manual price checking
- Delayed competitive intelligence
- No historical trend analysis

---

## 3. Product Requirements

### 3.1 Foundation Requirements (All Panels)

#### 3.1.1 Multi-Panel Architecture
| ID | Requirement | Priority | Acceptance Criteria |
|----|-------------|----------|---------------------|
| F001 | Separate URL paths per panel | P0 | `/pim/*`, `/supply/*`, `/pricing/*` |
| F002 | Panel-specific authentication | P0 | Users only access authorized panels |
| F003 | Independent menu structures | P0 | Each panel has unique navigation |
| F004 | Shared user database | P0 | Single user account across panels |
| F005 | Panel-specific theming | P1 | Distinct branding per panel |

#### 3.1.2 User Management
| ID | Requirement | Priority | Acceptance Criteria |
|----|-------------|----------|---------------------|
| U001 | Role-based access control | P0 | Admin, PIM Editor, Supplier Basic, Supplier Premium, Pricing Analyst |
| U002 | Panel access restrictions | P0 | Roles determine panel access |
| U003 | Brand-scoped supplier access | P0 | Suppliers only see their assigned brands |
| U004 | User activity logging | P2 | Audit trail of user actions |
| U005 | Password reset flow | P1 | Self-service password recovery |

#### 3.1.3 BigQuery Integration
| ID | Requirement | Priority | Acceptance Criteria |
|----|-------------|----------|---------------------|
| BQ001 | BigQuery client service | P0 | Execute queries, return results |
| BQ002 | Query result caching | P0 | 15-minute cache, configurable |
| BQ003 | Company ID filtering | P0 | All queries scoped to `company_id` |
| BQ004 | Query cost monitoring | P2 | Track bytes scanned, alert on thresholds |
| BQ005 | Connection pooling | P1 | Efficient connection management |

#### 3.1.4 Brand Management
| ID | Requirement | Priority | Acceptance Criteria |
|----|-------------|----------|---------------------|
| BR001 | Brands table (read-only cache) | P0 | Synced from BigQuery `dim_product` |
| BR002 | Brand sync action | P0 | Admin button to refresh brands |
| BR003 | Competitor brand mapping | P0 | Up to 3 competitors per brand |
| BR004 | Access level per brand | P0 | `basic` or `premium` tier |
| BR005 | Brand-user association | P0 | Link suppliers to their brands |

---

### 3.2 PIM Panel Requirements

#### 3.2.1 Existing Features (Must Preserve)
| ID | Requirement | Status | Notes |
|----|-------------|--------|-------|
| PIM001 | Entity type management | DONE | Products, Categories, etc. |
| PIM002 | Attribute configuration | DONE | Full CRUD with validation |
| PIM003 | Entity CRUD with EAV | DONE | Dynamic forms and tables |
| PIM004 | AI pipelines | DONE | Modules, execution, evals |
| PIM005 | Magento sync | DONE | Bidirectional with conflict detection |
| PIM006 | Approval workflow | PARTIAL | Queue UI in progress |

#### 3.2.2 Migration Requirements
| ID | Requirement | Priority | Acceptance Criteria |
|----|-------------|----------|---------------------|
| PIM-M001 | Zero downtime migration | P0 | No interruption to existing users |
| PIM-M002 | Preserve all routes | P0 | Existing URLs redirect to new paths |
| PIM-M003 | Maintain test coverage | P0 | All existing tests pass |
| PIM-M004 | Update documentation | P1 | Docs reflect new structure |

---

### 3.3 Supply Insights Panel Requirements

#### 3.3.1 Navigation Structure (Free Tier)
| ID | Page | Priority | Description |
|----|------|----------|-------------|
| SI001 | Overview | P0 | KPI tiles, summary charts |
| SI002 | Products | P0 | Product performance table |
| SI003 | Trends | P0 | Sales trends over time |
| SI004 | Benchmarks | P0 | Competitor comparison (anonymized) |
| SI005 | Premium Features | P0 | Locked preview with upgrade CTA |

#### 3.3.2 Navigation Structure (Premium Tier - Additional)
| ID | Page | Priority | Description |
|----|------|----------|-------------|
| SI006 | Forecasting | P1 | Predictive sales models |
| SI007 | Cohorts | P1 | Customer cohort analysis |
| SI008 | RFM | P1 | Recency, Frequency, Monetary segmentation |
| SI009 | Retention | P1 | Customer retention metrics |
| SI010 | Product Deep Dive | P1 | SKU-level detailed analytics |
| SI011 | Supply Chain | P0 | Stock, sell-in, sell-out |
| SI012 | Market & Benchmarks | P1 | Expanded market share |
| SI013 | Behavior | P2 | Customer behavior patterns |
| SI014 | Marketing | P2 | Campaign performance |

#### 3.3.3 Pet Heaven Premium (Additional)
| ID | Page | Priority | Description |
|----|------|----------|-------------|
| SI015 | Subscriptions | P1 | Subscription analytics |
| SI016 | Subscription Products | P1 | Product subscription rates |
| SI017 | Predictive | P2 | Advanced predictive models |

#### 3.3.4 Overview Page Requirements
| ID | Requirement | Priority | Acceptance Criteria |
|----|-------------|----------|---------------------|
| OV001 | KPI tiles at top | P0 | Net revenue, orders, AOV, units |
| OV002 | MoM change indicators | P0 | % change with arrow (up/down) |
| OV003 | Revenue trend chart | P0 | 12-month line chart |
| OV004 | Brand selector dropdown | P0 | Top of sidebar (multi-brand suppliers) |
| OV005 | Time period filter | P0 | Last 30/90/365 days, custom range |

#### 3.3.5 Sales Analytics Requirements
| ID | Requirement | Priority | Acceptance Criteria |
|----|-------------|----------|---------------------|
| SA001 | Overall revenue (net) | P0 | Brand total revenue |
| SA002 | Competitor revenue | P0 | Labeled "Competitor A/B/C" (anonymized) |
| SA003 | Revenue by product table | P0 | Monthly columns, last 12 months |
| SA004 | Export table to CSV | P1 | Download button |
| SA005 | Chart image export | P1 | PNG/SVG download |

#### 3.3.6 Market Share Requirements
| ID | Requirement | Priority | Acceptance Criteria |
|----|-------------|----------|---------------------|
| MS001 | Category tree view | P0 | Expandable hierarchy |
| MS002 | Market share columns | P0 | Brand + 3 competitors (%) |
| MS003 | Category search | P1 | Filter tree by search term |
| MS004 | Time period selector | P0 | Same as overview |

#### 3.3.7 Customer Engagement Requirements
| ID | Requirement | Priority | Acceptance Criteria |
|----|-------------|----------|---------------------|
| CE001 | SKU engagement table | P0 | One row per product |
| CE002 | Avg qty per order | P0 | Mean quantity in orders |
| CE003 | Reorder rate % | P0 | % customers with 2+ orders within 6 months |
| CE004 | Avg frequency | P0 | Mean months between repeat orders |
| CE005 | Promo intensity % | P0 | % sales on discount |

#### 3.3.8 Stock and Supply Requirements
| ID | Requirement | Priority | Acceptance Criteria |
|----|-------------|----------|---------------------|
| SS001 | Sell-in table | P0 | Units received, 12 months |
| SS002 | Sell-out table | P0 | Units sold, 12 months |
| SS003 | Closing stock table | P0 | End-of-month inventory |
| SS004 | Month-over-month comparison | P1 | Visual indicators |

#### 3.3.9 Purchase Orders Requirements
| ID | Requirement | Priority | Acceptance Criteria |
|----|-------------|----------|---------------------|
| PO001 | PO overview chart | P0 | Bar: count, Lines: OTIF % |
| PO002 | PO list table | P0 | PO#, date, status, lines, value |
| PO003 | PO detail drill-down | P0 | Line items on click |
| PO004 | On-time % metric | P0 | % delivered by due date |
| PO005 | In-full % metric | P0 | % delivered complete |
| PO006 | PO document download | P2 | Export PO PDF (future) |

#### 3.3.10 Premium Lock/Blur Feature
| ID | Requirement | Priority | Acceptance Criteria |
|----|-------------|----------|---------------------|
| PL001 | Blur locked sections | P0 | Visual blur effect on premium content |
| PL002 | Lock icon overlay | P0 | Padlock icon on blurred sections |
| PL003 | "Upgrade" CTA | P0 | Button to contact for upgrade |
| PL004 | Permission-based rendering | P0 | Check `can('view-premium-features')` |
| PL005 | Show blurred preview | P0 | Users see what they're missing |

---

### 3.4 Pricing Panel Requirements

#### 3.4.1 Core Features
| ID | Requirement | Priority | Acceptance Criteria |
|----|-------------|----------|---------------------|
| PR001 | Competitor price tracking | P0 | Display scraped prices |
| PR002 | Price history visualization | P0 | Line chart over time |
| PR003 | Product price comparison | P0 | Our price vs competitors |
| PR004 | Price alerts | P1 | Notify when price changes |
| PR005 | Margin analysis | P1 | Cost vs selling price |

#### 3.4.2 Dashboard
| ID | Requirement | Priority | Acceptance Criteria |
|----|-------------|----------|---------------------|
| PR-D001 | Price position summary | P0 | Above/below market average |
| PR-D002 | Recent price changes | P0 | List of recent competitor changes |
| PR-D003 | Alert summary | P1 | Pending price alerts |

---

### 3.5 Admin Panel Requirements

#### 3.5.1 User Management
| ID | Requirement | Priority | Acceptance Criteria |
|----|-------------|----------|---------------------|
| AD001 | CRUD users | P0 | Create, read, update, delete users |
| AD002 | Assign roles | P0 | Select role per user |
| AD003 | Link users to brands | P0 | For supplier users |
| AD004 | Bulk user import | P2 | CSV upload |
| AD005 | User activity log | P2 | View user login history |

#### 3.5.2 Brand Management
| ID | Requirement | Priority | Acceptance Criteria |
|----|-------------|----------|---------------------|
| AD-B001 | Resync brands from BigQuery | P0 | Button in admin UI |
| AD-B002 | Configure competitor brands | P0 | Select up to 3 per brand |
| AD-B003 | Set access level | P0 | Basic or Premium per brand |
| AD-B004 | View brand users | P1 | List users per brand |

---

## 4. UI/UX Specifications

### 4.1 Design Principles
1. **Vertical Scrolling**: Single-page scroll per section (not tabs)
2. **KPI Tiles First**: Large numbers at top, charts below
3. **Blurred Locked Previews**: Show what premium offers
4. **Mobile-Responsive**: Works on tablets (stretch goal: phones)

### 4.2 Color Palette
| Element | FtN | Pet Heaven |
|---------|-----|------------|
| Primary | `#006654` | `#50b848` |
| Background | `#F5F5F5` | `#F5F5F5` |
| Cards | `#FFFFFF` | `#FFFFFF` |
| Borders | `#CCCCCC` | `#CCCCCC` |
| Text Primary | `#333333` | `#333333` |
| Text Secondary | `#666666` | `#666666` |
| Locked Overlay | `#E0E0E0` (90% opacity) | `#E0E0E0` (90% opacity) |

### 4.3 Data Visualization Colors
Based on palette: `#264653`, `#287271`, `#2a9d8f`, `#8ab17d`, `#e9c46a`, `#f4a261`, `#ec8151`, `#e36040`, `#bc6b85`, `#9576c9`

### 4.4 Typography
| Element | Size | Weight |
|---------|------|--------|
| Page Headers | 24px | Bold |
| Section Headers | 18px | Bold |
| KPI Values | 32-48px | Bold |
| Body Text | 14-16px | Regular |
| Labels | 12px | Medium |

### 4.5 Component Library
Use Filament's built-in components where possible:
- Tables: `Filament\Tables`
- Forms: `Filament\Forms`
- Stats: `Filament\Widgets\StatsOverviewWidget`
- Charts: `Filament\Widgets\ChartWidget` (Chart.js)

Custom components needed:
- `<x-premium-locked-placeholder>` - Blurred lock overlay
- `<x-kpi-tile>` - KPI with change indicator
- `<x-category-tree>` - Expandable market share tree

---

## 5. Data Requirements

### 5.1 BigQuery Tables Used

| Table | Description | Key Columns |
|-------|-------------|-------------|
| `sh_output.dim_product` | Product master data | `product_id`, `sku`, `name`, `brand`, `category`, `company_id` |
| `sh_output.fact_sales` | Sales transactions | `product_id`, `order_id`, `qty`, `revenue`, `date`, `company_id` |
| `sh_output.dim_customer` | Customer data | `customer_id`, `demographics`, `company_id` |
| `sh_output.fact_inventory` | Stock levels | `product_id`, `qty`, `date`, `company_id` |
| `sh_output.dim_purchase_order` | PO headers | `po_id`, `supplier`, `status`, `company_id` |
| `sh_output.fact_po_line` | PO line items | `po_id`, `product_id`, `qty`, `price` |

### 5.2 Local Database Tables (New)

#### `brands`
```sql
CREATE TABLE brands (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    access_level ENUM('basic', 'premium') DEFAULT 'basic',
    synced_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY (name, company_id)
);
```

#### `brand_competitors`
```sql
CREATE TABLE brand_competitors (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    brand_id BIGINT UNSIGNED NOT NULL,
    competitor_brand_id BIGINT UNSIGNED NOT NULL,
    position TINYINT UNSIGNED NOT NULL, -- 1, 2, or 3
    created_at TIMESTAMP,
    FOREIGN KEY (brand_id) REFERENCES brands(id),
    FOREIGN KEY (competitor_brand_id) REFERENCES brands(id),
    UNIQUE KEY (brand_id, position)
);
```

#### `supplier_brand_access`
```sql
CREATE TABLE supplier_brand_access (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    brand_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (brand_id) REFERENCES brands(id),
    UNIQUE KEY (user_id, brand_id)
);
```

#### `price_scrapes` (Pricing Panel)
```sql
CREATE TABLE price_scrapes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    product_id CHAR(26) NOT NULL, -- ULID reference to entities
    competitor_name VARCHAR(255) NOT NULL,
    competitor_url TEXT,
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'ZAR',
    scraped_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP,
    INDEX (product_id, scraped_at)
);
```

---

## 6. API Specifications

### 6.1 Chart Data Endpoints

All endpoints require authentication via Sanctum token.

#### GET `/api/supply/charts/sales-trend`
**Request**:
```json
{
    "brand_id": 123,
    "period": "12m"
}
```
**Response**:
```json
{
    "labels": ["Jan", "Feb", "Mar", ...],
    "datasets": [
        {
            "label": "Your Brand",
            "data": [10000, 12000, 11500, ...]
        },
        {
            "label": "Competitor A",
            "data": [9500, 11000, 10800, ...]
        }
    ]
}
```

#### GET `/api/supply/tables/products`
**Request**:
```json
{
    "brand_id": 123,
    "page": 1,
    "per_page": 25,
    "sort": "revenue_desc"
}
```
**Response**:
```json
{
    "data": [
        {
            "sku": "ABC123",
            "name": "Product Name",
            "revenue_12m": 125000,
            "units_12m": 5000,
            "months": {
                "2025-01": 10500,
                "2025-02": 11200
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "total": 150
    }
}
```

---

## 7. Security Requirements

### 7.1 Authentication
| ID | Requirement | Priority |
|----|-------------|----------|
| SEC001 | Session-based auth with CSRF protection | P0 |
| SEC002 | Password hashing (bcrypt, cost 12) | P0 |
| SEC003 | Session timeout (120 minutes) | P0 |
| SEC004 | Failed login throttling | P1 |
| SEC005 | Optional 2FA | P2 |

### 7.2 Authorization
| ID | Requirement | Priority |
|----|-------------|----------|
| SEC010 | Policy-based resource access | P0 |
| SEC011 | Brand scope enforcement | P0 |
| SEC012 | Panel access via middleware | P0 |
| SEC013 | Audit logging for sensitive actions | P2 |

### 7.3 Data Protection
| ID | Requirement | Priority |
|----|-------------|----------|
| SEC020 | Anonymize competitor brand names | P0 |
| SEC021 | No raw SQL injection points | P0 |
| SEC022 | BigQuery parameterized queries | P0 |
| SEC023 | HTTPS only in production | P0 |

---

## 8. Performance Requirements

### 8.1 Response Times
| Metric | Target | Measurement |
|--------|--------|-------------|
| Dashboard load | < 2 seconds | Time to first meaningful paint |
| Chart data API | < 1 second | API response time |
| Table pagination | < 500ms | Page change response |
| BigQuery cached | < 200ms | Cached query response |
| BigQuery uncached | < 5 seconds | Fresh query execution |

### 8.2 Scalability
| Metric | Target |
|--------|--------|
| Concurrent users | 100+ per panel |
| Brands supported | 500+ |
| Products per brand | 10,000+ |
| BigQuery daily budget | $50/day cap |

---

## 9. Integration Requirements

### 9.1 External Systems
| System | Integration Type | Priority |
|--------|-----------------|----------|
| BigQuery | Read-only queries | P0 |
| Magento 2 | REST API (existing) | P0 |
| Google Cloud Auth | Service account | P0 |
| OpenAI | API (existing) | P0 |

### 9.2 Future Integrations (Out of Scope)
- Google Ads (marketing metrics)
- Acumatica (PO download)
- Email notifications

---

## 10. Release Plan

### 10.1 MVP (Phase 1-3)
**Target**: 8 weeks
- Multi-panel architecture
- PIM migration
- BigQuery integration
- Supply Insights: Overview, Products, Trends

### 10.2 Full Supply Insights (Phase 4)
**Target**: 12 weeks
- Market Share
- Customer Engagement
- Stock & Supply
- Purchase Orders

### 10.3 Pricing Tool (Phase 5)
**Target**: 16 weeks
- Price tracking
- Historical analysis
- Alerts

### 10.4 Polish & Launch (Phase 6)
**Target**: 20 weeks
- Performance optimization
- Documentation
- User acceptance testing
- Production deployment

---

## 11. Open Questions

1. **BigQuery authentication**: Service account or OAuth flow?
2. **Premium pricing**: What features constitute premium tier?
3. **Competitor identification**: How do we match competitors for each brand?
4. **Data freshness**: How often should BigQuery data be refreshed?
5. **PO download**: Can we get PO PDFs from Acumatica?
6. **Price scraping**: Is there an existing scraping service?
7. **Mobile support**: Is tablet support sufficient or do we need phone optimization?

---

## 12. Appendices

### Appendix A: Glossary
| Term | Definition |
|------|------------|
| EAV | Entity-Attribute-Value storage pattern |
| OTIF | On-Time In-Full delivery metric |
| RFM | Recency-Frequency-Monetary customer segmentation |
| AOV | Average Order Value |
| MoM | Month-over-Month change |

### Appendix B: Reference Documents
- [Multi-Panel Architecture Overview](./multi-panel-architecture-overview.md)
- [Test Strategy](./test-strategy.md)
- [Existing Architecture](./architecture.md)
- [Magento Sync Implementation](./magento-sync-implementation.md)

### Appendix C: Stakeholder Sign-off
| Role | Name | Date | Signature |
|------|------|------|-----------|
| Product Owner | | | |
| Engineering Lead | | | |
| Design Lead | | | |
| QA Lead | | | |
