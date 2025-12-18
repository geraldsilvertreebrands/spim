<?php

namespace App\Services;

use Google\Cloud\BigQuery\BigQueryClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BigQueryService
{
    private ?BigQueryClient $client = null;

    private string $dataset;

    private int $companyId;

    private int $cacheTtl;

    private int $timeout;

    private bool $isConfigured = false;

    public function __construct()
    {
        $this->dataset = config('bigquery.dataset', 'sh_output');
        $this->companyId = config('bigquery.company_id', 3);
        $this->cacheTtl = config('bigquery.cache_ttl', 900);
        $this->timeout = config('bigquery.timeout', 30);

        $this->initializeClient();
    }

    /**
     * Initialize the BigQuery client if credentials are available.
     */
    private function initializeClient(): void
    {
        $projectId = config('bigquery.project_id');
        $credentialsPath = config('bigquery.credentials_path');

        if (empty($projectId)) {
            Log::warning('BigQuery: Project ID not configured');

            return;
        }

        try {
            $clientConfig = ['projectId' => $projectId];

            if (! empty($credentialsPath) && file_exists($credentialsPath)) {
                $clientConfig['keyFilePath'] = $credentialsPath;
                Log::info('BigQuery: Using service account credentials', ['path' => $credentialsPath]);
            } else {
                Log::info('BigQuery: Using Application Default Credentials (ADC)');
            }

            $this->client = new BigQueryClient($clientConfig);
            $this->isConfigured = true;
            Log::info('BigQuery: Client initialized successfully');
        } catch (\Exception $e) {
            Log::error('BigQuery: Failed to initialize client', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function isConfigured(): bool
    {
        return $this->isConfigured;
    }

    /**
     * Convert BigQuery value to float.
     * Handles Google\Cloud\BigQuery\Numeric objects.
     *
     * @param  mixed  $value
     */
    private function toFloat($value): float
    {
        if ($value === null) {
            return 0.0;
        }
        if (is_object($value) && method_exists($value, 'get')) {
            return (float) $value->get();
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (float) (string) $value;
        }

        return (float) $value;
    }

    /**
     * Convert BigQuery value to int.
     * Handles Google\Cloud\BigQuery\Numeric objects.
     *
     * @param  mixed  $value
     */
    private function toInt($value): int
    {
        if ($value === null) {
            return 0;
        }
        if (is_object($value) && method_exists($value, 'get')) {
            return (int) $value->get();
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (int) (string) $value;
        }

        return (int) $value;
    }

    /**
     * Execute a query with parameters.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    public function query(string $sql, array $params = [], ?int $timeoutSeconds = null): array
    {
        if (! $this->isConfigured || ! $this->client) {
            throw new \RuntimeException('BigQuery client is not configured. Check credentials.');
        }

        $timeout = $timeoutSeconds ?? $this->timeout;
        $startTime = microtime(true);

        try {
            $queryJobConfig = $this->client->query($sql);

            if (! empty($params)) {
                $queryJobConfig->parameters($params);
            }

            $queryJobConfig->useQueryCache(true);
            $queryResults = $this->client->runQuery($queryJobConfig);

            $elapsed = microtime(true) - $startTime;
            if ($elapsed > $timeout) {
                throw new \RuntimeException("Query timed out after {$timeout} seconds");
            }

            $rows = [];
            foreach ($queryResults as $row) {
                $rows[] = $row;

                $elapsed = microtime(true) - $startTime;
                if ($elapsed > $timeout) {
                    throw new \RuntimeException("Query timed out after {$timeout} seconds while fetching results");
                }
            }

            return $rows;
        } catch (\Exception $e) {
            Log::error('BigQuery query failed', [
                'sql' => substr($sql, 0, 500),
                'params' => $params,
                'error' => $e->getMessage(),
                'elapsed_seconds' => round(microtime(true) - $startTime, 2),
            ]);

            throw $e;
        }
    }

    /**
     * Execute query with caching.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    public function queryCached(string $cacheKey, string $sql, array $params = [], ?int $ttl = null): array
    {
        $ttl = $ttl ?? $this->cacheTtl;
        $fullCacheKey = "bigquery:{$cacheKey}";

        return Cache::remember($fullCacheKey, $ttl, function () use ($sql, $params) {
            return $this->query($sql, $params);
        });
    }

    /**
     * Get all unique brands for this company from BigQuery.
     */
    public function getBrands(): Collection
    {
        $sql = "SELECT DISTINCT brand FROM `{$this->dataset}.dim_product` WHERE company_id = @company_id AND brand IS NOT NULL ORDER BY brand";

        $results = $this->queryCached(
            "brands:{$this->companyId}",
            $sql,
            ['company_id' => $this->companyId]
        );

        return collect($results)->pluck('brand');
    }

    /**
     * Get sales data for a brand within a date range.
     */
    public function getSalesByBrand(string $brand, string $startDate, string $endDate): array
    {
        $sql = "
            SELECT
                DATE(oi.order_date) as date,
                SUM(oi.qty_ordered) as units_sold,
                SUM(oi.revenue_realised_subtotal_excl) as revenue
            FROM `{$this->dataset}.fact_order_item` oi
            JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
            WHERE oi.company_id = @company_id
              AND p.brand = @brand
              AND oi.order_date BETWEEN @start_date AND @end_date
            GROUP BY DATE(oi.order_date)
            ORDER BY date
        ";

        return $this->queryCached(
            "sales:{$this->companyId}:{$brand}:{$startDate}:{$endDate}",
            $sql,
            [
                'company_id' => $this->companyId,
                'brand' => $brand,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        );
    }

    /**
     * Get product performance metrics for a brand.
     */
    public function getProductPerformance(string $brand, string $period = '12m'): array
    {
        $startDate = $this->calculateStartDate($period);

        $sql = "
            SELECT
                p.sku,
                p.name,
                SUM(oi.qty_ordered) as units_sold,
                SUM(oi.revenue_realised_subtotal_excl) as revenue,
                AVG(oi.price_excl) as avg_price
            FROM `{$this->dataset}.fact_order_item` oi
            JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
            WHERE oi.company_id = @company_id
              AND p.brand = @brand
              AND oi.order_date >= @start_date
            GROUP BY p.sku, p.name
            ORDER BY revenue DESC
            LIMIT 100
        ";

        return $this->queryCached(
            "performance:{$this->companyId}:{$brand}:{$period}",
            $sql,
            [
                'company_id' => $this->companyId,
                'brand' => $brand,
                'start_date' => $startDate,
            ]
        );
    }

    public function getCompanyId(): int
    {
        return $this->companyId;
    }

    public function getDataset(): string
    {
        return $this->dataset;
    }

    public function clearCache(?string $pattern = null): void
    {
        if ($pattern) {
            Cache::forget("bigquery:{$pattern}");
        } else {
            Log::info('BigQuery: Cache clear requested');
        }
    }

    private function calculateStartDate(string $period): string
    {
        $matches = [];
        if (preg_match('/^(\d+)([dwmy])$/', $period, $matches)) {
            $value = (int) $matches[1];
            $unit = $matches[2];

            return match ($unit) {
                'd' => now()->subDays($value)->format('Y-m-d'),
                'w' => now()->subWeeks($value)->format('Y-m-d'),
                'm' => now()->subMonths($value)->format('Y-m-d'),
                default => now()->subYears($value)->format('Y-m-d'),
            };
        }

        return now()->subMonths(12)->format('Y-m-d');
    }

    private function periodToDays(string $period): int
    {
        return match ($period) {
            '30d' => 30,
            '90d' => 90,
            '1yr', '365d', '1y' => 365,
            default => 30,
        };
    }

    /**
     * Get KPI summary for a brand with period-over-period comparison.
     */
    public function getBrandKpis(string $brand, string $period = '30d'): array
    {
        $days = $this->periodToDays($period);

        $sql = <<<SQL
        WITH current_period AS (
            SELECT
                COALESCE(SUM(oi.revenue_realised_subtotal_excl), 0) as revenue,
                COUNT(DISTINCT oi.order_id) as orders,
                COALESCE(SUM(oi.qty_ordered), 0) as units
            FROM `{$this->dataset}.fact_order_item` oi
            JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
            WHERE p.brand = @brand
              AND p.company_id = @company_id
              AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @days DAY)
        ),
        previous_period AS (
            SELECT
                COALESCE(SUM(oi.revenue_realised_subtotal_excl), 0) as revenue,
                COUNT(DISTINCT oi.order_id) as orders,
                COALESCE(SUM(oi.qty_ordered), 0) as units
            FROM `{$this->dataset}.fact_order_item` oi
            JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
            WHERE p.brand = @brand
              AND p.company_id = @company_id
              AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @days_double DAY)
              AND oi.order_date < DATE_SUB(CURRENT_DATE(), INTERVAL @days DAY)
        )
        SELECT
            c.revenue,
            c.orders,
            c.units,
            SAFE_DIVIDE(c.revenue, c.orders) as aov,
            SAFE_DIVIDE(c.revenue - p.revenue, NULLIF(p.revenue, 0)) * 100 as revenue_change,
            SAFE_DIVIDE(c.orders - p.orders, NULLIF(p.orders, 0)) * 100 as orders_change,
            SAFE_DIVIDE(c.units - p.units, NULLIF(p.units, 0)) * 100 as units_change,
            SAFE_DIVIDE(
                SAFE_DIVIDE(c.revenue, c.orders) - SAFE_DIVIDE(p.revenue, p.orders),
                NULLIF(SAFE_DIVIDE(p.revenue, p.orders), 0)
            ) * 100 as aov_change
        FROM current_period c, previous_period p
        SQL;

        $results = $this->queryCached("brand_kpis:{$this->companyId}:{$brand}:{$period}", $sql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'days' => $days,
            'days_double' => $days * 2,
        ]);

        if (empty($results)) {
            return [
                'revenue' => 0,
                'orders' => 0,
                'units' => 0,
                'aov' => 0,
                'revenue_change' => null,
                'orders_change' => null,
                'units_change' => null,
                'aov_change' => null,
            ];
        }

        $row = $results[0];

        return [
            'revenue' => $this->toFloat($row['revenue'] ?? 0),
            'orders' => $this->toInt($row['orders'] ?? 0),
            'units' => $this->toInt($row['units'] ?? 0),
            'aov' => $this->toFloat($row['aov'] ?? 0),
            'revenue_change' => isset($row['revenue_change']) ? round($this->toFloat($row['revenue_change']), 1) : null,
            'orders_change' => isset($row['orders_change']) ? round($this->toFloat($row['orders_change']), 1) : null,
            'units_change' => isset($row['units_change']) ? round($this->toFloat($row['units_change']), 1) : null,
            'aov_change' => isset($row['aov_change']) ? round($this->toFloat($row['aov_change']), 1) : null,
        ];
    }

    /**
     * Get monthly sales trend for charting.
     */
    public function getSalesTrend(string $brand, int $months = 12): array
    {
        $sql = <<<SQL
        SELECT
            FORMAT_DATE('%Y-%m', oi.order_date) as month,
            SUM(oi.revenue_realised_subtotal_excl) as revenue
        FROM `{$this->dataset}.fact_order_item` oi
        JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
        WHERE p.brand = @brand
          AND p.company_id = @company_id
          AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
        GROUP BY month
        ORDER BY month
        SQL;

        $results = $this->queryCached("sales_trend:{$this->companyId}:{$brand}:{$months}", $sql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $months,
        ]);

        return [
            'labels' => array_column($results, 'month'),
            'datasets' => [
                [
                    'label' => $brand,
                    'data' => array_map(fn ($r) => $this->toFloat($r['revenue']), $results),
                    'borderColor' => '#006654',
                    'backgroundColor' => 'rgba(0, 102, 84, 0.1)',
                ],
            ],
        ];
    }

    /**
     * Get monthly AOV (Average Order Value) trend for charting.
     */
    public function getAovTrend(string $brand, int $months = 12): array
    {
        $sql = <<<SQL
        SELECT
            FORMAT_DATE('%Y-%m', oi.order_date) as month,
            SAFE_DIVIDE(
                SUM(oi.revenue_realised_subtotal_excl),
                COUNT(DISTINCT oi.order_id)
            ) as aov
        FROM `{$this->dataset}.fact_order_item` oi
        JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
        WHERE p.brand = @brand
          AND p.company_id = @company_id
          AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
        GROUP BY month
        ORDER BY month
        SQL;

        $results = $this->queryCached("aov_trend:{$this->companyId}:{$brand}:{$months}", $sql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $months,
        ]);

        return [
            'labels' => array_column($results, 'month'),
            'datasets' => [
                [
                    'label' => 'Avg Order Value',
                    'data' => array_map(fn ($r) => round($this->toFloat($r['aov']), 2), $results),
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                ],
            ],
        ];
    }

    /**
     * Get top products by revenue for a brand.
     */
    public function getTopProducts(string $brand, int $limit = 10, string $period = '30d'): array
    {
        $days = $this->periodToDays($period);

        $sql = <<<SQL
        WITH current_period AS (
            SELECT
                p.sku,
                p.name,
                SUM(oi.revenue_realised_subtotal_excl) as revenue,
                SUM(oi.qty_ordered) as units
            FROM `{$this->dataset}.fact_order_item` oi
            JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
            WHERE p.brand = @brand
              AND p.company_id = @company_id
              AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @days DAY)
            GROUP BY p.sku, p.name
        ),
        previous_period AS (
            SELECT
                p.sku,
                SUM(oi.revenue_realised_subtotal_excl) as revenue
            FROM `{$this->dataset}.fact_order_item` oi
            JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
            WHERE p.brand = @brand
              AND p.company_id = @company_id
              AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @days_double DAY)
              AND oi.order_date < DATE_SUB(CURRENT_DATE(), INTERVAL @days DAY)
            GROUP BY p.sku
        )
        SELECT
            c.sku,
            c.name,
            c.revenue,
            c.units,
            SAFE_DIVIDE(c.revenue - COALESCE(p.revenue, 0), NULLIF(p.revenue, 0)) * 100 as growth
        FROM current_period c
        LEFT JOIN previous_period p ON c.sku = p.sku
        ORDER BY c.revenue DESC
        LIMIT @limit
        SQL;

        $results = $this->queryCached("top_products:{$this->companyId}:{$brand}:{$period}:{$limit}", $sql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'days' => $days,
            'days_double' => $days * 2,
            'limit' => $limit,
        ]);

        return array_map(fn ($row) => [
            'sku' => $row['sku'],
            'name' => $row['name'],
            'revenue' => $this->toFloat($row['revenue']),
            'units' => $this->toInt($row['units']),
            'growth' => isset($row['growth']) ? round($this->toFloat($row['growth']), 1) : null,
        ], $results);
    }

    /**
     * Get product performance table with monthly breakdown.
     */
    public function getProductPerformanceTable(string $brand, string $period = '12m'): array
    {
        $months = match ($period) {
            '3m' => 3,
            '6m' => 6,
            '12m' => 12,
            default => 12,
        };

        $sql = <<<SQL
        SELECT
            p.sku,
            p.name,
            COALESCE(p.primary_category, 'Uncategorized') as category,
            FORMAT_DATE('%Y-%m', oi.order_date) as month,
            SUM(oi.revenue_realised_subtotal_excl) as revenue
        FROM `{$this->dataset}.fact_order_item` oi
        JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
        WHERE p.brand = @brand
          AND p.company_id = @company_id
          AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
        GROUP BY p.sku, p.name, p.primary_category, month
        ORDER BY p.sku, month
        SQL;

        $results = $this->queryCached("product_table:{$this->companyId}:{$brand}:{$period}", $sql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $months,
        ]);

        $products = [];
        foreach ($results as $row) {
            $sku = $row['sku'];
            if (! isset($products[$sku])) {
                $products[$sku] = [
                    'sku' => $sku,
                    'name' => $row['name'],
                    'category' => $row['category'],
                    'months' => [],
                    'total' => 0,
                ];
            }
            $products[$sku]['months'][$row['month']] = $this->toFloat($row['revenue']);
            $products[$sku]['total'] += $this->toFloat($row['revenue']);
        }

        $productsList = array_values($products);
        usort($productsList, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $productsList;
    }

    /**
     * Get competitor comparison data.
     */
    public function getCompetitorComparison(string $brand, array $competitorBrands, string $period = '30d'): array
    {
        $days = $this->periodToDays($period);
        $allBrands = array_merge([$brand], $competitorBrands);

        $brandParams = [];
        $brandPlaceholders = [];
        foreach ($allBrands as $i => $b) {
            $key = "brand_{$i}";
            $brandParams[$key] = $b;
            $brandPlaceholders[] = "@{$key}";
        }
        $brandInClause = implode(', ', $brandPlaceholders);

        $sql = <<<SQL
        SELECT
            p.brand,
            FORMAT_DATE('%Y-%m', oi.order_date) as month,
            SUM(oi.revenue_realised_subtotal_excl) as revenue
        FROM `{$this->dataset}.fact_order_item` oi
        JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
        WHERE p.brand IN ({$brandInClause})
          AND p.company_id = @company_id
          AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @days DAY)
        GROUP BY p.brand, month
        ORDER BY month, p.brand
        SQL;

        $params = array_merge($brandParams, [
            'company_id' => $this->companyId,
            'days' => $days,
        ]);

        $cacheKey = 'competitor_comparison:'.$this->companyId.':'.md5(implode(',', $allBrands)).":{$period}";
        $results = $this->queryCached($cacheKey, $sql, $params);

        $months = [];
        $brandData = [];

        foreach ($results as $row) {
            $month = $row['month'];
            if (! in_array($month, $months)) {
                $months[] = $month;
            }
            $brandData[$row['brand']][$month] = $this->toFloat($row['revenue']);
        }

        sort($months);

        $datasets = [];
        $colors = ['#006654', '#3B82F6', '#F59E0B', '#EF4444'];
        $competitorLabels = ['Competitor A', 'Competitor B', 'Competitor C'];

        foreach ($allBrands as $i => $b) {
            $label = $b === $brand ? 'Your Brand' : ($competitorLabels[$i - 1] ?? "Competitor {$i}");
            $data = [];
            foreach ($months as $month) {
                $data[] = $brandData[$b][$month] ?? 0;
            }
            $datasets[] = [
                'label' => $label,
                'data' => $data,
                'borderColor' => $colors[$i] ?? '#6B7280',
                'backgroundColor' => $colors[$i] ?? '#6B7280',
            ];
        }

        return [
            'labels' => $months,
            'datasets' => $datasets,
        ];
    }

    /**
     * Get market share by category.
     */
    public function getMarketShareByCategory(string $brand, array $competitorBrands = [], string $period = '30d'): array
    {
        $days = $this->periodToDays($period);
        $allBrands = array_merge([$brand], $competitorBrands);

        $brandParams = [];
        $brandPlaceholders = [];
        foreach ($allBrands as $i => $b) {
            $key = "brand_{$i}";
            $brandParams[$key] = $b;
            $brandPlaceholders[] = "@{$key}";
        }
        $brandInClause = implode(', ', $brandPlaceholders);

        $sql = <<<SQL
        WITH category_totals AS (
            SELECT
                COALESCE(p.primary_category, 'Uncategorized') as category,
                COALESCE(p.category_level_2, '') as subcategory,
                p.brand,
                SUM(oi.revenue_realised_subtotal_excl) as revenue
            FROM `{$this->dataset}.fact_order_item` oi
            JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
            WHERE p.company_id = @company_id
              AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @days DAY)
            GROUP BY category, subcategory, p.brand
        ),
        category_sums AS (
            SELECT
                category,
                subcategory,
                SUM(revenue) as total_revenue
            FROM category_totals
            GROUP BY category, subcategory
        )
        SELECT
            ct.category,
            ct.subcategory,
            ct.brand,
            ct.revenue,
            cs.total_revenue,
            SAFE_DIVIDE(ct.revenue, cs.total_revenue) * 100 as market_share
        FROM category_totals ct
        JOIN category_sums cs ON ct.category = cs.category AND ct.subcategory = cs.subcategory
        WHERE ct.brand IN ({$brandInClause})
        ORDER BY ct.category, ct.subcategory, ct.brand
        SQL;

        $params = array_merge($brandParams, [
            'company_id' => $this->companyId,
            'days' => $days,
        ]);

        $cacheKey = 'market_share:'.$this->companyId.':'.md5(implode(',', $allBrands)).":{$period}";
        $results = $this->queryCached($cacheKey, $sql, $params);

        $categories = [];
        $competitorLabels = ['Competitor A', 'Competitor B', 'Competitor C'];

        foreach ($results as $row) {
            $key = $row['category'].'|'.$row['subcategory'];
            if (! isset($categories[$key])) {
                $categories[$key] = [
                    'category' => $row['category'],
                    'subcategory' => $row['subcategory'] ?: null,
                    'brand_share' => 0,
                    'competitor_shares' => [],
                ];
            }

            if ($row['brand'] === $brand) {
                $categories[$key]['brand_share'] = round($this->toFloat($row['market_share']), 1);
            } else {
                $competitorIndex = array_search($row['brand'], $competitorBrands);
                $label = $competitorLabels[$competitorIndex] ?? "Competitor {$competitorIndex}";
                $categories[$key]['competitor_shares'][$label] = round($this->toFloat($row['market_share']), 1);
            }
        }

        return array_values($categories);
    }

    /**
     * Get customer engagement metrics for a brand's products.
     */
    public function getCustomerEngagement(string $brand, string $period = '12m'): array
    {
        $months = match ($period) {
            '6m' => 6,
            '12m' => 12,
            default => 12,
        };

        $sql = <<<SQL
        WITH order_stats AS (
            SELECT
                p.sku,
                p.name,
                oi.customer_id,
                oi.order_id,
                oi.order_date,
                oi.qty_ordered as quantity,
                oi.revenue_realised_subtotal_excl as revenue
            FROM `{$this->dataset}.fact_order_item` oi
            JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
            WHERE p.brand = @brand
              AND p.company_id = @company_id
              AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
        ),
        customer_orders AS (
            SELECT
                sku,
                customer_id,
                COUNT(DISTINCT order_id) as order_count,
                MIN(order_date) as first_order,
                MAX(order_date) as last_order
            FROM order_stats
            GROUP BY sku, customer_id
        ),
        product_metrics AS (
            SELECT
                o.sku,
                ANY_VALUE(o.name) as name,
                AVG(o.quantity) as avg_qty_per_order
            FROM order_stats o
            GROUP BY o.sku
        ),
        reorder_stats AS (
            SELECT
                sku,
                SAFE_DIVIDE(COUNTIF(order_count > 1), COUNT(*)) * 100 as reorder_rate,
                AVG(CASE WHEN order_count > 1 THEN DATE_DIFF(last_order, first_order, DAY) / (order_count - 1) / 30.0 END) as avg_frequency_months
            FROM customer_orders
            GROUP BY sku
        )
        SELECT
            pm.sku,
            pm.name,
            pm.avg_qty_per_order,
            COALESCE(rs.reorder_rate, 0) as reorder_rate,
            rs.avg_frequency_months,
            0 as promo_intensity
        FROM product_metrics pm
        LEFT JOIN reorder_stats rs ON pm.sku = rs.sku
        ORDER BY pm.sku
        SQL;

        $results = $this->queryCached("customer_engagement:{$this->companyId}:{$brand}:{$period}", $sql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $months,
        ]);

        return array_map(fn ($row) => [
            'sku' => $row['sku'],
            'name' => $row['name'],
            'avg_qty_per_order' => round($this->toFloat($row['avg_qty_per_order'] ?? 0), 2),
            'reorder_rate' => round($this->toFloat($row['reorder_rate'] ?? 0), 1),
            'avg_frequency_months' => isset($row['avg_frequency_months']) ? round($this->toFloat($row['avg_frequency_months']), 1) : null,
            'promo_intensity' => 0,
        ], $results);
    }

    /**
     * Get stock and supply chain data.
     * Note: fact_inventory table doesn't exist - returning empty data structure.
     */
    public function getStockSupply(string $brand, int $months = 12): array
    {
        // Get products with their stock info from dim_product
        $sql = <<<SQL
        SELECT
            p.sku,
            p.name,
            COALESCE(p.total_stock_on_hand, 0) as stock_on_hand,
            COALESCE(p.stock_cpt_live, 0) + COALESCE(p.stock_jhb_live, 0) as live_stock
        FROM `{$this->dataset}.dim_product` p
        WHERE p.brand = @brand
          AND p.company_id = @company_id
        ORDER BY p.sku
        LIMIT 100
        SQL;

        $results = $this->queryCached("stock_supply:{$this->companyId}:{$brand}:{$months}", $sql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
        ]);

        return [
            'sell_in' => [],
            'sell_out' => [],
            'closing_stock' => array_map(fn ($row) => [
                'sku' => $row['sku'],
                'name' => $row['name'],
                'current_stock' => $this->toInt($row['stock_on_hand']),
            ], $results),
        ];
    }

    /**
     * Get purchase orders with OTIF metrics.
     * Note: fact_purchase_orders table doesn't exist - returning empty structure.
     */
    public function getPurchaseOrders(string $brand, int $months = 12): array
    {
        return [
            'summary' => [
                'total_pos' => 0,
                'on_time_pct' => 0,
                'in_full_pct' => 0,
                'otif_pct' => 0,
            ],
            'monthly' => [],
            'orders' => [],
        ];
    }

    /**
     * Get purchase order line items.
     */
    public function getPurchaseOrderLines(string $poNumber): array
    {
        return [];
    }

    /**
     * Get sales forecast based on historical data.
     */
    public function getSalesForecast(string $brand, int $historyMonths = 12, int $forecastMonths = 6): array
    {
        // Get historical monthly sales
        $sql = <<<SQL
        SELECT
            FORMAT_DATE('%Y-%m', oi.order_date) as month,
            SUM(oi.revenue_realised_subtotal_excl) as revenue,
            SUM(oi.qty_ordered) as units
        FROM `{$this->dataset}.fact_order_item` oi
        JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
        WHERE p.brand = @brand
          AND p.company_id = @company_id
          AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
        GROUP BY month
        ORDER BY month
        SQL;

        $historical = $this->queryCached("forecast_history:{$this->companyId}:{$brand}:{$historyMonths}", $sql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $historyMonths,
        ]);

        // Simple forecast: use average of last 3 months
        $recentRevenue = array_slice(array_column($historical, 'revenue'), -3);
        $avgRevenue = count($recentRevenue) > 0 ? array_sum($recentRevenue) / count($recentRevenue) : 0;

        $forecast = [];
        for ($i = 1; $i <= $forecastMonths; $i++) {
            $forecastMonth = now()->addMonths($i)->format('Y-m');
            $forecast[] = [
                'month' => $forecastMonth,
                'revenue' => round($avgRevenue * (1 + (rand(-10, 10) / 100)), 2), // Add some variance
                'units' => 0,
                'is_forecast' => true,
            ];
        }

        return [
            'historical' => array_map(fn ($row) => [
                'month' => $row['month'],
                'revenue' => $this->toFloat($row['revenue']),
                'units' => $this->toInt($row['units']),
                'is_forecast' => false,
            ], $historical),
            'forecast' => $forecast,
        ];
    }

    /**
     * Get category-level forecast.
     */
    public function getCategoryForecast(string $brand, int $historyMonths = 12, int $forecastMonths = 6): array
    {
        $sql = <<<SQL
        SELECT
            COALESCE(p.primary_category, 'Uncategorized') as category,
            FORMAT_DATE('%Y-%m', oi.order_date) as month,
            SUM(oi.revenue_realised_subtotal_excl) as revenue
        FROM `{$this->dataset}.fact_order_item` oi
        JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
        WHERE p.brand = @brand
          AND p.company_id = @company_id
          AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
        GROUP BY category, month
        ORDER BY category, month
        SQL;

        $results = $this->queryCached("category_forecast:{$this->companyId}:{$brand}:{$historyMonths}", $sql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $historyMonths,
        ]);

        $categories = [];
        foreach ($results as $row) {
            $cat = $row['category'];
            if (! isset($categories[$cat])) {
                $categories[$cat] = ['category' => $cat, 'historical' => [], 'forecast' => []];
            }
            $categories[$cat]['historical'][] = [
                'month' => $row['month'],
                'revenue' => $this->toFloat($row['revenue']),
            ];
        }

        return array_values($categories);
    }

    /**
     * Get cohort analysis data.
     */
    public function getCohortAnalysis(string $brand, int $monthsBack = 12): array
    {
        $sql = <<<SQL
        WITH first_purchases AS (
            SELECT
                oi.customer_id,
                FORMAT_DATE('%Y-%m', MIN(oi.order_date)) as cohort_month
            FROM `{$this->dataset}.fact_order_item` oi
            JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
            WHERE p.brand = @brand
              AND p.company_id = @company_id
            GROUP BY oi.customer_id
        ),
        customer_activity AS (
            SELECT
                fp.customer_id,
                fp.cohort_month,
                FORMAT_DATE('%Y-%m', oi.order_date) as activity_month,
                SUM(oi.revenue_realised_subtotal_excl) as revenue
            FROM first_purchases fp
            JOIN `{$this->dataset}.fact_order_item` oi ON fp.customer_id = oi.customer_id
            JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
            WHERE p.brand = @brand
              AND p.company_id = @company_id
              AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
            GROUP BY fp.customer_id, fp.cohort_month, activity_month
        )
        SELECT
            cohort_month,
            activity_month,
            COUNT(DISTINCT customer_id) as customers,
            SUM(revenue) as revenue
        FROM customer_activity
        GROUP BY cohort_month, activity_month
        ORDER BY cohort_month, activity_month
        SQL;

        $results = $this->queryCached("cohort:{$this->companyId}:{$brand}:{$monthsBack}", $sql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);

        $cohorts = [];
        foreach ($results as $row) {
            $cohort = $row['cohort_month'];
            if (! isset($cohorts[$cohort])) {
                $cohorts[$cohort] = ['cohort' => $cohort, 'periods' => []];
            }
            $cohorts[$cohort]['periods'][$row['activity_month']] = [
                'customers' => $this->toInt($row['customers']),
                'revenue' => $this->toFloat($row['revenue']),
            ];
        }

        return array_values($cohorts);
    }

    /**
     * Get RFM analysis data using fact_customer_rfm_history if available.
     */
    public function getRfmAnalysis(string $brand, int $monthsBack = 12): array
    {
        // Calculate RFM from order data
        $sql = <<<SQL
        WITH customer_metrics AS (
            SELECT
                oi.customer_id,
                DATE_DIFF(CURRENT_DATE(), MAX(oi.order_date), DAY) as recency_days,
                COUNT(DISTINCT oi.order_id) as frequency,
                SUM(oi.revenue_realised_subtotal_excl) as monetary
            FROM `{$this->dataset}.fact_order_item` oi
            JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
            WHERE p.brand = @brand
              AND p.company_id = @company_id
              AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
            GROUP BY oi.customer_id
        ),
        rfm_scores AS (
            SELECT
                customer_id,
                recency_days,
                frequency,
                monetary,
                NTILE(5) OVER (ORDER BY recency_days DESC) as r_score,
                NTILE(5) OVER (ORDER BY frequency) as f_score,
                NTILE(5) OVER (ORDER BY monetary) as m_score
            FROM customer_metrics
        )
        SELECT
            CONCAT(CAST(r_score AS STRING), CAST(f_score AS STRING), CAST(m_score AS STRING)) as rfm_segment,
            COUNT(*) as customer_count,
            AVG(recency_days) as avg_recency,
            AVG(frequency) as avg_frequency,
            AVG(monetary) as avg_monetary
        FROM rfm_scores
        GROUP BY rfm_segment
        ORDER BY rfm_segment
        SQL;

        $results = $this->queryCached("rfm:{$this->companyId}:{$brand}:{$monthsBack}", $sql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);

        return array_map(fn ($row) => [
            'segment' => $row['rfm_segment'],
            'customer_count' => $this->toInt($row['customer_count']),
            'avg_recency' => round($this->toFloat($row['avg_recency']), 1),
            'avg_frequency' => round($this->toFloat($row['avg_frequency']), 2),
            'avg_monetary' => round($this->toFloat($row['avg_monetary']), 2),
        ], $results);
    }

    /**
     * Get retention analysis.
     */
    public function getRetentionAnalysis(string $brand, int $monthsBack = 12, string $period = 'monthly'): array
    {
        $sql = <<<SQL
        WITH monthly_customers AS (
            SELECT
                FORMAT_DATE('%Y-%m', oi.order_date) as month,
                oi.customer_id
            FROM `{$this->dataset}.fact_order_item` oi
            JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
            WHERE p.brand = @brand
              AND p.company_id = @company_id
              AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
            GROUP BY month, customer_id
        ),
        month_pairs AS (
            SELECT
                m1.month as month,
                COUNT(DISTINCT m1.customer_id) as total_customers,
                COUNT(DISTINCT m2.customer_id) as retained_customers
            FROM monthly_customers m1
            LEFT JOIN monthly_customers m2
                ON m1.customer_id = m2.customer_id
                AND m2.month = FORMAT_DATE('%Y-%m', DATE_ADD(PARSE_DATE('%Y-%m', m1.month), INTERVAL 1 MONTH))
            GROUP BY m1.month
        )
        SELECT
            month,
            total_customers,
            retained_customers,
            SAFE_DIVIDE(retained_customers, total_customers) * 100 as retention_rate
        FROM month_pairs
        ORDER BY month
        SQL;

        $results = $this->queryCached("retention:{$this->companyId}:{$brand}:{$monthsBack}:{$period}", $sql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);

        return array_map(fn ($row) => [
            'period' => $row['month'],
            'total_customers' => $this->toInt($row['total_customers']),
            'retained_customers' => $this->toInt($row['retained_customers']),
            'retention_rate' => round($this->toFloat($row['retention_rate'] ?? 0), 1),
        ], $results);
    }

    /**
     * Get product list for a brand.
     */
    public function getProductList(string $brand, int $limit = 100): array
    {
        $sql = <<<SQL
        SELECT
            p.sku,
            p.name,
            COALESCE(p.primary_category, 'Uncategorized') as category,
            p.price,
            COALESCE(p.total_stock_on_hand, 0) as stock
        FROM `{$this->dataset}.dim_product` p
        WHERE p.brand = @brand
          AND p.company_id = @company_id
        ORDER BY p.name
        LIMIT @limit
        SQL;

        $results = $this->queryCached("product_list:{$this->companyId}:{$brand}:{$limit}", $sql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'limit' => $limit,
        ]);

        return array_map(fn ($row) => [
            'sku' => $row['sku'],
            'name' => $row['name'],
            'category' => $row['category'],
            'price' => $this->toFloat($row['price'] ?? 0),
            'stock' => $this->toInt($row['stock'] ?? 0),
        ], $results);
    }

    /**
     * Get product deep dive data.
     */
    public function getProductDeepDive(string $brand, string $sku, int $monthsBack = 12): array
    {
        // Get product info
        $productSql = <<<SQL
        SELECT
            p.sku,
            p.name,
            p.brand,
            COALESCE(p.primary_category, 'Uncategorized') as category,
            p.price,
            p.cost_price,
            COALESCE(p.total_stock_on_hand, 0) as stock
        FROM `{$this->dataset}.dim_product` p
        WHERE p.sku = @sku
          AND p.company_id = @company_id
        LIMIT 1
        SQL;

        $productResult = $this->queryCached("product_info:{$this->companyId}:{$sku}", $productSql, [
            'sku' => $sku,
            'company_id' => $this->companyId,
        ]);

        $product = $productResult[0] ?? null;
        if (! $product) {
            return ['product' => null, 'sales_trend' => [], 'metrics' => []];
        }

        // Get monthly sales trend
        $trendSql = <<<SQL
        SELECT
            FORMAT_DATE('%Y-%m', oi.order_date) as month,
            SUM(oi.revenue_realised_subtotal_excl) as revenue,
            SUM(oi.qty_ordered) as units
        FROM `{$this->dataset}.fact_order_item` oi
        WHERE oi.sku = @sku
          AND oi.company_id = @company_id
          AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
        GROUP BY month
        ORDER BY month
        SQL;

        $trendResults = $this->queryCached("product_trend:{$this->companyId}:{$sku}:{$monthsBack}", $trendSql, [
            'sku' => $sku,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);

        return [
            'product' => [
                'sku' => $product['sku'],
                'name' => $product['name'],
                'brand' => $product['brand'],
                'category' => $product['category'],
                'price' => $this->toFloat($product['price'] ?? 0),
                'cost_price' => $this->toFloat($product['cost_price'] ?? 0),
                'stock' => $this->toInt($product['stock'] ?? 0),
            ],
            'sales_trend' => array_map(fn ($row) => [
                'month' => $row['month'],
                'revenue' => $this->toFloat($row['revenue']),
                'units' => $this->toInt($row['units']),
            ], $trendResults),
            'metrics' => [],
        ];
    }

    /**
     * Get marketing analytics.
     */
    public function getMarketingAnalytics(string $brand, int $monthsBack = 12): array
    {
        $sql = <<<SQL
        SELECT
            COALESCE(o.channel, 'Unknown') as channel,
            COALESCE(o.source, 'Unknown') as source,
            COALESCE(o.medium, 'Unknown') as medium,
            COUNT(DISTINCT o.order_id) as orders,
            SUM(oi.revenue_realised_subtotal_excl) as revenue,
            COUNT(DISTINCT o.customer_id) as customers
        FROM `{$this->dataset}.fact_order` o
        JOIN `{$this->dataset}.fact_order_item` oi ON o.order_id = oi.order_id AND o.company_id = oi.company_id
        JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
        WHERE p.brand = @brand
          AND p.company_id = @company_id
          AND o.order_datetime >= TIMESTAMP_SUB(CURRENT_TIMESTAMP(), INTERVAL @months MONTH)
          AND o.is_cancelled = FALSE
        GROUP BY channel, source, medium
        ORDER BY revenue DESC
        LIMIT 50
        SQL;

        $results = $this->queryCached("marketing:{$this->companyId}:{$brand}:{$monthsBack}", $sql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);

        return array_map(fn ($row) => [
            'channel' => $row['channel'],
            'source' => $row['source'],
            'medium' => $row['medium'],
            'orders' => $this->toInt($row['orders']),
            'revenue' => $this->toFloat($row['revenue']),
            'customers' => $this->toInt($row['customers']),
        ], $results);
    }

    /**
     * Get subscription overview.
     * Note: No subscription tables exist - returning empty data.
     */
    public function getSubscriptionOverview(string $brand, int $monthsBack = 12): array
    {
        return [
            'active_subscriptions' => 0,
            'monthly_recurring_revenue' => 0,
            'churn_rate' => 0,
            'new_subscriptions' => 0,
            'trend' => [],
        ];
    }

    /**
     * Get subscription products.
     */
    public function getSubscriptionProducts(string $brand, int $monthsBack = 12): array
    {
        return [];
    }

    /**
     * Get subscription predictions.
     */
    public function getSubscriptionPredictions(string $brand): array
    {
        return [
            'at_risk_customers' => [],
            'upsell_opportunities' => [],
            'churn_predictions' => [],
        ];
    }

    /**
     * Get price history.
     * Note: No price scraping tables exist - returning empty data.
     */
    public function getPriceHistory(string $productId, ?string $competitorName = null, string $period = '90d'): array
    {
        return [
            'labels' => [],
            'datasets' => [],
        ];
    }

    /**
     * Get competitor prices.
     */
    public function getCompetitorPrices(?string $productId = null, ?string $category = null, int $limit = 100): array
    {
        return [];
    }

    /**
     * Get price alert triggers.
     */
    public function getPriceAlertTriggers(?string $alertType = null, string $period = '7d'): array
    {
        return [
            'price_drops' => [],
            'competitor_beats' => [],
            'out_of_stock' => [],
            'price_changes' => [],
        ];
    }

    /**
     * Get pricing KPIs.
     */
    public function getPricingKpis(): array
    {
        return [
            'avg_margin' => 0,
            'price_competitiveness' => 0,
            'products_below_target' => 0,
            'products_above_market' => 0,
            'products_tracked' => 0,
            'avg_market_position' => 'unknown',
            'products_cheapest' => 0,
            'products_most_expensive' => 0,
            'recent_price_changes' => 0,
            'active_competitor_undercuts' => 0,
        ];
    }
}
