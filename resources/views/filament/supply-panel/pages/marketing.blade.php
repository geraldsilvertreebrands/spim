<x-filament-panels::page>
    {{-- Premium Feature Gate --}}
    @unless(auth()->user()->hasRole('supplier-premium') || auth()->user()->hasRole('admin'))
        <x-premium-gate feature="Marketing Analytics">
            <p class="text-gray-600 dark:text-gray-400">
                Upgrade to Premium to access comprehensive marketing analytics including promo campaign performance,
                discount effectiveness, and personalized offer statistics to optimize your marketing strategy.
            </p>
        </x-premium-gate>
    @else
        {{-- Filters Row --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                {{-- Brand Selector --}}
                @if(count($this->getAvailableBrands()) > 1)
                    <div class="w-full sm:w-64">
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

                {{-- Time Period Selector --}}
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
            @if(!$loading && !$error && $brandId)
                @include('filament.shared.components.export-buttons', [
                    'showCsv' => false,
                    'showChart' => true,
                    'chartId' => 'revenueChart',
                    'chartFilename' => 'marketing_revenue',
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
                <p class="mt-4 text-gray-600 dark:text-gray-400">Loading marketing data...</p>
            </div>
        @endif

        {{-- Marketing Content --}}
        @if(!$loading && !$error && $brandId)
            {{-- Section Navigation --}}
            <x-section-nav :sections="[
                ['id' => 'kpis', 'label' => 'KPIs'],
                ['id' => 'campaigns', 'label' => 'Promo Campaigns'],
                ['id' => 'personalised', 'label' => 'Personalised Offers'],
                ['id' => 'revenue', 'label' => 'Revenue'],
                ['id' => 'tiers', 'label' => 'Discount Tiers'],
                ['id' => 'comparison', 'label' => 'Comparison'],
                ['id' => 'volume', 'label' => 'Volume'],
                ['id' => 'insights', 'label' => 'Insights'],
                ['id' => 'boost', 'label' => 'Boost Your Brand'],
            ]" />

            {{-- Summary KPIs --}}
            <div id="section-kpis" class="mb-6 grid gap-4 grid-cols-2 md:grid-cols-4">
                {{-- Total Revenue --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total Revenue</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $this->formatCurrency($summaryStats['total_revenue'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Last {{ $monthsBack }} months
                    </div>
                </div>

                {{-- Promo Revenue % --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Promo Revenue %</div>
                    <div class="mt-2 text-3xl font-bold {{ $this->getPromoIntensityColorClass($summaryStats['promo_revenue_pct'] ?? 0) }}">
                        {{ $this->formatPercent($summaryStats['promo_revenue_pct'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $this->formatCurrency($summaryStats['promo_revenue'] ?? 0) }} promo / {{ $this->formatCurrency($summaryStats['regular_revenue'] ?? 0) }} regular
                    </div>
                </div>

                {{-- Total Discount Given --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total Discounts</div>
                    <div class="mt-2 text-3xl font-bold text-orange-600 dark:text-orange-400">
                        {{ $this->formatCurrency($summaryStats['total_discount_given'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Avg: {{ $this->formatCurrency($summaryStats['avg_discount_amount'] ?? 0) }} per order
                    </div>
                </div>

                {{-- Avg Discount % --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Avg Discount %</div>
                    <div class="mt-2 text-3xl font-bold text-blue-600 dark:text-blue-400">
                        {{ $this->formatPercent($summaryStats['avg_discount_pct'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        On promotional orders
                    </div>
                </div>
            </div>

            {{-- Promo Campaigns Table --}}
            <x-filament::section id="section-campaigns" class="mb-6">
                <x-slot name="heading">
                    Promotional Campaigns
                </x-slot>
                <x-slot name="description">
                    Coupon codes and promotional campaigns used with your brand's products
                </x-slot>

                @if($this->hasPromoCampaigns())
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Coupon Code</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Description</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Orders</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Revenue</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Units</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Discount Given</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Avg %</th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">Date Range</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($promoCampaigns as $campaign)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3">
                                            <span class="font-mono text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-gray-800 dark:text-gray-200">
                                                {{ $campaign['coupon_code'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300 max-w-48 truncate" title="{{ $campaign['description'] }}">
                                            {{ Str::limit($campaign['description'], 30) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                            {{ $this->formatNumber($campaign['orders']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                            {{ $this->formatCurrency($campaign['revenue']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                            {{ $this->formatNumber($campaign['units']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-orange-600 dark:text-orange-400">
                                            {{ $this->formatCurrency($campaign['discount_given']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                            {{ $this->formatPercent($campaign['avg_discount_pct']) }}
                                        </td>
                                        <td class="px-4 py-3 text-center text-xs text-gray-500 dark:text-gray-400">
                                            {{ $this->formatDate($campaign['first_used']) }} - {{ $this->formatDate($campaign['last_used']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        Showing top {{ count($promoCampaigns) }} campaigns by revenue. Campaigns with fewer than 5 orders are excluded.
                    </p>
                @else
                    <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-ticket class="mx-auto h-8 w-8 mb-2 text-gray-400" />
                        <p>No promotional campaigns found for this brand in the selected period</p>
                    </div>
                @endif
            </x-filament::section>

            {{-- Personalised Offers Section --}}
            <x-filament::section id="section-personalised" class="mb-6">
                <x-slot name="heading">
                    <span class="flex items-center gap-2">
                        <x-heroicon-o-gift class="w-5 h-5 text-purple-500" />
                        Personalised Offers
                    </span>
                </x-slot>
                <x-slot name="description">
                    Statistics on how often your products are featured in personalised discounts sent to customers
                </x-slot>

                @if($this->hasPersonalisedOffers())
                    @php
                        $pdSummary = $this->getPersonalisedOffersSummary();
                        $pdTrend = $this->getPersonalisedOffersWeeklyTrend();
                        $pdTopProducts = $this->getPersonalisedOffersTopProducts();
                    @endphp

                    {{-- Summary Stats --}}
                    <div class="mb-6 grid gap-4 grid-cols-2 md:grid-cols-5">
                        <div class="rounded-lg bg-purple-50 p-4 dark:bg-purple-900/20">
                            <div class="text-xs font-medium uppercase tracking-wide text-purple-600 dark:text-purple-400">Total Offers</div>
                            <div class="mt-1 text-2xl font-bold text-purple-700 dark:text-purple-300">
                                {{ number_format($pdSummary['total_offers'] ?? 0) }}
                            </div>
                        </div>
                        <div class="rounded-lg bg-purple-50 p-4 dark:bg-purple-900/20">
                            <div class="text-xs font-medium uppercase tracking-wide text-purple-600 dark:text-purple-400">Unique Customers</div>
                            <div class="mt-1 text-2xl font-bold text-purple-700 dark:text-purple-300">
                                {{ number_format($pdSummary['unique_customers'] ?? 0) }}
                            </div>
                        </div>
                        <div class="rounded-lg bg-purple-50 p-4 dark:bg-purple-900/20">
                            <div class="text-xs font-medium uppercase tracking-wide text-purple-600 dark:text-purple-400">Products Featured</div>
                            <div class="mt-1 text-2xl font-bold text-purple-700 dark:text-purple-300">
                                {{ number_format($pdSummary['products_featured'] ?? 0) }}
                            </div>
                        </div>
                        <div class="rounded-lg bg-purple-50 p-4 dark:bg-purple-900/20">
                            <div class="text-xs font-medium uppercase tracking-wide text-purple-600 dark:text-purple-400">Campaigns</div>
                            <div class="mt-1 text-2xl font-bold text-purple-700 dark:text-purple-300">
                                {{ number_format($pdSummary['campaigns_count'] ?? 0) }}
                            </div>
                        </div>
                        <div class="rounded-lg bg-purple-50 p-4 dark:bg-purple-900/20">
                            <div class="text-xs font-medium uppercase tracking-wide text-purple-600 dark:text-purple-400">Avg Discount</div>
                            <div class="mt-1 text-2xl font-bold text-purple-700 dark:text-purple-300">
                                {{ number_format($pdSummary['avg_discount_pct'] ?? 0, 1) }}%
                            </div>
                        </div>
                    </div>

                    {{-- Weekly Trend --}}
                    @if(count($pdTrend) > 0)
                        <div class="mb-6">
                            <h4 class="mb-3 font-semibold text-gray-900 dark:text-white">Weekly Offers Trend</h4>
                            <div class="overflow-x-auto">
                                <div class="flex gap-2 min-w-max pb-2">
                                    @foreach($pdTrend as $week)
                                        <div class="flex-shrink-0 w-24 rounded-lg bg-gray-50 p-3 dark:bg-gray-800 text-center">
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($week['week_start'])->format('d M') }}</div>
                                            <div class="mt-1 text-lg font-bold text-purple-600 dark:text-purple-400">{{ number_format($week['offers']) }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($week['customers']) }} customers</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Top Products --}}
                    @if(count($pdTopProducts) > 0)
                        <div>
                            <h4 class="mb-3 font-semibold text-gray-900 dark:text-white">Top Products in Personalised Offers</h4>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-gray-200 dark:border-gray-700">
                                            <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-300">SKU</th>
                                            <th class="px-4 py-2 text-left font-semibold text-gray-600 dark:text-gray-300">Product Name</th>
                                            <th class="px-4 py-2 text-right font-semibold text-gray-600 dark:text-gray-300">Times Featured</th>
                                            <th class="px-4 py-2 text-right font-semibold text-gray-600 dark:text-gray-300">Unique Customers</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($pdTopProducts as $product)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                                <td class="px-4 py-2">
                                                    <span class="font-mono text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-gray-800 dark:text-gray-200">
                                                        {{ $product['sku'] }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                                    {{ Str::limit($product['name'], 40) }}
                                                </td>
                                                <td class="px-4 py-2 text-right font-medium text-purple-600 dark:text-purple-400">
                                                    {{ number_format($product['times_featured']) }}
                                                </td>
                                                <td class="px-4 py-2 text-right text-gray-700 dark:text-gray-300">
                                                    {{ number_format($product['unique_customers']) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        Personalised offers data is based on automated email campaigns that feature products tailored to each customer's preferences and purchase history.
                    </p>
                @else
                    <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-gift class="mx-auto h-8 w-8 mb-2 text-gray-400" />
                        <p>No personalised offers data available for this brand</p>
                        <p class="text-xs mt-1">This feature is currently available for Faithful to Nature brands only</p>
                    </div>
                @endif
            </x-filament::section>

            {{-- Revenue Trend Chart --}}
            <x-filament::section id="section-revenue" class="mb-6">
                <x-slot name="heading">
                    Promo vs Regular Revenue
                </x-slot>
                <x-slot name="description">
                    Monthly breakdown of promotional and regular price revenue
                </x-slot>

                @if(count($monthlyTrend) > 0)
                    <div class="h-80">
                        <canvas id="revenueChart" wire:ignore></canvas>
                    </div>

                    <script>
                        document.addEventListener('livewire:navigated', function() {
                            initMarketingCharts();
                        });

                        document.addEventListener('DOMContentLoaded', function() {
                            initMarketingCharts();
                        });

                        function initMarketingCharts() {
                            const revenueCtx = document.getElementById('revenueChart');
                            if (!revenueCtx) return;

                            // Destroy existing chart if it exists
                            if (window.marketingRevenueChart) {
                                window.marketingRevenueChart.destroy();
                            }

                            const chartData = @json($chartData);
                            const labels = chartData.labels;
                            const revenueDatasets = chartData.revenue.datasets;

                            window.marketingRevenueChart = new Chart(revenueCtx, {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: revenueDatasets
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: true,
                                            position: 'top'
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    return context.dataset.label + ': R' + context.raw.toLocaleString();
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        x: {
                                            stacked: true,
                                            title: {
                                                display: true,
                                                text: 'Month'
                                            }
                                        },
                                        y: {
                                            stacked: true,
                                            title: {
                                                display: true,
                                                text: 'Revenue (R)'
                                            },
                                            ticks: {
                                                callback: function(value) {
                                                    if (value >= 1000000) {
                                                        return 'R' + (value / 1000000).toFixed(1) + 'M';
                                                    } else if (value >= 1000) {
                                                        return 'R' + (value / 1000).toFixed(0) + 'K';
                                                    }
                                                    return 'R' + value;
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        }

                        // Re-initialize charts when Livewire updates
                        Livewire.on('chartDataUpdated', function() {
                            initMarketingCharts();
                        });
                    </script>
                @else
                    <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                        No trend data available for the selected period
                    </div>
                @endif
            </x-filament::section>

            {{-- Discount Tier Breakdown --}}
            <x-filament::section id="section-tiers" class="mb-6">
                <x-slot name="heading">
                    Discount Tier Performance
                </x-slot>
                <x-slot name="description">
                    Revenue and order breakdown by discount percentage tier
                </x-slot>

                @if($this->hasCampaignData())
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Discount Tier</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Revenue</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Orders</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Units Sold</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Discount Given</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Effective %</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($campaigns as $campaign)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-block w-3 h-3 rounded-full {{ $this->getDiscountTierColor($campaign['discount_tier']) }}"></span>
                                                <span class="font-medium text-gray-900 dark:text-white">{{ $campaign['discount_tier'] }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                            {{ $this->formatCurrency($campaign['revenue']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                            {{ $this->formatNumber($campaign['orders']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                            {{ $this->formatNumber($campaign['units']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-orange-600 dark:text-orange-400">
                                            {{ $this->formatCurrency($campaign['discount_given']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                            {{ $this->formatPercent($campaign['effective_discount_pct']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                        No promotional campaign data available
                    </div>
                @endif
            </x-filament::section>

            {{-- Promo vs Regular Comparison --}}
            <x-filament::section id="section-comparison" class="mb-6">
                <x-slot name="heading">
                    Promo vs Regular Price Comparison
                </x-slot>
                <x-slot name="description">
                    Compare customer behavior between promotional and regular price orders
                </x-slot>

                <div class="grid gap-6 md:grid-cols-2">
                    {{-- Promo Stats --}}
                    <div class="rounded-lg border border-orange-200 bg-orange-50 p-6 dark:border-orange-800 dark:bg-orange-900/20">
                        <h3 class="mb-4 text-lg font-semibold text-orange-800 dark:text-orange-300">Promotional Orders</h3>
                        <dl class="space-y-3">
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Avg Order Value</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">
                                    {{ $this->formatCurrency($discountAnalysis['promo']['avg_order_value'] ?? 0) }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Avg Units/Order</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">
                                    {{ number_format($discountAnalysis['promo']['avg_units_per_order'] ?? 0, 1) }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Unique Customers</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">
                                    {{ $this->formatNumber($discountAnalysis['promo']['unique_customers'] ?? 0) }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Regular Stats --}}
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-900/20">
                        <h3 class="mb-4 text-lg font-semibold text-blue-800 dark:text-blue-300">Regular Price Orders</h3>
                        <dl class="space-y-3">
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Avg Order Value</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">
                                    {{ $this->formatCurrency($discountAnalysis['regular']['avg_order_value'] ?? 0) }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Avg Units/Order</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">
                                    {{ number_format($discountAnalysis['regular']['avg_units_per_order'] ?? 0, 1) }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Unique Customers</dt>
                                <dd class="font-medium text-gray-900 dark:text-white">
                                    {{ $this->formatNumber($discountAnalysis['regular']['unique_customers'] ?? 0) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </x-filament::section>

            {{-- Monthly Orders Chart --}}
            <x-filament::section id="section-volume" class="mb-6">
                <x-slot name="heading">
                    Order Volume Breakdown
                </x-slot>
                <x-slot name="description">
                    Monthly comparison of promotional vs regular price orders
                </x-slot>

                @if(count($monthlyTrend) > 0)
                    <div class="h-80">
                        <canvas id="ordersChart" wire:ignore></canvas>
                    </div>

                    <script>
                        document.addEventListener('livewire:navigated', function() {
                            initOrdersChart();
                        });

                        document.addEventListener('DOMContentLoaded', function() {
                            initOrdersChart();
                        });

                        function initOrdersChart() {
                            const ordersCtx = document.getElementById('ordersChart');
                            if (!ordersCtx) return;

                            // Destroy existing chart if it exists
                            if (window.marketingOrdersChart) {
                                window.marketingOrdersChart.destroy();
                            }

                            const chartData = @json($chartData);
                            const labels = chartData.labels;
                            const ordersDatasets = chartData.orders.datasets;

                            window.marketingOrdersChart = new Chart(ordersCtx, {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: ordersDatasets
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: true,
                                            position: 'top'
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    return context.dataset.label + ': ' + context.raw.toLocaleString();
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        x: {
                                            stacked: true,
                                            title: {
                                                display: true,
                                                text: 'Month'
                                            }
                                        },
                                        y: {
                                            stacked: true,
                                            title: {
                                                display: true,
                                                text: 'Orders'
                                            }
                                        }
                                    }
                                }
                            });
                        }

                        // Re-initialize charts when Livewire updates
                        Livewire.on('chartDataUpdated', function() {
                            initOrdersChart();
                        });
                    </script>
                @else
                    <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                        No order data available for the selected period
                    </div>
                @endif
            </x-filament::section>

            {{-- Insights Section --}}
            <x-filament::section id="section-insights">
                <x-slot name="heading">
                    Marketing Insights
                </x-slot>

                <div class="prose prose-sm dark:prose-invert max-w-none">
                    <div class="grid gap-4 md:grid-cols-2">
                        {{-- Discount Efficiency --}}
                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                            <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">Discount Efficiency</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                @if(($summaryStats['promo_revenue_pct'] ?? 0) > 50)
                                    <span class="text-orange-600 dark:text-orange-400 font-medium">High promo dependency</span> -
                                    Over half of your revenue comes from promotional orders. Consider strategies to increase regular-price purchases.
                                @elseif(($summaryStats['promo_revenue_pct'] ?? 0) > 30)
                                    <span class="text-yellow-600 dark:text-yellow-400 font-medium">Moderate promo usage</span> -
                                    Promotional sales account for a significant portion of revenue. Monitor discount effectiveness carefully.
                                @else
                                    <span class="text-green-600 dark:text-green-400 font-medium">Healthy promo balance</span> -
                                    Most revenue comes from regular-priced sales, indicating strong brand value.
                                @endif
                            </p>
                        </div>

                        {{-- Order Value Impact --}}
                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                            <h4 class="mb-2 font-semibold text-gray-900 dark:text-white">Order Value Impact</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                @php
                                    $promoAov = $discountAnalysis['promo']['avg_order_value'] ?? 0;
                                    $regularAov = $discountAnalysis['regular']['avg_order_value'] ?? 0;
                                    $aovDiff = $regularAov > 0 ? (($promoAov - $regularAov) / $regularAov) * 100 : 0;
                                @endphp
                                @if($aovDiff > 10)
                                    <span class="text-green-600 dark:text-green-400 font-medium">Promotions drive larger orders</span> -
                                    Customers spend {{ number_format(abs($aovDiff), 0) }}% more per order when promotions are applied.
                                @elseif($aovDiff < -10)
                                    <span class="text-yellow-600 dark:text-yellow-400 font-medium">Lower promo order values</span> -
                                    Promotional orders have {{ number_format(abs($aovDiff), 0) }}% lower average value than regular orders.
                                @else
                                    <span class="text-blue-600 dark:text-blue-400 font-medium">Consistent order values</span> -
                                    Order values are similar regardless of promotional status.
                                @endif
                            </p>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-4">
                        Note: Marketing analytics are based on historical sales data. Use these insights to optimize your promotional strategies.
                    </p>
                </div>
            </x-filament::section>

            {{-- Rate Card / Upsell Section --}}
            <x-filament::section id="section-boost" class="mt-6">
                <x-slot name="heading">
                    <span class="flex items-center gap-2">
                        <x-heroicon-o-rocket-launch class="w-5 h-5 text-primary-500" />
                        Boost Your Brand
                    </span>
                </x-slot>
                <x-slot name="description">
                    Partner with us to amplify your brand's visibility and reach more customers
                </x-slot>

                <div class="grid gap-6 md:grid-cols-2">
                    {{-- Featured Placement --}}
                    <div class="rounded-xl border border-gray-200 bg-gradient-to-br from-white to-gray-50 p-6 dark:border-gray-700 dark:from-gray-800 dark:to-gray-900">
                        <div class="flex items-start gap-4">
                            <div class="rounded-lg bg-primary-100 p-3 dark:bg-primary-900/30">
                                <x-heroicon-o-star class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900 dark:text-white">Featured Placement</h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Get premium visibility on our homepage, category pages, and search results. Ideal for new product launches or seasonal campaigns.
                                </p>
                                <a href="mailto:sales@silvertreebrands.com?subject=Featured%20Placement%20Inquiry&body=Hi%2C%0A%0AI'm%20interested%20in%20learning%20more%20about%20featured%20placement%20opportunities%20for%20my%20brand.%0A%0ABrand%3A%20%0AContact%20Name%3A%20%0APhone%3A%20"
                                   class="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 transition-colors">
                                    <x-heroicon-o-envelope class="w-4 h-4" />
                                    Contact Us
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Newsletter Inclusion --}}
                    <div class="rounded-xl border border-gray-200 bg-gradient-to-br from-white to-gray-50 p-6 dark:border-gray-700 dark:from-gray-800 dark:to-gray-900">
                        <div class="flex items-start gap-4">
                            <div class="rounded-lg bg-blue-100 p-3 dark:bg-blue-900/30">
                                <x-heroicon-o-envelope-open class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900 dark:text-white">Newsletter Inclusion</h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Reach our engaged subscriber base directly. Feature your products in our weekly or monthly newsletters to thousands of potential customers.
                                </p>
                                <a href="mailto:sales@silvertreebrands.com?subject=Newsletter%20Inclusion%20Inquiry&body=Hi%2C%0A%0AI'm%20interested%20in%20featuring%20my%20products%20in%20your%20newsletter.%0A%0ABrand%3A%20%0AContact%20Name%3A%20%0APhone%3A%20"
                                   class="mt-4 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                                    <x-heroicon-o-envelope class="w-4 h-4" />
                                    Contact Us
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Social Media Promotion --}}
                    <div class="rounded-xl border border-gray-200 bg-gradient-to-br from-white to-gray-50 p-6 dark:border-gray-700 dark:from-gray-800 dark:to-gray-900">
                        <div class="flex items-start gap-4">
                            <div class="rounded-lg bg-purple-100 p-3 dark:bg-purple-900/30">
                                <x-heroicon-o-share class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900 dark:text-white">Social Media Promotion</h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Leverage our social media presence across Instagram, Facebook, and TikTok. Includes sponsored posts, stories, and influencer collaborations.
                                </p>
                                <a href="mailto:sales@silvertreebrands.com?subject=Social%20Media%20Promotion%20Inquiry&body=Hi%2C%0A%0AI'm%20interested%20in%20social%20media%20promotion%20opportunities.%0A%0ABrand%3A%20%0AContact%20Name%3A%20%0APhone%3A%20"
                                   class="mt-4 inline-flex items-center gap-2 rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 transition-colors">
                                    <x-heroicon-o-envelope class="w-4 h-4" />
                                    Contact Us
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Co-Branded Campaigns --}}
                    <div class="rounded-xl border border-gray-200 bg-gradient-to-br from-white to-gray-50 p-6 dark:border-gray-700 dark:from-gray-800 dark:to-gray-900">
                        <div class="flex items-start gap-4">
                            <div class="rounded-lg bg-orange-100 p-3 dark:bg-orange-900/30">
                                <x-heroicon-o-sparkles class="w-6 h-6 text-orange-600 dark:text-orange-400" />
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900 dark:text-white">Co-Branded Campaigns</h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Partner with us for exclusive co-branded marketing campaigns. Perfect for limited editions, seasonal promotions, or cause marketing initiatives.
                                </p>
                                <a href="mailto:sales@silvertreebrands.com?subject=Co-Branded%20Campaign%20Inquiry&body=Hi%2C%0A%0AI'm%20interested%20in%20exploring%20co-branded%20campaign%20opportunities.%0A%0ABrand%3A%20%0AContact%20Name%3A%20%0APhone%3A%20"
                                   class="mt-4 inline-flex items-center gap-2 rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 transition-colors">
                                    <x-heroicon-o-envelope class="w-4 h-4" />
                                    Contact Us
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 rounded-lg bg-gray-100 p-4 dark:bg-gray-800">
                    <p class="text-sm text-gray-600 dark:text-gray-400 text-center">
                        <span class="font-medium text-gray-900 dark:text-white">Have a custom idea?</span>
                        We're always open to creative partnerships.
                        <a href="mailto:sales@silvertreebrands.com?subject=Custom%20Marketing%20Partnership" class="text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300 font-medium">
                            Get in touch
                        </a>
                        to discuss your vision.
                    </p>
                </div>
            </x-filament::section>
        @endif

        {{-- No Brand Selected --}}
        @if(!$loading && !$error && !$brandId)
            <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
                <p class="text-gray-600 dark:text-gray-400">Please select a brand to view marketing data</p>
            </div>
        @endif
    @endunless
</x-filament-panels::page>
