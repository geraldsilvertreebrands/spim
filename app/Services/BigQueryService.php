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
     * Get chart colors from config.
     *
     * @return array<int, string>
     */
    public function getChartColors(): array
    {
        return config('charts.colors', [
            '#264653', '#287271', '#2a9d8f', '#8ab17d', '#e9c46a',
            '#f4a261', '#ec8151', '#e36040', '#bc6b85', '#9576c9',
        ]);
    }

    /**
     * Get chart background colors (with transparency).
     *
     * @return array<int, string>
     */
    public function getChartBackgrounds(): array
    {
        return config('charts.backgrounds', [
            'rgba(38, 70, 83, 0.1)', 'rgba(40, 114, 113, 0.1)', 'rgba(42, 157, 143, 0.1)',
            'rgba(138, 177, 125, 0.1)', 'rgba(233, 196, 106, 0.1)', 'rgba(244, 162, 97, 0.1)',
            'rgba(236, 129, 81, 0.1)', 'rgba(227, 96, 64, 0.1)', 'rgba(188, 107, 133, 0.1)',
            'rgba(149, 118, 201, 0.1)',
        ]);
    }

    /**
     * Get competitor chart colors.
     *
     * @return array<string, string>
     */
    public function getCompetitorColors(): array
    {
        return config('charts.competitors', [
            'your_brand' => '#264653',
            'competitor_a' => '#2a9d8f',
            'competitor_b' => '#e9c46a',
            'competitor_c' => '#e36040',
        ]);
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
                // Convert BigQuery row to plain array with primitive values
                // to ensure Livewire can serialize the data
                $plainRow = [];
                foreach ($row as $key => $value) {
                    $plainRow[$key] = $this->convertBigQueryValue($value);
                }
                $rows[] = $plainRow;

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
     * Convert BigQuery value to a primitive type that can be serialized by Livewire.
     *
     * @param  mixed  $value
     * @return mixed
     */
    private function convertBigQueryValue($value)
    {
        if ($value === null) {
            return null;
        }

        // Handle Google Cloud BigQuery Numeric type
        if ($value instanceof \Google\Cloud\BigQuery\Numeric) {
            return (float) (string) $value;
        }

        // Handle Google Cloud Core Date type
        if ($value instanceof \Google\Cloud\Core\Date) {
            return $value->formatAsString();
        }

        // Handle Google Cloud BigQuery Date type
        if ($value instanceof \Google\Cloud\BigQuery\Date) {
            return (string) $value;
        }

        // Handle Google Cloud BigQuery Timestamp type
        if ($value instanceof \Google\Cloud\BigQuery\Timestamp) {
            return $value->formatAsString();
        }

        // Handle Google Cloud BigQuery Time type
        if ($value instanceof \Google\Cloud\BigQuery\Time) {
            return (string) $value;
        }

        // Handle Google Cloud BigQuery Bytes type
        if ($value instanceof \Google\Cloud\BigQuery\Bytes) {
            return base64_encode((string) $value);
        }

        // Handle any other objects by converting to string
        if (is_object($value)) {
            return (string) $value;
        }

        // Handle arrays recursively
        if (is_array($value)) {
            return array_map([$this, 'convertBigQueryValue'], $value);
        }

        // Return primitive types as-is
        return $value;
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
                    'borderColor' => $this->getChartColors()[0],
                    'backgroundColor' => $this->getChartBackgrounds()[0],
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
                    'borderColor' => $this->getChartColors()[4], // Gold
                    'backgroundColor' => $this->getChartBackgrounds()[4],
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
        $competitorColors = $this->getCompetitorColors();
        $colors = [
            $competitorColors['your_brand'],
            $competitorColors['competitor_a'],
            $competitorColors['competitor_b'],
            $competitorColors['competitor_c'],
        ];
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
                'borderColor' => $colors[$i] ?? $this->getChartColors()[0],
                'backgroundColor' => $colors[$i] ?? $this->getChartColors()[0],
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
                oi.revenue_realised_subtotal_excl as revenue,
                oi.price_excl,
                -- Detect discount: if actual revenue < expected revenue (qty * price)
                CASE
                    WHEN oi.revenue_realised_subtotal_excl < (oi.qty_ordered * oi.price_excl * 0.99)
                    THEN oi.qty_ordered
                    ELSE 0
                END as discounted_units
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
                AVG(o.quantity) as avg_qty_per_order,
                SUM(o.quantity) as total_units,
                SUM(o.discounted_units) as total_discounted_units
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
            -- Promo intensity: % of units sold on discount
            COALESCE(SAFE_DIVIDE(pm.total_discounted_units, pm.total_units) * 100, 0) as promo_intensity
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
            'promo_intensity' => round($this->toFloat($row['promo_intensity'] ?? 0), 1),
        ], $results);
    }

    /**
     * Get stock and supply chain data.
     * Note: fact_inventory table doesn't exist - returning empty data structure.
     */
    public function getStockSupply(string $brand, int $months = 12): array
    {
        // Get sell-out data (units sold by month) from fact_order_item
        $sellOutSql = <<<SQL
        SELECT
            p.sku,
            p.name,
            FORMAT_DATE('%Y-%m', oi.order_date) as month,
            SUM(oi.qty_shipped) as units
        FROM `{$this->dataset}.fact_order_item` oi
        JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
        WHERE p.brand = @brand
          AND p.company_id = @company_id
          AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
        GROUP BY p.sku, p.name, month
        ORDER BY p.sku, month
        SQL;

        $sellOutResults = $this->queryCached("sell_out:{$this->companyId}:{$brand}:{$months}", $sellOutSql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $months,
        ]);

        // Transform sell-out results into product-centric format
        $sellOutByProduct = [];
        foreach ($sellOutResults as $row) {
            $sku = $row['sku'];
            if (! isset($sellOutByProduct[$sku])) {
                $sellOutByProduct[$sku] = [
                    'sku' => $sku,
                    'name' => $row['name'],
                    'months' => [],
                ];
            }
            $sellOutByProduct[$sku]['months'][$row['month']] = $this->toInt($row['units']);
        }

        // Get closing stock data from stock snapshot (FtN-specific dataset)
        // Note: This uses a different dataset (ftn_dw_prod) for stock snapshots
        $stockDataset = 'ftn_dw_prod';
        $closingStockSql = <<<SQL
        SELECT
            p.sku,
            p.name,
            FORMAT_DATE('%Y-%m', s.cycle_date) as month,
            AVG(s.stock_on_hand) as stock_on_hand
        FROM `silvertreepoc.{$stockDataset}.fact_ac_stock_snapshot` s
        JOIN `{$this->dataset}.dim_product` p ON s.sku = p.sku AND p.company_id = @company_id
        WHERE p.brand = @brand
          AND p.company_id = @company_id
          AND s.cycle_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
          AND EXTRACT(DAY FROM s.cycle_date) BETWEEN 1 AND 7
        GROUP BY p.sku, p.name, month
        ORDER BY p.sku, month
        SQL;

        $closingStockResults = $this->queryCached("closing_stock:{$this->companyId}:{$brand}:{$months}", $closingStockSql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $months,
        ]);

        // Transform closing stock results into product-centric format
        $closingStockByProduct = [];
        foreach ($closingStockResults as $row) {
            $sku = $row['sku'];
            if (! isset($closingStockByProduct[$sku])) {
                $closingStockByProduct[$sku] = [
                    'sku' => $sku,
                    'name' => $row['name'],
                    'months' => [],
                ];
            }
            $closingStockByProduct[$sku]['months'][$row['month']] = $this->toInt($row['stock_on_hand']);
        }

        return [
            'sell_in' => [], // Purchase receipts data not available
            'sell_out' => array_values($sellOutByProduct),
            'closing_stock' => array_values($closingStockByProduct),
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

        // Convert BigQuery Numeric types to float before using array_sum
        $revenues = array_map(fn ($row) => $this->toFloat($row['revenue']), $historical);
        $recentRevenue = array_slice($revenues, -3);
        $avgRevenue = count($recentRevenue) > 0 ? array_sum($recentRevenue) / count($recentRevenue) : 0;

        // Generate forecast with baseline, optimistic, and pessimistic scenarios
        $forecast = [];
        for ($i = 1; $i <= $forecastMonths; $i++) {
            $forecastMonth = now()->addMonths($i)->format('Y-m');
            $baseline = round($avgRevenue * (1 + (rand(-5, 5) / 100)), 2);
            $forecast[] = [
                'month' => $forecastMonth,
                'baseline' => $baseline,
                'optimistic' => round($baseline * 1.15, 2),
                'pessimistic' => round($baseline * 0.90, 2),
                'lower_bound' => round($baseline * 0.85, 2),
                'upper_bound' => round($baseline * 1.20, 2),
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
            HAVING MIN(oi.order_date) >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
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

        // Collect all unique months for column headers
        $allMonths = [];
        $rawCohorts = [];

        foreach ($results as $row) {
            $cohort = $row['cohort_month'];
            $activityMonth = $row['activity_month'];
            $allMonths[$activityMonth] = true;

            if (! isset($rawCohorts[$cohort])) {
                $rawCohorts[$cohort] = [
                    'cohort' => $cohort,
                    'periods' => [],
                    'initial_customers' => 0,
                ];
            }
            $customers = $this->toInt($row['customers']);
            $rawCohorts[$cohort]['periods'][$activityMonth] = [
                'customers' => $customers,
                'revenue' => $this->toFloat($row['revenue']),
            ];

            // Track initial customers (cohort month = activity month)
            if ($cohort === $activityMonth) {
                $rawCohorts[$cohort]['initial_customers'] = $customers;
            }
        }

        // Sort months chronologically
        $months = array_keys($allMonths);
        sort($months);

        // Transform to retention format expected by the page
        // Each cohort has: initial_customers, retention[0..N] as percentages
        $cohortData = [];
        foreach ($rawCohorts as $cohortMonth => $data) {
            $initialCustomers = $data['initial_customers'] ?: 1; // Avoid division by zero

            // Build retention array indexed by period number (0 = acquisition month, 1 = first month after, etc.)
            $retention = [];
            $sortedPeriods = array_keys($data['periods']);
            sort($sortedPeriods);

            foreach ($sortedPeriods as $periodIndex => $periodMonth) {
                $customers = $data['periods'][$periodMonth]['customers'];
                $retentionRate = ($customers / $initialCustomers) * 100;
                $retention[$periodIndex] = round($retentionRate, 1);
            }

            // Build customers and revenue arrays indexed by period number for the view
            $customersArray = [];
            $revenueArray = [];
            foreach ($sortedPeriods as $periodIndex => $periodMonth) {
                $customersArray[$periodIndex] = $data['periods'][$periodMonth]['customers'];
                $revenueArray[$periodIndex] = $data['periods'][$periodMonth]['revenue'];
            }

            $cohortData[$cohortMonth] = [
                'size' => $initialCustomers,
                'initial_customers' => $initialCustomers,
                'retention' => $retention,
                'customers' => $customersArray,
                'revenue' => $revenueArray,
                'periods' => $data['periods'],
            ];
        }

        return [
            'cohorts' => $cohortData,
            'months' => $months,
        ];
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

        // Build matrix data (for visualization)
        $matrix = [];

        // Initialize segment accumulators for calculating averages
        $segmentNames = [
            'Champions', 'Loyal Customers', 'Potential Loyalists', 'New Customers',
            'Promising', 'Need Attention', 'About to Sleep', 'At Risk', 'Hibernating', 'Lost',
        ];
        $segmentAccum = [];
        foreach ($segmentNames as $name) {
            $segmentAccum[$name] = [
                'count' => 0,
                'total_revenue' => 0,
                'total_r' => 0,
                'total_f' => 0,
                'total_m' => 0,
            ];
        }

        foreach ($results as $row) {
            $rfmCode = $row['rfm_segment'] ?? '';
            $r = (int) substr($rfmCode, 0, 1);
            $f = (int) substr($rfmCode, 1, 1);
            $m = (int) substr($rfmCode, 2, 1);
            $count = $this->toInt($row['customer_count']);
            $avgMonetary = $this->toFloat($row['avg_monetary']);

            // Add to matrix
            $matrix[] = [
                'r_score' => $r,
                'f_score' => $f,
                'm_score' => $m,
                'count' => $count,
                'avg_recency' => round($this->toFloat($row['avg_recency']), 1),
                'avg_frequency' => round($this->toFloat($row['avg_frequency']), 2),
                'avg_monetary' => round($avgMonetary, 2),
            ];

            // Map to named segment and accumulate
            $segmentName = $this->mapRfmToSegment($r, $f, $m);
            $segmentAccum[$segmentName]['count'] += $count;
            $segmentAccum[$segmentName]['total_revenue'] += $avgMonetary * $count;
            $segmentAccum[$segmentName]['total_r'] += $r * $count;
            $segmentAccum[$segmentName]['total_f'] += $f * $count;
            $segmentAccum[$segmentName]['total_m'] += $m * $count;
        }

        // Build final segments with averages
        $segments = [];
        foreach ($segmentNames as $name) {
            $acc = $segmentAccum[$name];
            $count = $acc['count'];
            $segments[$name] = [
                'count' => $count,
                'avg_revenue' => $count > 0 ? round($acc['total_revenue'] / $count, 2) : 0,
                'r_avg' => $count > 0 ? round($acc['total_r'] / $count, 1) : 0,
                'f_avg' => $count > 0 ? round($acc['total_f'] / $count, 1) : 0,
                'm_avg' => $count > 0 ? round($acc['total_m'] / $count, 1) : 0,
            ];
        }

        return [
            'segments' => $segments,
            'matrix' => $matrix,
        ];
    }

    /**
     * Map RFM scores to segment name.
     */
    private function mapRfmToSegment(int $r, int $f, int $m): string
    {
        // Champions: Recent, frequent, high spenders
        if ($r >= 4 && $f >= 4 && $m >= 4) {
            return 'Champions';
        }
        // Loyal Customers: Good across all dimensions
        if ($r >= 3 && $f >= 4 && $m >= 3) {
            return 'Loyal Customers';
        }
        // Potential Loyalists: Recent with moderate F/M
        if ($r >= 4 && $f >= 2 && $f <= 4 && $m >= 2) {
            return 'Potential Loyalists';
        }
        // New Customers: Very recent, low frequency
        if ($r >= 4 && $f <= 2) {
            return 'New Customers';
        }
        // Promising: Recent-ish with low F/M
        if ($r >= 3 && $r <= 4 && $f <= 2) {
            return 'Promising';
        }
        // At Risk: Were valuable but haven't bought recently
        if ($r <= 2 && $f >= 3 && $m >= 3) {
            return 'At Risk';
        }
        // Need Attention: Average customers declining
        if ($r >= 2 && $r <= 3 && $f >= 2 && $f <= 3 && $m >= 2) {
            return 'Need Attention';
        }
        // About to Sleep: Below average, at risk of churning
        if ($r >= 2 && $r <= 3 && $f <= 2) {
            return 'About to Sleep';
        }
        // Hibernating: Long gone, low value
        if ($r <= 2 && $f <= 2 && $m >= 2) {
            return 'Hibernating';
        }

        // Lost: Lowest across all
        return 'Lost';
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

        // Transform data to match page expectations
        $retentionData = array_map(function ($row) {
            $totalCustomers = $this->toInt($row['total_customers']);
            $retainedCustomers = $this->toInt($row['retained_customers']);
            $churnedCustomers = $totalCustomers - $retainedCustomers;
            $retentionRate = round($this->toFloat($row['retention_rate'] ?? 0), 1);
            $churnRate = round(100 - $retentionRate, 1);

            return [
                'month' => $row['month'],
                'retained' => $retainedCustomers,
                'churned' => $churnedCustomers,
                'retention_rate' => $retentionRate,
                'churn_rate' => $churnRate,
            ];
        }, $results);

        return [
            'retention' => $retentionData,
        ];
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
            COALESCE(p.secondary_category, '') as subcategory,
            p.price,
            p.cost_price,
            COALESCE(p.total_stock_on_hand, 0) as stock
        FROM `{$this->dataset}.dim_product` p
        WHERE p.sku = @sku
          AND p.company_id = @company_id
        LIMIT 1
        SQL;

        $productResult = $this->queryCached("product_info_v2:{$this->companyId}:{$sku}", $productSql, [
            'sku' => $sku,
            'company_id' => $this->companyId,
        ]);

        $product = $productResult[0] ?? null;
        if (! $product) {
            return [
                'product_info' => [],
                'performance' => [],
                'customer' => [],
                'price' => [],
                'trend' => [],
                'comparison' => [],
            ];
        }

        // Get comprehensive product metrics
        $metricsSql = <<<SQL
        SELECT
            -- Performance metrics
            SUM(oi.revenue_realised_subtotal_excl) as total_revenue,
            COUNT(DISTINCT o.order_id) as total_orders,
            SUM(oi.qty_ordered) as total_units,
            AVG(oi.revenue_realised_subtotal_excl) as avg_order_value,
            AVG(oi.price_realised_excl) as avg_price,
            -- Customer metrics
            COUNT(DISTINCT o.customer_id) as unique_customers,
            AVG(oi.qty_ordered) as avg_qty_per_order,
            -- Price metrics
            MIN(oi.price_realised_excl) as min_price,
            MAX(oi.price_realised_excl) as max_price,
            -- Promo analysis
            COUNT(DISTINCT CASE WHEN o.coupon_code IS NOT NULL AND TRIM(o.coupon_code) != '' THEN o.order_id END) as promo_orders,
            AVG(CASE WHEN o.coupon_code IS NOT NULL AND TRIM(o.coupon_code) != '' THEN o.discount_excl ELSE NULL END) as avg_discount
        FROM `{$this->dataset}.fact_order_item` oi
        JOIN `{$this->dataset}.fact_order` o ON oi.order_id = o.order_id AND oi.company_id = o.company_id
        WHERE oi.sku = @sku
          AND oi.company_id = @company_id
          AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
          AND o.is_cancelled = FALSE
        SQL;

        $metricsResults = $this->queryCached("product_metrics_v2:{$this->companyId}:{$sku}:{$monthsBack}", $metricsSql, [
            'sku' => $sku,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);

        $metrics = $metricsResults[0] ?? [];
        $totalOrders = $this->toInt($metrics['total_orders'] ?? 0);
        $promoOrders = $this->toInt($metrics['promo_orders'] ?? 0);
        $uniqueCustomers = $this->toInt($metrics['unique_customers'] ?? 0);

        // Calculate reorder rate - customers who bought more than once
        $reorderSql = <<<SQL
        SELECT
            COUNT(DISTINCT customer_id) as repeat_customers
        FROM (
            SELECT
                o.customer_id,
                COUNT(DISTINCT o.order_id) as order_count
            FROM `{$this->dataset}.fact_order_item` oi
            JOIN `{$this->dataset}.fact_order` o ON oi.order_id = o.order_id AND oi.company_id = o.company_id
            WHERE oi.sku = @sku
              AND oi.company_id = @company_id
              AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
              AND o.is_cancelled = FALSE
            GROUP BY o.customer_id
            HAVING order_count > 1
        )
        SQL;

        $reorderResults = $this->queryCached("product_reorder_v2:{$this->companyId}:{$sku}:{$monthsBack}", $reorderSql, [
            'sku' => $sku,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);
        $repeatCustomers = $this->toInt($reorderResults[0]['repeat_customers'] ?? 0);
        $reorderRate = $uniqueCustomers > 0 ? round(($repeatCustomers / $uniqueCustomers) * 100, 1) : 0;

        // Calculate average customer span (days between first and last purchase)
        $spanSql = <<<SQL
        SELECT
            AVG(DATE_DIFF(last_purchase, first_purchase, DAY)) as avg_span
        FROM (
            SELECT
                o.customer_id,
                MIN(DATE(o.order_datetime)) as first_purchase,
                MAX(DATE(o.order_datetime)) as last_purchase
            FROM `{$this->dataset}.fact_order_item` oi
            JOIN `{$this->dataset}.fact_order` o ON oi.order_id = o.order_id AND oi.company_id = o.company_id
            WHERE oi.sku = @sku
              AND oi.company_id = @company_id
              AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
              AND o.is_cancelled = FALSE
            GROUP BY o.customer_id
        )
        SQL;

        $spanResults = $this->queryCached("product_span_v2:{$this->companyId}:{$sku}:{$monthsBack}", $spanSql, [
            'sku' => $sku,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);
        $avgSpanDays = $this->toInt($spanResults[0]['avg_span'] ?? 0);

        // Get monthly sales trend with orders count
        $trendSql = <<<SQL
        SELECT
            FORMAT_DATE('%Y-%m', oi.order_date) as month,
            SUM(oi.revenue_realised_subtotal_excl) as revenue,
            COUNT(DISTINCT oi.order_id) as orders,
            SUM(oi.qty_ordered) as units
        FROM `{$this->dataset}.fact_order_item` oi
        JOIN `{$this->dataset}.fact_order` o ON oi.order_id = o.order_id AND oi.company_id = o.company_id
        WHERE oi.sku = @sku
          AND oi.company_id = @company_id
          AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
          AND o.is_cancelled = FALSE
        GROUP BY month
        ORDER BY month
        SQL;

        $trendResults = $this->queryCached("product_trend_v2:{$this->companyId}:{$sku}:{$monthsBack}", $trendSql, [
            'sku' => $sku,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);

        // Get brand averages for comparison
        $brandAvgSql = <<<SQL
        SELECT
            AVG(product_revenue) as avg_revenue,
            AVG(product_orders) as avg_orders,
            AVG(product_units) as avg_units
        FROM (
            SELECT
                oi.sku,
                SUM(oi.revenue_realised_subtotal_excl) as product_revenue,
                COUNT(DISTINCT o.order_id) as product_orders,
                SUM(oi.qty_ordered) as product_units
            FROM `{$this->dataset}.fact_order_item` oi
            JOIN `{$this->dataset}.fact_order` o ON oi.order_id = o.order_id AND oi.company_id = o.company_id
            JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
            WHERE p.brand = @brand
              AND p.company_id = @company_id
              AND oi.order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
              AND o.is_cancelled = FALSE
            GROUP BY oi.sku
        )
        SQL;

        $brandAvgResults = $this->queryCached("brand_avg_v2:{$this->companyId}:{$brand}:{$monthsBack}", $brandAvgSql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);

        $brandAvg = $brandAvgResults[0] ?? [];
        $brandAvgRevenue = $this->toFloat($brandAvg['avg_revenue'] ?? 0);
        $brandAvgOrders = $this->toFloat($brandAvg['avg_orders'] ?? 0);
        $brandAvgUnits = $this->toFloat($brandAvg['avg_units'] ?? 0);

        $productRevenue = $this->toFloat($metrics['total_revenue'] ?? 0);
        $productOrders = $this->toInt($metrics['total_orders'] ?? 0);
        $productUnits = $this->toInt($metrics['total_units'] ?? 0);

        return [
            'product_info' => [
                'sku' => $product['sku'],
                'name' => $product['name'],
                'brand' => $product['brand'],
                'category' => $product['category'],
                'subcategory' => $product['subcategory'],
            ],
            'performance' => [
                'total_revenue' => $productRevenue,
                'total_orders' => $productOrders,
                'total_units' => $productUnits,
                'avg_order_value' => round($this->toFloat($metrics['avg_order_value'] ?? 0), 2),
                'avg_price' => round($this->toFloat($metrics['avg_price'] ?? 0), 2),
            ],
            'customer' => [
                'unique_customers' => $uniqueCustomers,
                'avg_qty_per_customer' => $uniqueCustomers > 0
                    ? round($productUnits / $uniqueCustomers, 1)
                    : 0,
                'reorder_rate' => $reorderRate,
                'avg_customer_span_days' => $avgSpanDays,
            ],
            'price' => [
                'min_price' => round($this->toFloat($metrics['min_price'] ?? 0), 2),
                'max_price' => round($this->toFloat($metrics['max_price'] ?? 0), 2),
                'avg_price' => round($this->toFloat($metrics['avg_price'] ?? 0), 2),
                'promo_rate' => $totalOrders > 0 ? round(($promoOrders / $totalOrders) * 100, 1) : 0,
                'avg_discount' => round($this->toFloat($metrics['avg_discount'] ?? 0), 2),
            ],
            'trend' => array_map(fn ($row) => [
                'month' => $row['month'],
                'revenue' => $this->toFloat($row['revenue']),
                'orders' => $this->toInt($row['orders']),
                'units' => $this->toInt($row['units']),
            ], $trendResults),
            'comparison' => [
                'revenue_vs_avg' => $brandAvgRevenue > 0
                    ? round((($productRevenue - $brandAvgRevenue) / $brandAvgRevenue) * 100, 1)
                    : 0,
                'orders_vs_avg' => $brandAvgOrders > 0
                    ? round((($productOrders - $brandAvgOrders) / $brandAvgOrders) * 100, 1)
                    : 0,
                'units_vs_avg' => $brandAvgUnits > 0
                    ? round((($productUnits - $brandAvgUnits) / $brandAvgUnits) * 100, 1)
                    : 0,
                'brand_avg_revenue' => $brandAvgRevenue,
                'brand_avg_orders' => round($brandAvgOrders, 0),
                'brand_avg_units' => round($brandAvgUnits, 0),
            ],
        ];
    }

    /**
     * Get marketing analytics.
     */
    public function getMarketingAnalytics(string $brand, int $monthsBack = 12): array
    {
        // Summary stats - promo vs regular orders
        $summarySql = <<<SQL
        SELECT
            COUNT(DISTINCT o.order_id) as total_orders,
            SUM(oi.revenue_realised_subtotal_excl) as total_revenue,
            COUNT(DISTINCT CASE WHEN o.coupon_code IS NOT NULL AND TRIM(o.coupon_code) != '' THEN o.order_id END) as promo_orders,
            SUM(CASE WHEN o.coupon_code IS NOT NULL AND TRIM(o.coupon_code) != '' THEN oi.revenue_realised_subtotal_excl ELSE 0 END) as promo_revenue,
            SUM(COALESCE(o.discount_excl, 0)) as total_discount,
            COUNT(DISTINCT o.customer_id) as total_customers,
            AVG(CASE WHEN o.coupon_code IS NOT NULL AND TRIM(o.coupon_code) != '' THEN o.discount_excl ELSE NULL END) as avg_promo_discount,
            AVG(CASE WHEN o.coupon_code IS NOT NULL AND TRIM(o.coupon_code) != ''
                THEN SAFE_DIVIDE(o.discount_excl, NULLIF(o.revenue_realised_excl + o.discount_excl, 0)) * 100
                ELSE NULL END) as avg_discount_pct
        FROM `{$this->dataset}.fact_order` o
        JOIN `{$this->dataset}.fact_order_item` oi ON o.order_id = oi.order_id AND o.company_id = oi.company_id
        JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
        WHERE p.brand = @brand
          AND p.company_id = @company_id
          AND DATE(o.order_datetime) >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
          AND o.is_cancelled = FALSE
        SQL;

        $summaryResults = $this->queryCached("marketing_summary_v2:{$this->companyId}:{$brand}:{$monthsBack}", $summarySql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);

        $summaryRow = $summaryResults[0] ?? [];
        $totalOrders = $this->toInt($summaryRow['total_orders'] ?? 0);
        $totalRevenue = $this->toFloat($summaryRow['total_revenue'] ?? 0);
        $promoOrders = $this->toInt($summaryRow['promo_orders'] ?? 0);
        $promoRevenue = $this->toFloat($summaryRow['promo_revenue'] ?? 0);
        $totalDiscount = $this->toFloat($summaryRow['total_discount'] ?? 0);

        $summary = [
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'promo_orders' => $promoOrders,
            'promo_revenue' => $promoRevenue,
            'regular_orders' => $totalOrders - $promoOrders,
            'regular_revenue' => $totalRevenue - $promoRevenue,
            'promo_order_pct' => $totalOrders > 0 ? round(($promoOrders / $totalOrders) * 100, 1) : 0,
            'promo_revenue_pct' => $totalRevenue > 0 ? round(($promoRevenue / $totalRevenue) * 100, 1) : 0,
            'total_discount' => $totalDiscount,
            'total_discount_given' => $totalDiscount,
            'avg_discount_amount' => $promoOrders > 0 ? round($this->toFloat($summaryRow['avg_promo_discount'] ?? 0), 2) : 0,
            'avg_discount_pct' => round($this->toFloat($summaryRow['avg_discount_pct'] ?? 0), 1),
            'total_customers' => $this->toInt($summaryRow['total_customers'] ?? 0),
        ];

        // Discount tier breakdown - for "campaigns" variable used in Discount Tier Performance table
        $discountTierSql = <<<SQL
        SELECT
            CASE
                WHEN SAFE_DIVIDE(o.discount_excl, NULLIF(o.revenue_realised_excl + o.discount_excl, 0)) * 100 = 0 THEN '0-10%'
                WHEN SAFE_DIVIDE(o.discount_excl, NULLIF(o.revenue_realised_excl + o.discount_excl, 0)) * 100 <= 10 THEN '0-10%'
                WHEN SAFE_DIVIDE(o.discount_excl, NULLIF(o.revenue_realised_excl + o.discount_excl, 0)) * 100 <= 20 THEN '10-20%'
                WHEN SAFE_DIVIDE(o.discount_excl, NULLIF(o.revenue_realised_excl + o.discount_excl, 0)) * 100 <= 30 THEN '20-30%'
                WHEN SAFE_DIVIDE(o.discount_excl, NULLIF(o.revenue_realised_excl + o.discount_excl, 0)) * 100 <= 50 THEN '30-50%'
                ELSE '50%+'
            END as discount_tier,
            COUNT(DISTINCT o.order_id) as orders,
            SUM(oi.revenue_realised_subtotal_excl) as revenue,
            SUM(oi.qty_ordered) as units,
            SUM(o.discount_excl) as discount_given,
            AVG(SAFE_DIVIDE(o.discount_excl, NULLIF(o.revenue_realised_excl + o.discount_excl, 0)) * 100) as effective_discount_pct
        FROM `{$this->dataset}.fact_order` o
        JOIN `{$this->dataset}.fact_order_item` oi ON o.order_id = oi.order_id AND o.company_id = oi.company_id
        JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
        WHERE p.brand = @brand
          AND p.company_id = @company_id
          AND DATE(o.order_datetime) >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
          AND o.is_cancelled = FALSE
        GROUP BY discount_tier
        ORDER BY
            CASE discount_tier
                WHEN '0-10%' THEN 1
                WHEN '10-20%' THEN 2
                WHEN '20-30%' THEN 3
                WHEN '30-50%' THEN 4
                WHEN '50%+' THEN 5
            END
        SQL;

        $discountTierResults = $this->queryCached("marketing_discount_tiers_v2:{$this->companyId}:{$brand}:{$monthsBack}", $discountTierSql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);

        $campaigns = array_map(fn ($row) => [
            'discount_tier' => $row['discount_tier'],
            'orders' => $this->toInt($row['orders']),
            'revenue' => $this->toFloat($row['revenue']),
            'units' => $this->toInt($row['units']),
            'discount_given' => $this->toFloat($row['discount_given']),
            'effective_discount_pct' => round($this->toFloat($row['effective_discount_pct']), 1),
        ], $discountTierResults);

        // Promo vs Regular comparison for discount_analysis section
        $comparisonSql = <<<SQL
        SELECT
            CASE WHEN o.coupon_code IS NOT NULL AND TRIM(o.coupon_code) != '' THEN 'promo' ELSE 'regular' END as order_type,
            AVG(oi.revenue_realised_subtotal_excl) as avg_order_value,
            AVG(oi.qty_ordered) as avg_units_per_order,
            COUNT(DISTINCT o.customer_id) as unique_customers
        FROM `{$this->dataset}.fact_order` o
        JOIN `{$this->dataset}.fact_order_item` oi ON o.order_id = oi.order_id AND o.company_id = oi.company_id
        JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
        WHERE p.brand = @brand
          AND p.company_id = @company_id
          AND DATE(o.order_datetime) >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
          AND o.is_cancelled = FALSE
        GROUP BY order_type
        SQL;

        $comparisonResults = $this->queryCached("marketing_comparison_v2:{$this->companyId}:{$brand}:{$monthsBack}", $comparisonSql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);

        $discountAnalysis = [
            'promo' => [
                'avg_order_value' => 0,
                'avg_units_per_order' => 0,
                'unique_customers' => 0,
            ],
            'regular' => [
                'avg_order_value' => 0,
                'avg_units_per_order' => 0,
                'unique_customers' => 0,
            ],
        ];

        foreach ($comparisonResults as $row) {
            $type = $row['order_type'];
            if (isset($discountAnalysis[$type])) {
                $discountAnalysis[$type] = [
                    'avg_order_value' => round($this->toFloat($row['avg_order_value']), 2),
                    'avg_units_per_order' => round($this->toFloat($row['avg_units_per_order']), 1),
                    'unique_customers' => $this->toInt($row['unique_customers']),
                ];
            }
        }

        // Monthly trend - promo vs regular
        $trendSql = <<<SQL
        SELECT
            FORMAT_DATE('%Y-%m', DATE(o.order_datetime)) as month,
            COUNT(DISTINCT CASE WHEN o.coupon_code IS NOT NULL AND TRIM(o.coupon_code) != '' THEN o.order_id END) as promo_orders,
            COUNT(DISTINCT CASE WHEN o.coupon_code IS NULL OR TRIM(o.coupon_code) = '' THEN o.order_id END) as regular_orders,
            SUM(CASE WHEN o.coupon_code IS NOT NULL AND TRIM(o.coupon_code) != '' THEN oi.revenue_realised_subtotal_excl ELSE 0 END) as promo_revenue,
            SUM(CASE WHEN o.coupon_code IS NULL OR TRIM(o.coupon_code) = '' THEN oi.revenue_realised_subtotal_excl ELSE 0 END) as regular_revenue
        FROM `{$this->dataset}.fact_order` o
        JOIN `{$this->dataset}.fact_order_item` oi ON o.order_id = oi.order_id AND o.company_id = oi.company_id
        JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
        WHERE p.brand = @brand
          AND p.company_id = @company_id
          AND DATE(o.order_datetime) >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
          AND o.is_cancelled = FALSE
        GROUP BY month
        ORDER BY month
        SQL;

        $trendResults = $this->queryCached("marketing_trend:{$this->companyId}:{$brand}:{$monthsBack}", $trendSql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);

        $monthlyTrend = array_map(fn ($row) => [
            'month' => $row['month'],
            'promo_orders' => $this->toInt($row['promo_orders']),
            'regular_orders' => $this->toInt($row['regular_orders']),
            'promo_revenue' => $this->toFloat($row['promo_revenue']),
            'regular_revenue' => $this->toFloat($row['regular_revenue']),
        ], $trendResults);

        return [
            'summary' => $summary,
            'campaigns' => $campaigns,
            'discount_analysis' => $discountAnalysis,
            'monthly_trend' => $monthlyTrend,
        ];
    }

    /**
     * Get promo campaigns list with statistics.
     *
     * @return array<int, array{coupon_code: string, description: string, orders: int, revenue: float, units: int, discount_given: float, avg_discount_pct: float, first_used: string, last_used: string}>
     */
    public function getPromoCampaigns(string $brand, int $monthsBack = 12, int $limit = 20): array
    {
        $sql = <<<SQL
        SELECT
            UPPER(TRIM(o.coupon_code)) as coupon_code,
            MAX(o.discount_description) as description,
            COUNT(DISTINCT o.order_id) as orders,
            SUM(oi.revenue_realised_subtotal_excl) as revenue,
            SUM(oi.qty_ordered) as units,
            SUM(o.discount_excl) as discount_given,
            AVG(SAFE_DIVIDE(o.discount_excl, NULLIF(o.revenue_realised_excl + o.discount_excl, 0)) * 100) as avg_discount_pct,
            MIN(DATE(o.order_datetime)) as first_used,
            MAX(DATE(o.order_datetime)) as last_used
        FROM `{$this->dataset}.fact_order` o
        JOIN `{$this->dataset}.fact_order_item` oi ON o.order_id = oi.order_id AND o.company_id = oi.company_id
        JOIN `{$this->dataset}.dim_product` p ON oi.sku = p.sku AND oi.company_id = p.company_id
        WHERE p.brand = @brand
          AND p.company_id = @company_id
          AND DATE(o.order_datetime) >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
          AND o.is_cancelled = FALSE
          AND o.coupon_code IS NOT NULL
          AND TRIM(o.coupon_code) != ''
        GROUP BY UPPER(TRIM(o.coupon_code))
        HAVING orders >= 5
        ORDER BY revenue DESC
        LIMIT @limit
        SQL;

        $results = $this->queryCached("promo_campaigns:{$this->companyId}:{$brand}:{$monthsBack}:{$limit}", $sql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
            'limit' => $limit,
        ]);

        return array_map(fn ($row) => [
            'coupon_code' => $row['coupon_code'] ?? '',
            'description' => $row['description'] ?? $row['coupon_code'] ?? '',
            'orders' => $this->toInt($row['orders']),
            'revenue' => $this->toFloat($row['revenue']),
            'units' => $this->toInt($row['units']),
            'discount_given' => $this->toFloat($row['discount_given']),
            'avg_discount_pct' => $this->toFloat($row['avg_discount_pct']),
            'first_used' => $row['first_used'] ?? '',
            'last_used' => $row['last_used'] ?? '',
        ], $results);
    }

    /**
     * Get personalised offers statistics for a brand.
     * Data from ftn_reporting.ftn_personalised_discounts_listed table.
     *
     * @return array{summary: array<string, mixed>, weekly_trend: array<int, array<string, mixed>>, top_products: array<int, array<string, mixed>>}
     */
    public function getPersonalisedOffers(string $brand, int $monthsBack = 6): array
    {
        // Note: This query only works for FtN (company_id = 3) as the personalised discounts
        // table is FtN-specific and stored in the ftn_reporting dataset
        $pdDataset = 'ftn_reporting';
        $pdTable = 'ftn_personalised_discounts_listed';

        // Summary stats
        $summarySql = <<<SQL
        WITH parsed_offers AS (
            SELECT
                pd.customer_id,
                pd.date_from,
                pd.discount_perc,
                TRIM(REPLACE(REPLACE(sku_item, "'", ''), ' ', '')) as clean_sku
            FROM `silvertreepoc.{$pdDataset}.{$pdTable}` pd,
            UNNEST(SPLIT(pd.sku, ',')) as sku_item
            WHERE pd.date_from >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
        )
        SELECT
            COUNT(*) as total_offers,
            COUNT(DISTINCT po.customer_id) as unique_customers,
            COUNT(DISTINCT po.clean_sku) as products_featured,
            AVG(po.discount_perc) * 100 as avg_discount_pct,
            COUNT(DISTINCT po.date_from) as campaigns_count
        FROM parsed_offers po
        JOIN `{$this->dataset}.dim_product` p ON po.clean_sku = p.sku AND p.company_id = @company_id
        WHERE p.brand = @brand
        SQL;

        $summaryResults = $this->queryCached("pd_summary:{$this->companyId}:{$brand}:{$monthsBack}", $summarySql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);

        $summary = ! empty($summaryResults) ? [
            'total_offers' => $this->toInt($summaryResults[0]['total_offers']),
            'unique_customers' => $this->toInt($summaryResults[0]['unique_customers']),
            'products_featured' => $this->toInt($summaryResults[0]['products_featured']),
            'avg_discount_pct' => round($this->toFloat($summaryResults[0]['avg_discount_pct']), 1),
            'campaigns_count' => $this->toInt($summaryResults[0]['campaigns_count']),
        ] : [
            'total_offers' => 0,
            'unique_customers' => 0,
            'products_featured' => 0,
            'avg_discount_pct' => 0,
            'campaigns_count' => 0,
        ];

        // Weekly trend
        $trendSql = <<<SQL
        WITH parsed_offers AS (
            SELECT
                pd.customer_id,
                pd.date_from,
                TRIM(REPLACE(REPLACE(sku_item, "'", ''), ' ', '')) as clean_sku
            FROM `silvertreepoc.{$pdDataset}.{$pdTable}` pd,
            UNNEST(SPLIT(pd.sku, ',')) as sku_item
            WHERE pd.date_from >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
        )
        SELECT
            FORMAT_DATE('%Y-%m-%d', po.date_from) as week_start,
            COUNT(*) as offers,
            COUNT(DISTINCT po.customer_id) as customers
        FROM parsed_offers po
        JOIN `{$this->dataset}.dim_product` p ON po.clean_sku = p.sku AND p.company_id = @company_id
        WHERE p.brand = @brand
        GROUP BY po.date_from
        ORDER BY po.date_from DESC
        LIMIT 12
        SQL;

        $trendResults = $this->queryCached("pd_trend:{$this->companyId}:{$brand}:{$monthsBack}", $trendSql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);

        $weeklyTrend = array_map(fn ($row) => [
            'week_start' => $row['week_start'],
            'offers' => $this->toInt($row['offers']),
            'customers' => $this->toInt($row['customers']),
        ], array_reverse($trendResults));

        // Top products featured in personalised offers
        $topProductsSql = <<<SQL
        WITH parsed_offers AS (
            SELECT
                pd.customer_id,
                TRIM(REPLACE(REPLACE(sku_item, "'", ''), ' ', '')) as clean_sku
            FROM `silvertreepoc.{$pdDataset}.{$pdTable}` pd,
            UNNEST(SPLIT(pd.sku, ',')) as sku_item
            WHERE pd.date_from >= DATE_SUB(CURRENT_DATE(), INTERVAL @months MONTH)
        )
        SELECT
            p.sku,
            p.name,
            COUNT(*) as times_featured,
            COUNT(DISTINCT po.customer_id) as unique_customers
        FROM parsed_offers po
        JOIN `{$this->dataset}.dim_product` p ON po.clean_sku = p.sku AND p.company_id = @company_id
        WHERE p.brand = @brand
        GROUP BY p.sku, p.name
        ORDER BY times_featured DESC
        LIMIT 10
        SQL;

        $topProductsResults = $this->queryCached("pd_top_products:{$this->companyId}:{$brand}:{$monthsBack}", $topProductsSql, [
            'brand' => $brand,
            'company_id' => $this->companyId,
            'months' => $monthsBack,
        ]);

        $topProducts = array_map(fn ($row) => [
            'sku' => $row['sku'],
            'name' => $row['name'],
            'times_featured' => $this->toInt($row['times_featured']),
            'unique_customers' => $this->toInt($row['unique_customers']),
        ], $topProductsResults);

        return [
            'summary' => $summary,
            'weekly_trend' => $weeklyTrend,
            'top_products' => $topProducts,
        ];
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
