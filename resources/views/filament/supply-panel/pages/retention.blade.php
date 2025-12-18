<x-filament-panels::page>
    {{-- Premium Feature Gate --}}
    @unless(auth()->user()->hasRole('supplier-premium') || auth()->user()->hasRole('admin'))
        <x-premium-gate feature="Retention Analysis">
            <p class="text-gray-600 dark:text-gray-400">
                Upgrade to Premium to access customer retention curves, churn rate analysis,
                and trend insights to understand and improve customer loyalty.
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

                {{-- Time Period --}}
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

                {{-- Period Granularity --}}
                <div class="w-full sm:w-40">
                    <label for="periodSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Granularity
                    </label>
                    <select
                        wire:model.live="period"
                        id="periodSelect"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        @foreach($this->getPeriodOptions() as $value => $label)
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
                <p class="mt-4 text-gray-600 dark:text-gray-400">Loading retention data...</p>
            </div>
        @endif

        {{-- Retention Content --}}
        @if(!$loading && !$error && $brandId)
            {{-- Summary KPIs --}}
            <div class="mb-6 grid gap-4 grid-cols-2 md:grid-cols-4">
                {{-- Average Retention Rate --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Retention Rate</div>
                    <div class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ $summaryStats['avg_retention_rate'] ?? 0 }}%
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Customers returning each {{ $period === 'quarterly' ? 'quarter' : 'month' }}
                    </div>
                </div>

                {{-- Average Churn Rate --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Churn Rate</div>
                    <div class="mt-2 text-2xl font-bold text-red-600 dark:text-red-400">
                        {{ $summaryStats['avg_churn_rate'] ?? 0 }}%
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Customers not returning
                    </div>
                </div>

                {{-- Current Period Change --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Latest Period</div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold {{ $this->getRetentionColorClass($summaryStats['current_retention'] ?? 0) }}">
                            {{ $summaryStats['current_retention'] ?? 0 }}%
                        </span>
                        @if($summaryStats['retention_change'] != 0)
                            <span class="text-sm {{ $this->getChangeColorClass() }}">
                                {{ $this->getChangeIcon() }} {{ abs($summaryStats['retention_change']) }}%
                            </span>
                        @endif
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        vs previous {{ $period === 'quarterly' ? 'quarter' : 'month' }}
                    </div>
                </div>

                {{-- Trend --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Overall Trend</div>
                    <div class="mt-2 flex items-center gap-2">
                        <span class="text-2xl font-bold {{ $this->getTrendColorClass() }}">
                            {{ $this->getTrendIcon() }}
                            {{ ucfirst($summaryStats['trend'] ?? 'stable') }}
                        </span>
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Based on period comparison
                    </div>
                </div>
            </div>

            {{-- Best/Worst Periods --}}
            @if($summaryStats['best_period'] || $summaryStats['worst_period'])
                <div class="mb-6 grid gap-4 sm:grid-cols-2">
                    @if($summaryStats['best_period'])
                        <div class="rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                            <div class="flex items-center gap-2">
                                <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="font-medium text-green-800 dark:text-green-200">Best Retention Period</span>
                            </div>
                            <p class="mt-1 text-sm text-green-700 dark:text-green-300">
                                {{ $summaryStats['best_period'] }} had the highest customer retention
                            </p>
                        </div>
                    @endif

                    @if($summaryStats['worst_period'])
                        <div class="rounded-lg bg-amber-50 p-4 dark:bg-amber-900/20">
                            <div class="flex items-center gap-2">
                                <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span class="font-medium text-amber-800 dark:text-amber-200">Lowest Retention Period</span>
                            </div>
                            <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                                {{ $summaryStats['worst_period'] }} had the lowest customer retention
                            </p>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Retention Chart --}}
            <x-filament::section class="mb-6">
                <x-slot name="heading">
                    Retention & Churn Trends
                </x-slot>
                <x-slot name="description">
                    Customer retention and churn rates over time
                </x-slot>

                @if(count($retentionData) >= 2)
                    <div class="h-80">
                        <canvas id="retentionChart" wire:ignore></canvas>
                    </div>

                    <script>
                        document.addEventListener('livewire:navigated', function() {
                            initRetentionChart();
                        });

                        document.addEventListener('DOMContentLoaded', function() {
                            initRetentionChart();
                        });

                        function initRetentionChart() {
                            const ctx = document.getElementById('retentionChart');
                            if (!ctx) return;

                            // Destroy existing chart if it exists
                            if (window.retentionChartInstance) {
                                window.retentionChartInstance.destroy();
                            }

                            const chartData = @json($chartData);

                            window.retentionChartInstance = new Chart(ctx, {
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
                                            display: true,
                                            position: 'top',
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    return context.dataset.label + ': ' + context.raw + '%';
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        x: {
                                            display: true,
                                            title: {
                                                display: true,
                                                text: 'Period'
                                            }
                                        },
                                        y: {
                                            display: true,
                                            title: {
                                                display: true,
                                                text: 'Rate (%)'
                                            },
                                            min: 0,
                                            max: 100,
                                            ticks: {
                                                callback: function(value) {
                                                    return value + '%';
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        }

                        // Re-initialize chart when Livewire updates
                        Livewire.on('chartDataUpdated', function() {
                            initRetentionChart();
                        });
                    </script>
                @else
                    <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                        <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <p class="mt-2 text-sm">Insufficient data for retention chart</p>
                        <p class="text-xs">At least 2 periods of data required</p>
                    </div>
                @endif
            </x-filament::section>

            {{-- Retention Data Table --}}
            <x-filament::section class="mb-6">
                <x-slot name="heading">
                    Period Breakdown
                </x-slot>
                <x-slot name="description">
                    Detailed retention and churn metrics by {{ $period === 'quarterly' ? 'quarter' : 'month' }}
                </x-slot>

                @if(count($retentionData) > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Period</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Retained</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Churned</th>
                                    <th class="px-4 py-3 text-right font-semibold text-green-600 dark:text-green-400">Retention Rate</th>
                                    <th class="px-4 py-3 text-right font-semibold text-red-600 dark:text-red-400">Churn Rate</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($retentionData as $row)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                            {{ $row['month'] }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">
                                            {{ $this->formatNumber($row['retained']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">
                                            {{ $this->formatNumber($row['churned']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium {{ $this->getRetentionColorClass($row['retention_rate']) }}">
                                            {{ $row['retention_rate'] }}%
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium {{ $this->getChurnColorClass($row['churn_rate']) }}">
                                            {{ $row['churn_rate'] }}%
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-gray-300 dark:border-gray-600">
                                <tr class="font-semibold">
                                    <td class="px-4 py-3 text-gray-900 dark:text-white">Average</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">-</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">-</td>
                                    <td class="px-4 py-3 text-right text-green-600 dark:text-green-400">
                                        {{ $summaryStats['avg_retention_rate'] ?? 0 }}%
                                    </td>
                                    <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">
                                        {{ $summaryStats['avg_churn_rate'] ?? 0 }}%
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                        No retention data available
                    </div>
                @endif
            </x-filament::section>

            {{-- Understanding Retention --}}
            <x-filament::section>
                <x-slot name="heading">
                    Understanding Retention Metrics
                </x-slot>

                <div class="prose prose-sm dark:prose-invert max-w-none">
                    <p class="text-gray-600 dark:text-gray-400">
                        Retention analysis helps you understand customer loyalty and identify trends in customer behavior.
                    </p>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 p-4">
                            <h4 class="font-semibold text-green-700 dark:text-green-300">Retention Rate</h4>
                            <p class="text-sm text-green-600 dark:text-green-400 mt-2">
                                The percentage of customers from the previous period who made a purchase in the current period.
                                Higher is better - indicates strong customer loyalty.
                            </p>
                        </div>

                        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 p-4">
                            <h4 class="font-semibold text-red-700 dark:text-red-300">Churn Rate</h4>
                            <p class="text-sm text-red-600 dark:text-red-400 mt-2">
                                The percentage of customers from the previous period who did NOT make a purchase in the current period.
                                Lower is better - indicates fewer lost customers.
                            </p>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-4">
                        Note: Retention is calculated by comparing active customers between consecutive periods. A "retained" customer is one who purchased in both the previous and current period.
                    </p>
                </div>
            </x-filament::section>
        @endif

        {{-- No Brand Selected --}}
        @if(!$loading && !$error && !$brandId)
            <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
                <p class="text-gray-600 dark:text-gray-400">Please select a brand to view retention analysis</p>
            </div>
        @endif
    @endunless
</x-filament-panels::page>
