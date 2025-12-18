# User Acceptance Testing (UAT) Plan

## Silvertree Multi-Panel Platform

**Document Version**: 1.0
**Last Updated**: December 2025
**Author**: Engineering Team
**Status**: Ready for Execution

---

## 1. Introduction

### 1.1 Purpose
This document defines the User Acceptance Testing (UAT) plan for the Silvertree multi-panel platform. UAT validates that the system meets business requirements and provides a satisfactory user experience for all stakeholder groups.

### 1.2 Scope
UAT covers all three panels of the platform:
- **PIM Panel** (`/pim`) - Product Information Management for internal teams
- **Supply Portal** (`/supply`) - Supplier analytics and insights
- **Pricing Tool** (`/pricing`) - Competitive pricing analysis

### 1.3 Success Criteria
UAT is considered successful when:
- [ ] All critical test scenarios pass
- [ ] No severity 1 (critical) bugs remain open
- [ ] All test groups provide sign-off
- [ ] Performance meets acceptable thresholds (< 2s page load)
- [ ] User feedback is overall positive (>80% satisfaction)

---

## 2. Test Groups

### 2.1 Internal PIM Team
**Participants**: 2-3 internal product information managers
**Panel Access**: PIM Panel
**Test Duration**: 2-3 days
**Focus Areas**:
- Product management workflows
- Attribute editing
- Pipeline execution
- Magento sync operations
- Review queue management

### 2.2 Selected Suppliers (Beta)
**Participants**: 3-5 existing supplier partners
**Panel Access**: Supply Portal
**Test Duration**: 3-5 days
**Focus Areas**:
- Dashboard usability
- Sales and analytics data accuracy
- Brand data visibility
- Premium vs Basic feature access
- Mobile responsiveness

### 2.3 Pricing Team
**Participants**: 2-3 pricing analysts
**Panel Access**: Pricing Tool
**Test Duration**: 2-3 days
**Focus Areas**:
- Competitor price monitoring
- Margin analysis
- Price alert configuration
- Data export functionality
- Dashboard accuracy

---

## 3. Test Environment

### 3.1 Environment Details
| Component | Value |
|-----------|-------|
| Environment URL | `https://staging.silvertreebrands.com` |
| Database | Staging (production-like data) |
| BigQuery | Production read-only |
| Authentication | Standard login |

### 3.2 Test Accounts

| Email | Password | Role | Panel Access |
|-------|----------|------|--------------|
| admin@silvertreebrands.com | (provided separately) | admin | All panels |
| pim@silvertreebrands.com | (provided separately) | pim-editor | PIM |
| supplier-basic@test.com | (provided separately) | supplier-basic | Supply |
| supplier-premium@test.com | (provided separately) | supplier-premium | Supply |
| pricing@silvertreebrands.com | (provided separately) | pricing-analyst | Pricing |

### 3.3 Browser Requirements
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

---

## 4. Test Scenarios

### 4.1 PIM Panel Test Scenarios

#### TC-PIM-001: Login and Dashboard Access
**Priority**: Critical
**Preconditions**: Valid PIM user credentials
**Steps**:
1. Navigate to `/pim/login`
2. Enter valid email and password
3. Click "Sign In"
4. Observe dashboard loads correctly

**Expected Results**:
- Login successful
- Dashboard displays without errors
- Navigation menu visible
- User name displayed in header

**Pass/Fail**: [ ]

---

#### TC-PIM-002: View Products List
**Priority**: Critical
**Preconditions**: Logged in as PIM user
**Steps**:
1. Click "Products" in navigation
2. Wait for product list to load
3. Verify table displays products
4. Test pagination (if >10 products)

**Expected Results**:
- Product list loads within 2 seconds
- Table shows product columns (SKU, Name, Status, etc.)
- Pagination works correctly
- Sort/filter controls function

**Pass/Fail**: [ ]

---

#### TC-PIM-003: Edit Product
**Priority**: Critical
**Preconditions**: Logged in as PIM user, product exists
**Steps**:
1. Click on a product from the list
2. Modify a text attribute (e.g., description)
3. Click "Save"
4. Navigate away and return to verify change persisted

**Expected Results**:
- Edit form loads correctly
- All attribute fields display
- Save completes without error
- Change persists after navigation

**Pass/Fail**: [ ]

---

#### TC-PIM-004: Side-by-Side Edit
**Priority**: High
**Preconditions**: Logged in as PIM user
**Steps**:
1. Open a product for editing
2. Click "Side by Side" view
3. Compare external data with internal values
4. Make an edit and save

**Expected Results**:
- Side-by-side view renders correctly
- External data (Magento) displays on left
- Internal PIM data on right
- Edits save successfully

**Pass/Fail**: [ ]

---

#### TC-PIM-005: Run Pipeline
**Priority**: High
**Preconditions**: Logged in as PIM user, pipeline configured
**Steps**:
1. Navigate to Pipelines
2. Select a pipeline
3. Click "Run" button
4. Monitor execution progress

**Expected Results**:
- Pipeline starts executing
- Progress indicator shows
- Completion notification appears
- Results can be viewed

**Pass/Fail**: [ ]

---

#### TC-PIM-006: Magento Sync
**Priority**: Critical
**Preconditions**: Logged in as admin, Magento connected
**Steps**:
1. Navigate to Magento Sync page
2. Click "Start Sync" button
3. Monitor sync progress
4. Verify products updated after sync

**Expected Results**:
- Sync initiates without error
- Progress bar updates
- Sync completes successfully
- Products reflect synced data

**Pass/Fail**: [ ]

---

#### TC-PIM-007: Manage Attributes
**Priority**: High
**Preconditions**: Logged in as PIM user
**Steps**:
1. Navigate to Attributes
2. View existing attribute list
3. Create a new attribute (if permitted)
4. Edit an existing attribute

**Expected Results**:
- Attribute list loads
- Attribute details viewable
- Create/edit forms work correctly
- Validation errors display appropriately

**Pass/Fail**: [ ]

---

#### TC-PIM-008: Review Queue
**Priority**: Medium
**Preconditions**: Logged in as PIM user, pending reviews exist
**Steps**:
1. Navigate to Review Queue
2. View pending changes
3. Approve or reject a change
4. Verify status updates

**Expected Results**:
- Queue displays pending items
- Item details viewable
- Approve/reject actions work
- Status updates correctly

**Pass/Fail**: [ ]

---

### 4.2 Supply Portal Test Scenarios

#### TC-SUP-001: Supplier Login
**Priority**: Critical
**Preconditions**: Valid supplier credentials
**Steps**:
1. Navigate to `/supply/login`
2. Enter supplier email and password
3. Click "Sign In"

**Expected Results**:
- Login successful
- Redirected to Supply dashboard
- Only assigned brand(s) visible
- User cannot access PIM or Pricing panels

**Pass/Fail**: [ ]

---

#### TC-SUP-002: Dashboard Overview
**Priority**: Critical
**Preconditions**: Logged in as supplier user
**Steps**:
1. View dashboard after login
2. Verify KPI tiles display
3. Check brand selector shows only assigned brands
4. Verify period filter works (30d, 90d, 12m)

**Expected Results**:
- Dashboard loads within 2 seconds
- KPIs display relevant data
- Brand selector limits to assigned brands
- Period filter updates data correctly

**Pass/Fail**: [ ]

---

#### TC-SUP-003: View Products Page
**Priority**: High
**Preconditions**: Logged in as supplier
**Steps**:
1. Navigate to Products page
2. Verify products display for selected brand
3. Test search functionality
4. View product details

**Expected Results**:
- Products page loads
- Only brand-specific products shown
- Search filters results correctly
- Product detail view works

**Pass/Fail**: [ ]

---

#### TC-SUP-004: Trends Analysis
**Priority**: High
**Preconditions**: Logged in as supplier
**Steps**:
1. Navigate to Trends page
2. View sales trend charts
3. Change date range
4. Compare different metrics

**Expected Results**:
- Trend charts render correctly
- Date range changes update charts
- Multiple metrics viewable
- Data appears accurate

**Pass/Fail**: [ ]

---

#### TC-SUP-005: Basic User - Premium Feature Lock
**Priority**: Critical
**Preconditions**: Logged in as supplier-basic user
**Steps**:
1. Navigate to a premium feature (Forecasting, Cohorts, RFM Analysis)
2. Observe premium lock indicator
3. Attempt to access premium data

**Expected Results**:
- Premium feature shows lock/upgrade message
- "Upgrade to Premium" CTA visible
- Premium data is NOT accessible
- No bypass possible

**Pass/Fail**: [ ]

---

#### TC-SUP-006: Premium User - Full Access
**Priority**: Critical
**Preconditions**: Logged in as supplier-premium user
**Steps**:
1. Navigate to premium features (Forecasting, Cohorts, RFM Analysis)
2. Verify full access granted
3. View forecasting data
4. View RFM analysis data

**Expected Results**:
- All premium features accessible
- No lock/upgrade messages
- Data displays correctly
- Full functionality available

**Pass/Fail**: [ ]

---

#### TC-SUP-007: Brand Isolation
**Priority**: Critical
**Preconditions**: Logged in as supplier with single brand assignment
**Steps**:
1. Check brand selector
2. Try to access data for non-assigned brand (via URL manipulation if known)
3. Verify all data shown relates to assigned brand only

**Expected Results**:
- Only assigned brand(s) in selector
- Cannot access other brands' data
- URL manipulation returns error/redirect
- Data isolation enforced

**Pass/Fail**: [ ]

---

#### TC-SUP-008: Export Data
**Priority**: Medium
**Preconditions**: Logged in as supplier-premium
**Steps**:
1. Navigate to a data page (Products, Trends)
2. Click Export button
3. Select format (CSV/Excel)
4. Download and verify file

**Expected Results**:
- Export button visible
- Format selection works
- File downloads successfully
- Data in file is correct

**Pass/Fail**: [ ]

---

#### TC-SUP-009: Mobile Responsiveness
**Priority**: High
**Preconditions**: Mobile device or responsive mode
**Steps**:
1. Access Supply portal on mobile device
2. Test navigation menu
3. Verify dashboard layout adapts
4. Test touch interactions

**Expected Results**:
- Mobile layout renders properly
- Navigation hamburger menu works
- KPIs stack vertically on small screens
- Touch interactions work smoothly

**Pass/Fail**: [ ]

---

#### TC-SUP-010: Benchmarks Page
**Priority**: Medium
**Preconditions**: Logged in as supplier
**Steps**:
1. Navigate to Benchmarks
2. View benchmark comparisons
3. Select different comparison metrics
4. Verify data accuracy

**Expected Results**:
- Benchmarks page loads
- Comparison data displays
- Metric selection works
- Data appears reasonable

**Pass/Fail**: [ ]

---

### 4.3 Pricing Tool Test Scenarios

#### TC-PRC-001: Pricing Analyst Login
**Priority**: Critical
**Preconditions**: Valid pricing analyst credentials
**Steps**:
1. Navigate to `/pricing/login`
2. Enter credentials
3. Click "Sign In"

**Expected Results**:
- Login successful
- Redirected to Pricing dashboard
- Cannot access PIM or Supply panels
- Pricing navigation visible

**Pass/Fail**: [ ]

---

#### TC-PRC-002: Dashboard Overview
**Priority**: Critical
**Preconditions**: Logged in as pricing-analyst
**Steps**:
1. View dashboard after login
2. Check KPIs display
3. Verify price tracking widgets
4. Review recent alerts

**Expected Results**:
- Dashboard loads quickly
- KPIs show relevant pricing metrics
- Widgets functional
- Recent alerts visible if any

**Pass/Fail**: [ ]

---

#### TC-PRC-003: Competitor Prices Page
**Priority**: Critical
**Preconditions**: Logged in, price data exists
**Steps**:
1. Navigate to Competitor Prices
2. View price comparison table
3. Filter by category
4. Sort by price difference

**Expected Results**:
- Price table loads
- Our price vs competitor prices shown
- Filter narrows results
- Sort orders correctly

**Pass/Fail**: [ ]

---

#### TC-PRC-004: Price History Chart
**Priority**: High
**Preconditions**: Logged in, historical data exists
**Steps**:
1. Navigate to Price History
2. Select a product
3. View price history chart
4. Change date range

**Expected Results**:
- Chart renders correctly
- Multiple competitor lines visible
- Our price as reference line
- Date range updates chart

**Pass/Fail**: [ ]

---

#### TC-PRC-005: Price Comparison Matrix
**Priority**: High
**Preconditions**: Logged in
**Steps**:
1. Navigate to Price Comparison Matrix
2. View matrix table
3. Observe color coding (green/red/yellow)
4. Check matrix accuracy

**Expected Results**:
- Matrix displays products vs competitors
- Green = cheapest, Red = most expensive
- Yellow = mid-range
- Color coding is accurate

**Pass/Fail**: [ ]

---

#### TC-PRC-006: Configure Price Alert
**Priority**: High
**Preconditions**: Logged in
**Steps**:
1. Navigate to Price Alerts (or create from dashboard)
2. Create a new price alert
3. Set alert type (price below, competitor beats, etc.)
4. Configure threshold
5. Save alert

**Expected Results**:
- Alert creation form accessible
- All alert types available
- Threshold configuration works
- Alert saved successfully

**Pass/Fail**: [ ]

---

#### TC-PRC-007: View Alert Notifications
**Priority**: Medium
**Preconditions**: Logged in, alerts configured and triggered
**Steps**:
1. Check notification badge in header
2. View alert notifications
3. Mark notifications as read
4. Navigate to alert source

**Expected Results**:
- Notification badge shows count
- Notification list displays alerts
- Mark as read works
- Can navigate to related data

**Pass/Fail**: [ ]

---

#### TC-PRC-008: Margin Analysis
**Priority**: High
**Preconditions**: Logged in, cost data available
**Steps**:
1. Navigate to Margin Analysis
2. View margin data per product
3. Filter by category
4. Export margin report

**Expected Results**:
- Margin data displays
- Cost vs selling price visible
- Category filter works
- Export produces valid file

**Pass/Fail**: [ ]

---

#### TC-PRC-009: Export Pricing Data
**Priority**: Medium
**Preconditions**: Logged in
**Steps**:
1. Navigate to any data page
2. Click Export button
3. Download CSV/Excel
4. Verify data integrity

**Expected Results**:
- Export options available
- File downloads
- Data matches screen display
- No data corruption

**Pass/Fail**: [ ]

---

### 4.4 Cross-Panel Test Scenarios

#### TC-CROSS-001: Admin Panel Switching
**Priority**: High
**Preconditions**: Logged in as admin
**Steps**:
1. From PIM panel, switch to Supply panel
2. From Supply panel, switch to Pricing panel
3. From Pricing panel, switch back to PIM

**Expected Results**:
- Panel switcher visible for admin
- Switching between panels works
- Context preserved appropriately
- No unauthorized access errors

**Pass/Fail**: [ ]

---

#### TC-CROSS-002: Session Persistence
**Priority**: High
**Preconditions**: Logged in as any user
**Steps**:
1. Perform actions in panel
2. Leave browser idle for 15 minutes
3. Return and continue working
4. Close browser, reopen, and check session

**Expected Results**:
- Session persists during idle
- Actions resume without re-login
- Session eventually expires (security)
- Re-login required after browser close

**Pass/Fail**: [ ]

---

#### TC-CROSS-003: Logout Functionality
**Priority**: Critical
**Preconditions**: Logged in as any user
**Steps**:
1. Click logout/user menu
2. Select "Sign Out"
3. Attempt to access protected page
4. Verify redirected to login

**Expected Results**:
- Logout option accessible
- Session terminated
- Cannot access protected pages
- Redirected to login page

**Pass/Fail**: [ ]

---

#### TC-CROSS-004: Password Reset
**Priority**: High
**Preconditions**: Valid user account
**Steps**:
1. Navigate to login page
2. Click "Forgot Password"
3. Enter email address
4. Check email for reset link
5. Reset password using link

**Expected Results**:
- Forgot password link works
- Email sent confirmation shown
- Reset email received
- Password reset successful
- Can login with new password

**Pass/Fail**: [ ]

---

#### TC-CROSS-005: Unauthorized Access Prevention
**Priority**: Critical
**Preconditions**: Logged in as supplier user
**Steps**:
1. Manually navigate to `/pim`
2. Manually navigate to `/pricing`
3. Try to access admin-only features

**Expected Results**:
- Redirected away from unauthorized panels
- Error message displayed
- No unauthorized data exposed
- Session remains valid

**Pass/Fail**: [ ]

---

## 5. Feedback Collection

### 5.1 Bug Report Template

When reporting bugs, please use this format:

```
BUG REPORT
==========
Reporter: [Your Name]
Date: [Date]
Panel: [PIM / Supply / Pricing]
Severity: [1-Critical / 2-High / 3-Medium / 4-Low]

Summary:
[One line description of the bug]

Steps to Reproduce:
1. [Step 1]
2. [Step 2]
3. [Step 3]

Expected Result:
[What should have happened]

Actual Result:
[What actually happened]

Environment:
- Browser: [Browser name and version]
- Device: [Desktop/Mobile/Tablet]
- Screen Size: [e.g., 1920x1080]

Screenshots/Video:
[Attach if available]

Additional Notes:
[Any other relevant information]
```

### 5.2 UX Feedback Template

```
UX FEEDBACK
===========
Reviewer: [Your Name]
Date: [Date]
Panel: [PIM / Supply / Pricing]
Page/Feature: [Specific page or feature]

Ease of Use (1-5): [ ]
1 = Very Difficult, 5 = Very Easy

Visual Appeal (1-5): [ ]
1 = Poor, 5 = Excellent

Clarity (1-5): [ ]
1 = Confusing, 5 = Crystal Clear

What did you like?
[Your answer]

What was confusing or difficult?
[Your answer]

What would you improve?
[Your answer]

Would you recommend this feature to colleagues? (Yes/No/Maybe)
[Your answer]

Additional Comments:
[Any other feedback]
```

### 5.3 Performance Feedback Template

```
PERFORMANCE FEEDBACK
====================
Tester: [Your Name]
Date: [Date]
Panel: [PIM / Supply / Pricing]
Connection Type: [WiFi / Ethernet / Mobile Data]

Page Load Times (subjective):
- Dashboard: [Fast / Acceptable / Slow]
- List Pages: [Fast / Acceptable / Slow]
- Chart Loading: [Fast / Acceptable / Slow]
- Form Submissions: [Fast / Acceptable / Slow]

Did you experience any:
- [ ] Timeout errors
- [ ] Pages not loading
- [ ] Slow response times
- [ ] Browser freezing
- [ ] Excessive loading spinners

Describe any performance issues:
[Your description]

Overall Performance Rating (1-5): [ ]
1 = Unusable, 5 = Excellent
```

---

## 6. UAT Execution Process

### 6.1 Pre-UAT Checklist

- [ ] Staging environment deployed and stable
- [ ] Test accounts created and credentials distributed
- [ ] Test data loaded and verified
- [ ] BigQuery connection working
- [ ] All testers have browser access
- [ ] Feedback templates distributed
- [ ] UAT kick-off meeting held
- [ ] Communication channel established (Slack/Email)

### 6.2 Daily UAT Activities

**Morning**:
1. Check environment health
2. Review overnight issues
3. Sync with testers on focus areas

**During Testing**:
1. Testers execute scenarios
2. Report bugs immediately (Severity 1-2)
3. Document feedback continuously

**End of Day**:
1. Collect feedback forms
2. Triage bugs
3. Update test status tracker
4. Brief development team on critical issues

### 6.3 Bug Severity Definitions

| Severity | Definition | Response Time |
|----------|------------|---------------|
| 1 - Critical | System unusable, data loss, security issue | Fix immediately |
| 2 - High | Major feature broken, no workaround | Fix within 24 hours |
| 3 - Medium | Feature impaired, workaround exists | Fix before go-live |
| 4 - Low | Minor issue, cosmetic | Fix in future release |

### 6.4 Test Status Tracking

Maintain a shared spreadsheet with:
- Test case ID
- Tester assigned
- Execution status (Not Started / In Progress / Pass / Fail / Blocked)
- Bug reference (if failed)
- Notes

---

## 7. Sign-off Process

### 7.1 Individual Sign-off

Each tester completes:

```
UAT SIGN-OFF FORM
=================
Tester Name: _______________________
Role: _______________________
Panel(s) Tested: _______________________
Test Period: _______________________

I confirm that:
[ ] I have executed all assigned test scenarios
[ ] All critical bugs have been reported
[ ] The system meets my acceptance criteria
[ ] I approve the system for production release

Signature: _______________________
Date: _______________________

Comments/Conditions:
_______________________
```

### 7.2 Group Sign-off

Each test group (PIM Team, Suppliers, Pricing Team) provides collective sign-off after:
- All critical scenarios pass
- No open Severity 1 bugs
- No open Severity 2 bugs (or agreed timeline for resolution)
- >80% of test scenarios pass
- Majority positive feedback

### 7.3 Final Go-Live Approval

**Approvers**:
- Engineering Lead
- Product Owner
- Test Group Representatives

**Criteria**:
- All group sign-offs obtained
- Performance targets met
- Security audit passed
- Rollback plan documented

---

## 8. Appendices

### Appendix A: Contact Information

| Role | Name | Email | Phone |
|------|------|-------|-------|
| UAT Coordinator | TBD | uat@silvertreebrands.com | - |
| Engineering Lead | TBD | dev@silvertreebrands.com | - |
| Product Owner | TBD | product@silvertreebrands.com | - |
| Support | TBD | support@silvertreebrands.com | - |

### Appendix B: Environment URLs

| Environment | URL | Purpose |
|-------------|-----|---------|
| Staging | https://staging.silvertreebrands.com | UAT Testing |
| Production | https://app.silvertreebrands.com | Live (after go-live) |

### Appendix C: Known Limitations

1. **BigQuery Data**: Data reflects production but may have slight delay (up to 24 hours)
2. **Test Transactions**: Do not create real orders; use test data only
3. **Email Notifications**: May be disabled or use test recipients in staging

### Appendix D: Test Data Guidelines

- Use products with SKU prefix `TEST-` for testing
- Test brands: "UAT Brand 1", "UAT Brand 2"
- Do not modify production-critical data
- Report any data discrepancies immediately

---

## 9. Version History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | December 2025 | Engineering Team | Initial document |

---

**End of Document**
