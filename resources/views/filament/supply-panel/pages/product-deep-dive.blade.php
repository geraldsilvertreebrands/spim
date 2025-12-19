<x-filament-panels::page>
    {{-- Premium Feature Gate --}}
    @unless(auth()->user()->hasRole('supplier-premium') || auth()->user()->hasRole('admin'))
        <x-premium-gate feature="Product Deep Dive">
            <p class="text-gray-600 dark:text-gray-400">
                Upgrade to Premium to access detailed SKU-level analytics including performance metrics,
                customer insights, price analysis, and trend visualization for individual products.
            </p>
        </x-premium-gate>
    @else
        {{-- Filters Row --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                {{-- Brand Selector --}}
                @if(count($this->getAvailableBrands()) > 1)
                    <div class="w-full sm:w-48">
                        <label for="brandSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Brand
                        </label>
                        <select
                            wire:model.live="brandId"
                            id="brandSelect"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach($this->getAvailableBrands() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Product Selector --}}
                <div class="w-full sm:w-80">
                    <label for="skuSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Select Product
                    </label>
                    <select
                        wire:model.live="sku"
                        id="skuSelect"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        <option value="">-- Select a product --</option>
                        @foreach($availableProducts as $product)
                            <option value="{{ $product['sku'] }}">{{ $product['name'] }} ({{ $product['sku'] }})</option>
                        @endforeach
                    </select>
                </div>

                {{-- Period Selector --}}
                <div class="w-full sm:w-48">
                    <label for="monthsBackSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Time Period
                    </label>
                    <select
                        wire:model.live="monthsBack"
                        id="monthsBackSelect"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        @foreach($this->getMonthsBackOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Export Buttons --}}
            @if(!$loading && !$error && $sku)
                @include('filament.shared.components.export-buttons', [
                    'showCsv' => false,
                    'showChart' => true,
                    'chartId' => 'productTrendChart',
                    'chartFilename' => 'product_deep_dive',
                    'showPrint' => true,
                ])
            @endif
        </div>

        {{-- Error Message --}}
        @if($error)
            <div class="mb-6 rounded-lg bg-red-50 p-4 text-red-800 dark:bg-red-900/20 dark:text-red-400">
                <p class="font-medium">Error</p>
                <p class="mt-1 text-sm">{{ $error }}</p>
            </div>
        @endif

        {{-- Loading State --}}
        @if($loading)
            <div class="mb-6 rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
                <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-primary-600 border-r-transparent"></div>
                <p class="mt-4 text-gray-600 dark:text-gray-400">Loading product data...</p>
            </div>
        @endif

        {{-- No Product Selected State --}}
        @if(!$loading && !$error && !$sku)
            <div class="rounded-lg bg-gray-50 p-6 text-center dark:bg-gray-800">
                <svg width="32" height="32" class="mx-auto h-8 w-8 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">Select a Product</h3>
                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                    Choose a product from the dropdown
                </p>
            </div>
        @endif

        {{-- Product Deep Dive Content --}}
        @if(!$loading && !$error && $this->hasProductData())
            {{-- Product Header --}}
            <div class="mb-6 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white shadow-lg">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-2xl font-bold">{{ $productInfo['name'] ?? $sku }}</h2>
                        <p class="mt-1 text-blue-100">
                            SKU: {{ $productInfo['sku'] ?? $sku }}
                            @if(!empty($productInfo['category']))
                                <span class="mx-2">|</span>
                                {{ $productInfo['category'] }}
                                @if(!empty($productInfo['subcategory']))
                                    &raquo; {{ $productInfo['subcategory'] }}
                                @endif
                            @endif
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center rounded-full bg-white/20 px-3 py-1 text-sm font-medium">
                            {{ $monthsBack }} month analysis
                        </span>
                    </div>
                </div>
            </div>

            {{-- Performance KPIs --}}
            <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                {{-- Total Revenue --}}
                <div class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Revenue</div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $this->formatCurrency($performanceMetrics['total_revenue'] ?? 0) }}
                    </div>
                    @if(!empty($comparisonData))
                        <div class="mt-1 flex items-center gap-1 text-xs {{ $this->getComparisonColorClass($comparisonData['revenue_vs_avg'] ?? 0) }}">
                            <span>{{ $this->getComparisonIcon($comparisonData['revenue_vs_avg'] ?? 0) }}</span>
                            <span>{{ abs($comparisonData['revenue_vs_avg'] ?? 0) }}% vs brand avg</span>
                        </div>
                    @endif
                </div>

                {{-- Total Orders --}}
                <div class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Orders</div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $this->formatNumber($performanceMetrics['total_orders'] ?? 0) }}
                    </div>
                    @if(!empty($comparisonData))
                        <div class="mt-1 flex items-center gap-1 text-xs {{ $this->getComparisonColorClass($comparisonData['orders_vs_avg'] ?? 0) }}">
                            <span>{{ $this->getComparisonIcon($comparisonData['orders_vs_avg'] ?? 0) }}</span>
                            <span>{{ abs($comparisonData['orders_vs_avg'] ?? 0) }}% vs brand avg</span>
                        </div>
                    @endif
                </div>

                {{-- Total Units --}}
                <div class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Units Sold</div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $this->formatNumber($performanceMetrics['total_units'] ?? 0) }}
                    </div>
                    @if(!empty($comparisonData))
                        <div class="mt-1 flex items-center gap-1 text-xs {{ $this->getComparisonColorClass($comparisonData['units_vs_avg'] ?? 0) }}">
                            <span>{{ $this->getComparisonIcon($comparisonData['units_vs_avg'] ?? 0) }}</span>
                            <span>{{ abs($comparisonData['units_vs_avg'] ?? 0) }}% vs brand avg</span>
                        </div>
                    @endif
                </div>

                {{-- Avg Order Value --}}
                <div class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Order Value</div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $this->formatCurrency($performanceMetrics['avg_order_value'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Per transaction
                    </div>
                </div>

                {{-- Avg Price --}}
                <div class="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Unit Price</div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $this->formatCurrency($performanceMetrics['avg_price'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Mean selling price
                    </div>
                </div>
            </div>

            {{-- Trend Chart --}}
            @if(count($trendData) > 0)
                <x-filament::section class="mb-6">
                    <x-slot name="heading">
                        Sales Trend
                    </x-slot>
                    <x-slot name="description">
                        Monthly revenue, orders, and units over the selected period
                    </x-slot>

                    <div class="h-64 md:h-72 lg:h-80">
                        <canvas id="productTrendChart"></canvas>
                    </div>
                </x-filament::section>
            @endif

            {{-- Customer & Price Metrics --}}
            <div class="mb-6 grid gap-6 md:grid-cols-2">
                {{-- Customer Metrics --}}
                <x-filament::section>
                    <x-slot name="heading">
                        Customer Insights
                    </x-slot>

                    <div class="grid gap-4 sm:grid-cols-2">
                        {{-- Unique Customers --}}
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Unique Customers</div>
                            <div class="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                                {{ $this->formatNumber($customerMetrics['unique_customers'] ?? 0) }}
                            </div>
                        </div>

                        {{-- Avg Qty per Customer --}}
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Avg Units per Customer</div>
                            <div class="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                                {{ number_format($customerMetrics['avg_qty_per_customer'] ?? 0, 1) }}
                            </div>
                        </div>

                        {{-- Reorder Rate --}}
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Reorder Rate</div>
                            <div class="mt-1 flex items-baseline gap-2">
                                <span class="text-xl font-bold {{ $this->getReorderRateColorClass($customerMetrics['reorder_rate'] ?? 0) }}">
                                    {{ $this->formatPercent($customerMetrics['reorder_rate'] ?? 0) }}
                                </span>
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Customers who bought 2+ times
                            </div>
                        </div>

                        {{-- Avg Customer Span --}}
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Avg Customer Lifetime</div>
                            <div class="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                                {{ $customerMetrics['avg_customer_span_days'] ?? 0 }} days
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                From first to last purchase
                            </div>
                        </div>
                    </div>
                </x-filament::section>

                {{-- Price Metrics --}}
                <x-filament::section>
                    <x-slot name="heading">
                        Price Analysis
                    </x-slot>

                    <div class="grid gap-4 sm:grid-cols-2">
                        {{-- Price Range --}}
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Price Range</div>
                            <div class="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                                {{ $this->formatCurrency($priceMetrics['min_price'] ?? 0) }} - {{ $this->formatCurrency($priceMetrics['max_price'] ?? 0) }}
                            </div>
                        </div>

                        {{-- Avg Price --}}
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Average Price</div>
                            <div class="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                                {{ $this->formatCurrency($priceMetrics['avg_price'] ?? 0) }}
                            </div>
                        </div>

                        {{-- Promo Rate --}}
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Promo Intensity</div>
                            <div class="mt-1 flex items-baseline gap-2">
                                <span class="text-xl font-bold {{ $this->getPromoIntensityColorClass($priceMetrics['promo_rate'] ?? 0) }}">
                                    {{ $this->formatPercent($priceMetrics['promo_rate'] ?? 0) }}
                                </span>
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                % of sales on promotion
                            </div>
                        </div>

                        {{-- Avg Discount --}}
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 p-4">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Avg Discount</div>
                            <div class="mt-1 text-xl font-bold text-gray-900 dark:text-white">
                                {{ $this->formatCurrency($priceMetrics['avg_discount'] ?? 0) }}
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                When on promotion
                            </div>
                        </div>
                    </div>
                </x-filament::section>
            </div>

            {{-- Comparison Summary --}}
            @if(!empty($comparisonData))
                <x-filament::section>
                    <x-slot name="heading">
                        Brand Comparison
                    </x-slot>
                    <x-slot name="description">
                        How this product performs compared to the brand average
                    </x-slot>

                    <div class="grid gap-4 sm:grid-cols-3">
                        {{-- Revenue vs Avg --}}
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-center">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Revenue vs Brand Avg</div>
                            <div class="mt-2 text-3xl font-bold {{ $this->getComparisonColorClass($comparisonData['revenue_vs_avg'] ?? 0) }}">
                                {{ $this->getComparisonIcon($comparisonData['revenue_vs_avg'] ?? 0) }}
                                {{ abs($comparisonData['revenue_vs_avg'] ?? 0) }}%
                            </div>
                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Brand avg: {{ $this->formatCurrency($comparisonData['brand_avg_revenue'] ?? 0) }}
                            </div>
                        </div>

                        {{-- Orders vs Avg --}}
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-center">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Orders vs Brand Avg</div>
                            <div class="mt-2 text-3xl font-bold {{ $this->getComparisonColorClass($comparisonData['orders_vs_avg'] ?? 0) }}">
                                {{ $this->getComparisonIcon($comparisonData['orders_vs_avg'] ?? 0) }}
                                {{ abs($comparisonData['orders_vs_avg'] ?? 0) }}%
                            </div>
                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Brand avg: {{ $this->formatNumber($comparisonData['brand_avg_orders'] ?? 0) }} orders
                            </div>
                        </div>

                        {{-- Units vs Avg --}}
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 text-center">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Units vs Brand Avg</div>
                            <div class="mt-2 text-3xl font-bold {{ $this->getComparisonColorClass($comparisonData['units_vs_avg'] ?? 0) }}">
                                {{ $this->getComparisonIcon($comparisonData['units_vs_avg'] ?? 0) }}
                                {{ abs($comparisonData['units_vs_avg'] ?? 0) }}%
                            </div>
                            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Brand avg: {{ $this->formatNumber($comparisonData['brand_avg_units'] ?? 0) }} units
                            </div>
                        </div>
                    </div>
                </x-filament::section>
            @endif
        @endif
    @endunless

    {{-- Chart.js Script --}}
    @if(!$loading && !$error && $this->hasProductData() && count($trendData) > 0)
        @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const ctx = document.getElementById('productTrendChart');
                if (!ctx) return;

                const chartData = @json($chartData);

                new Chart(ctx, {
                    type: 'line',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.datasetIndex === 0) {
                                            label += 'R' + context.parsed.y.toLocaleString();
                                        } else {
                                            label += context.parsed.y.toLocaleString();
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: 'Revenue (R)'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: 'Count'
                                },
                                grid: {
                                    drawOnChartArea: false,
                                }
                            }
                        }
                    }
                });
            });
        </script>
        @endpush
    @endif
</x-filament-panels::page>
