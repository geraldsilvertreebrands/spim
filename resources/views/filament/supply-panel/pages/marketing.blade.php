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
            {{-- Summary KPIs --}}
            <div class="mb-6 grid gap-4 grid-cols-2 md:grid-cols-4">
                {{-- Total Revenue --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Revenue</div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $this->formatCurrency($summaryStats['total_revenue'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Last {{ $monthsBack }} months
                    </div>
                </div>

                {{-- Promo Revenue % --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Promo Revenue %</div>
                    <div class="mt-2 text-2xl font-bold {{ $this->getPromoIntensityColorClass($summaryStats['promo_revenue_pct'] ?? 0) }}">
                        {{ $this->formatPercent($summaryStats['promo_revenue_pct'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $this->formatCurrency($summaryStats['promo_revenue'] ?? 0) }} promo / {{ $this->formatCurrency($summaryStats['regular_revenue'] ?? 0) }} regular
                    </div>
                </div>

                {{-- Total Discount Given --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Discounts</div>
                    <div class="mt-2 text-2xl font-bold text-orange-600 dark:text-orange-400">
                        {{ $this->formatCurrency($summaryStats['total_discount_given'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Avg: {{ $this->formatCurrency($summaryStats['avg_discount_amount'] ?? 0) }} per order
                    </div>
                </div>

                {{-- Avg Discount % --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Discount %</div>
                    <div class="mt-2 text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ $this->formatPercent($summaryStats['avg_discount_pct'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        On promotional orders
                    </div>
                </div>
            </div>

            {{-- Revenue Trend Chart --}}
            <x-filament::section class="mb-6">
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
            <x-filament::section class="mb-6">
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
            <x-filament::section class="mb-6">
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
            <x-filament::section class="mb-6">
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
            <x-filament::section>
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
        @endif

        {{-- No Brand Selected --}}
        @if(!$loading && !$error && !$brandId)
            <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
                <p class="text-gray-600 dark:text-gray-400">Please select a brand to view marketing data</p>
            </div>
        @endif
    @endunless
</x-filament-panels::page>
