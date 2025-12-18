# API Documentation

**Platform**: Silvertree Multi-Panel Platform
**API Version**: 1.0
**Base URL**: `/api`
**Last Updated**: 2025-12-14

---

## Table of Contents

1. [Overview](#overview)
2. [Authentication](#authentication)
3. [Rate Limiting](#rate-limiting)
4. [Error Handling](#error-handling)
5. [Supply Insights API](#supply-insights-api)
6. [Response Formats](#response-formats)
7. [Examples](#examples)

---

## Overview

The Silvertree Platform provides a limited set of API endpoints primarily for internal use by the frontend application. These endpoints are designed for AJAX calls from the same application rather than external third-party integrations.

### API Characteristics

- **Internal Use**: Designed for web application AJAX calls
- **Authentication**: Uses web session authentication (not token-based)
- **Same-Origin**: CORS not configured for external domains
- **Rate Limited**: 60 requests per minute per user

### Available APIs

Currently, the platform exposes:

- **Supply Insights API**: Chart and table data for the Supply panel

### Future APIs (Not Yet Implemented)

- PIM Product API
- Pricing Data API
- User Management API

---

## Authentication

### Session-Based Authentication

API endpoints use **web session authentication** via Laravel's default authentication guard.

**How it works:**

1. User logs in via web interface (`/login`)
2. Session cookie is set
3. Subsequent API requests include session cookie
4. Middleware validates session

**No Token Required**: Unlike typical REST APIs, these endpoints do not use API tokens (Sanctum/Passport is not installed).

### Making Authenticated Requests

**From JavaScript (same origin):**

```javascript
fetch('/api/supply/charts/sales-trend?brand_id=1&period=30', {
  method: 'GET',
  credentials: 'same-origin', // Include cookies
  headers: {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest' // Laravel expects this for AJAX
  }
})
.then(response => response.json())
.then(data => console.log(data));
```

**From Axios (recommended):**

```javascript
axios.get('/api/supply/charts/sales-trend', {
  params: {
    brand_id: 1,
    period: 30
  }
})
.then(response => console.log(response.data));
```

Axios automatically includes CSRF token and credentials.

### CSRF Protection

All state-changing requests (POST, PUT, DELETE) require a CSRF token.

**Getting CSRF Token:**

Included in page meta tag:

```html
<meta name="csrf-token" content="...">
```

**Sending CSRF Token:**

Laravel automatically includes it if you're using Axios or if you include it in headers:

```javascript
fetch('/api/endpoint', {
  method: 'POST',
  headers: {
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
  }
})
```

---

## Rate Limiting

All API endpoints are rate limited to prevent abuse.

### Limits

- **Supply API**: 60 requests per minute per user
- **Future APIs**: TBD

### Rate Limit Headers

Responses include rate limit headers:

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
Retry-After: 30
```

### Exceeding Rate Limit

When rate limit is exceeded, you'll receive:

**HTTP 429 Too Many Requests**

```json
{
  "message": "Too Many Attempts."
}
```

**Retry After**: Check `Retry-After` header for seconds to wait.

---

## Error Handling

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 400 | Bad Request (invalid parameters) |
| 401 | Unauthorized (not logged in) |
| 403 | Forbidden (insufficient permissions) |
| 404 | Not Found |
| 422 | Unprocessable Entity (validation error) |
| 429 | Too Many Requests (rate limit) |
| 500 | Internal Server Error |

### Error Response Format

Errors return JSON with `message` field:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "brand_id": [
      "The brand id field is required."
    ]
  }
}
```

### Common Errors

**401 Unauthorized:**

```json
{
  "message": "Unauthenticated."
}
```

**Solution**: User needs to log in.

**403 Forbidden:**

```json
{
  "message": "This action is unauthorized."
}
```

**Solution**: User lacks required role or brand access.

**422 Validation Error:**

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "period": ["The period must be a number."]
  }
}
```

**Solution**: Fix invalid parameters.

---

## Supply Insights API

Endpoints for retrieving Supply panel chart and table data.

### Base URL

```
/api/supply
```

### Authentication

Requires:
- User must be logged in
- User must have `admin`, `supplier-basic`, or `supplier-premium` role
- Endpoint protected by `supply-panel-access` middleware

---

### GET /api/supply/charts/sales-trend

Retrieve sales trend data for charting.

**URL**: `/api/supply/charts/sales-trend`

**Method**: GET

**Auth**: Required (session)

**Query Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `brand_id` | integer | No | Filter by brand ID (admin/multi-brand users) |
| `period` | integer | No | Days to look back (default: 30) |
| `granularity` | string | No | `daily`, `weekly`, `monthly` (default: `daily`) |

**Success Response (200)**:

```json
{
  "success": true,
  "data": {
    "labels": ["2024-11-15", "2024-11-16", "2024-11-17"],
    "datasets": [
      {
        "label": "Sales",
        "data": [12500.50, 15200.75, 14800.00]
      }
    ]
  }
}
```

**Example Request**:

```javascript
axios.get('/api/supply/charts/sales-trend', {
  params: {
    brand_id: 1,
    period: 90,
    granularity: 'weekly'
  }
})
```

---

### GET /api/supply/charts/competitor

Retrieve competitor comparison data.

**URL**: `/api/supply/charts/competitor`

**Method**: GET

**Auth**: Required (session)

**Query Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `brand_id` | integer | Yes | Your brand ID |
| `period` | integer | No | Days to look back (default: 30) |

**Success Response (200)**:

```json
{
  "success": true,
  "data": {
    "labels": ["Your Brand", "Competitor 1", "Competitor 2"],
    "datasets": [
      {
        "label": "Sales",
        "data": [125000, 98000, 110000]
      }
    ]
  }
}
```

---

### GET /api/supply/charts/market-share

Retrieve market share data.

**URL**: `/api/supply/charts/market-share`

**Method**: GET

**Auth**: Required (session)

**Query Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `brand_id` | integer | Yes | Your brand ID |
| `category` | string | No | Filter by category |

**Success Response (200)**:

```json
{
  "success": true,
  "data": {
    "labels": ["Your Brand", "Competitor 1", "Competitor 2", "Others"],
    "datasets": [
      {
        "label": "Market Share %",
        "data": [35.5, 28.2, 22.1, 14.2]
      }
    ]
  }
}
```

---

### GET /api/supply/tables/products

Retrieve product sales data in table format.

**URL**: `/api/supply/tables/products`

**Method**: GET

**Auth**: Required (session)

**Query Parameters**:

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `brand_id` | integer | No | Filter by brand ID |
| `period` | integer | No | Days to look back (default: 30) |
| `page` | integer | No | Page number for pagination (default: 1) |
| `per_page` | integer | No | Results per page (default: 20, max: 100) |
| `sort_by` | string | No | `name`, `sales`, `units` (default: `sales`) |
| `sort_dir` | string | No | `asc`, `desc` (default: `desc`) |

**Success Response (200)**:

```json
{
  "success": true,
  "data": [
    {
      "product_id": "01HXK...",
      "product_name": "Organic Coconut Oil 500ml",
      "sku": "FTN-COC-500",
      "units_sold": 245,
      "revenue": 21805.50,
      "avg_price": 89.00,
      "stock_level": 450,
      "stock_coverage_days": 35
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 156,
    "last_page": 8
  }
}
```

---

### GET /api/supply/tables/stock

Retrieve stock level data in table format.

**URL**: `/api/supply/tables/stock`

**Method**: GET

**Auth**: Required (session)

**Query Parameters**: Same as `/tables/products`

**Success Response (200)**:

```json
{
  "success": true,
  "data": [
    {
      "product_id": "01HXK...",
      "product_name": "Manuka Honey 250g",
      "sku": "FTN-HON-250",
      "stock_level": 15,
      "stock_coverage_days": 5,
      "status": "low_stock",
      "reorder_recommended": true
    }
  ]
}
```

---

### GET /api/supply/tables/purchase-orders

Retrieve purchase order data in table format.

**URL**: `/api/supply/tables/purchase-orders`

**Method**: GET

**Auth**: Required (session)

**Query Parameters**: Same as `/tables/products`

**Success Response (200)**:

```json
{
  "success": true,
  "data": [
    {
      "po_number": "PO-2024-1234",
      "supplier": "Acme Suppliers",
      "order_date": "2024-11-15",
      "expected_delivery": "2024-11-22",
      "status": "in_transit",
      "total_value": 45600.00,
      "items_count": 12
    }
  ]
}
```

---

## Response Formats

### Success Response

All successful responses follow this format:

```json
{
  "success": true,
  "data": { ... }
}
```

or with pagination:

```json
{
  "success": true,
  "data": [ ... ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 156,
    "last_page": 8
  }
}
```

### Error Response

All error responses follow this format:

```json
{
  "success": false,
  "message": "Error description",
  "errors": { ... }
}
```

---

## Examples

### Complete JavaScript Example

**Fetch Sales Trend and Display Chart:**

```javascript
async function loadSalesTrend() {
  try {
    const response = await axios.get('/api/supply/charts/sales-trend', {
      params: {
        brand_id: 1,
        period: 30,
        granularity: 'daily'
      }
    });

    if (response.data.success) {
      const chartData = response.data.data;

      // Use with Chart.js
      new Chart(document.getElementById('salesChart'), {
        type: 'line',
        data: {
          labels: chartData.labels,
          datasets: chartData.datasets
        }
      });
    }
  } catch (error) {
    if (error.response) {
      // Server responded with error
      console.error('Error:', error.response.data.message);

      if (error.response.status === 401) {
        // Redirect to login
        window.location.href = '/login';
      } else if (error.response.status === 403) {
        alert('You do not have access to this data');
      }
    } else {
      console.error('Network error:', error);
    }
  }
}
```

### Fetch with Error Handling

```javascript
fetch('/api/supply/tables/products?brand_id=1&period=30', {
  credentials: 'same-origin',
  headers: {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  }
})
.then(response => {
  if (!response.ok) {
    throw new Error(`HTTP ${response.status}`);
  }
  return response.json();
})
.then(data => {
  if (data.success) {
    console.log('Products:', data.data);
  }
})
.catch(error => {
  console.error('Error:', error);
});
```

### Pagination Example

```javascript
async function loadProducts(page = 1) {
  const response = await axios.get('/api/supply/tables/products', {
    params: {
      brand_id: 1,
      page: page,
      per_page: 50,
      sort_by: 'sales',
      sort_dir: 'desc'
    }
  });

  const products = response.data.data;
  const pagination = response.data.pagination;

  console.log(`Page ${pagination.current_page} of ${pagination.last_page}`);
  console.log(`Showing ${products.length} of ${pagination.total} products`);

  // Load next page
  if (pagination.current_page < pagination.last_page) {
    await loadProducts(page + 1);
  }
}
```

---

## Extending the API

### Adding New Endpoints

To add new API endpoints:

1. **Create Controller**:
   ```bash
   php artisan make:controller Api/YourController
   ```

2. **Define Routes** in `routes/api.php`:
   ```php
   Route::middleware(['auth', 'your-middleware'])->group(function () {
       Route::get('/your-endpoint', [YourController::class, 'index']);
   });
   ```

3. **Implement Controller**:
   ```php
   public function index(Request $request)
   {
       $data = YourModel::query()
           ->when($request->filter, fn($q) => $q->where(...))
           ->get();

       return response()->json([
           'success' => true,
           'data' => $data
       ]);
   }
   ```

4. **Add Tests**:
   ```bash
   php artisan make:test YourApiTest
   ```

---

## Best Practices

### For API Consumers

1. **Always handle errors**: Check response status and handle 401, 403, 422, 429, 500
2. **Respect rate limits**: Implement exponential backoff on 429 errors
3. **Use pagination**: Don't fetch all data at once
4. **Cache when possible**: Cache data client-side to reduce API calls
5. **Include CSRF token**: For POST/PUT/DELETE requests

### For API Developers

1. **Validate input**: Use Laravel's validation
2. **Return consistent formats**: Always include `success` and `data` fields
3. **Document endpoints**: Update this documentation
4. **Add tests**: Every endpoint needs tests
5. **Apply middleware**: Authentication, rate limiting, panel access

---

## Need Help?

- **Troubleshooting**: See [Troubleshooting Guide](troubleshooting-guide.md)
- **Admin Guide**: See [Admin Guide](admin-guide.md)
- **Support**: Contact support@silvertreebrands.com

---

**Document Version**: 1.0
**Last Updated**: 2025-12-14
**Platform Version**: Laravel 12 + Filament 4
