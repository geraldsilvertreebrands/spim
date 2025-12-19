<x-filament-panels::page>
    {{-- Premium + Pet Heaven Feature Gate --}}
    @unless((auth()->user()->hasRole('supplier-premium') || auth()->user()->hasRole('admin')) && count($this->getPetHeavenBrands()) > 0)
        <x-premium-gate feature="Subscription Analytics">
            <p class="text-gray-600 dark:text-gray-400">
                Subscription analytics is a Pet Heaven Premium feature. This page provides insights into
                active subscriptions, churn rates, lifetime value, and subscription trends.
            </p>
        </x-premium-gate>
    @else
        {{-- Filters Row --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                {{-- Brand Selector --}}
                @if(count($this->getPetHeavenBrands()) > 1)
                    <div class="w-full sm:w-64">
                        <label for="brandSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Pet Heaven Brand
                        </label>
                        <select
                            wire:model.live="brandId"
                            id="brandSelect"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach($this->getPetHeavenBrands() as $id => $name)
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
                    'chartId' => 'subscriptionTrendChart',
                    'chartFilename' => 'subscription_trends',
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
                <p class="mt-4 text-gray-600 dark:text-gray-400">Loading subscription data...</p>
            </div>
        @endif

        {{-- Subscription Content --}}
        @if(!$loading && !$error && $brandId && $this->hasSubscriptionData())
            {{-- Section Navigation --}}
            <x-section-nav :sections="[
                ['id' => 'kpis', 'label' => 'KPIs'],
                ['id' => 'trends', 'label' => 'Trends'],
                ['id' => 'frequency', 'label' => 'Frequency'],
                ['id' => 'movement', 'label' => 'Monthly Movement'],
            ]" />

            {{-- Summary KPIs --}}
            <div id="section-kpis" class="mb-6 grid gap-4 grid-cols-2 md:grid-cols-4">
                {{-- Active Subscriptions --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Active Subscriptions</div>
                    <div class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">
                        {{ $this->formatNumber($summary['active_subscriptions'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $this->formatNumber($summary['subscribers'] ?? 0) }} unique subscribers
                    </div>
                </div>

                {{-- MRR --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Monthly Recurring Revenue</div>
                    <div class="mt-2 text-3xl font-bold text-blue-600 dark:text-blue-400">
                        {{ $this->formatCurrency($summary['mrr'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        ARR: {{ $this->formatCurrency($summary['arr'] ?? 0) }}
                    </div>
                </div>

                {{-- Churn Rate --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Churn Rate</div>
                    <div class="mt-2 text-3xl font-bold {{ $this->getChurnColorClass($summary['churn_rate'] ?? 0) }}">
                        {{ $this->formatPercent($summary['churn_rate'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $this->formatNumber($summary['cancelled_subscriptions'] ?? 0) }} cancelled
                    </div>
                </div>

                {{-- Average LTV --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Average LTV</div>
                    <div class="mt-2 text-3xl font-bold text-purple-600 dark:text-purple-400">
                        {{ $this->formatCurrency($summary['avg_ltv'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Avg lifetime: {{ number_format($summary['avg_lifetime_days'] ?? 0, 0) }} days
                    </div>
                </div>
            </div>

            {{-- Additional KPIs Row --}}
            <div class="mb-6 grid gap-4 sm:grid-cols-3">
                {{-- Retention Rate --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Retention Rate</div>
                    <div class="mt-2 text-3xl font-bold {{ $this->getRetentionColorClass($summary['retention_rate'] ?? 0) }}">
                        {{ $this->formatPercent($summary['retention_rate'] ?? 0) }}
                    </div>
                </div>

                {{-- Avg Subscription Value --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Avg Subscription Value</div>
                    <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $this->formatCurrency($summary['avg_subscription_value'] ?? 0) }}
                    </div>
                </div>

                {{-- Paused Subscriptions --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Paused Subscriptions</div>
                    <div class="mt-2 text-3xl font-bold text-yellow-600 dark:text-yellow-400">
                        {{ $this->formatNumber($summary['paused_subscriptions'] ?? 0) }}
                    </div>
                </div>
            </div>

            {{-- Subscription Trend Chart --}}
            <x-filament::section id="section-trends" class="mb-6">
                <x-slot name="heading">
                    Subscription Trends
                </x-slot>
                <x-slot name="description">
                    New subscriptions vs churned over time
                </x-slot>

                @if(count($monthlyTrend) > 0)
                    <div class="h-80">
                        <canvas id="subscriptionTrendChart" wire:ignore></canvas>
                    </div>

                    <script>
                        document.addEventListener('livewire:navigated', function() {
                            initSubscriptionCharts();
                        });

                        document.addEventListener('DOMContentLoaded', function() {
                            initSubscriptionCharts();
                        });

                        function initSubscriptionCharts() {
                            const trendCtx = document.getElementById('subscriptionTrendChart');
                            if (!trendCtx) return;

                            if (window.subscriptionTrendChart) {
                                window.subscriptionTrendChart.destroy();
                            }

                            const chartData = @json($chartData);

                            window.subscriptionTrendChart = new Chart(trendCtx, {
                                type: 'bar',
                                data: {
                                    labels: chartData.subscriptions.labels,
                                    datasets: chartData.subscriptions.datasets
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: true,
                                            position: 'top'
                                        }
                                    },
                                    scales: {
                                        x: {
                                            title: {
                                                display: true,
                                                text: 'Month'
                                            }
                                        },
                                        y: {
                                            title: {
                                                display: true,
                                                text: 'Subscriptions'
                                            },
                                            beginAtZero: true
                                        }
                                    }
                                }
                            });
                        }

                        Livewire.on('chartDataUpdated', function() {
                            initSubscriptionCharts();
                        });
                    </script>
                @else
                    <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                        No trend data available for the selected period
                    </div>
                @endif
            </x-filament::section>

            {{-- Delivery Frequency Breakdown --}}
            <x-filament::section id="section-frequency" class="mb-6">
                <x-slot name="heading">
                    Subscription Frequency
                </x-slot>
                <x-slot name="description">
                    Breakdown by delivery frequency
                </x-slot>

                @if(count($byFrequency) > 0)
                    <div class="grid gap-6 md:grid-cols-2">
                        {{-- Chart --}}
                        <div class="h-64">
                            <canvas id="frequencyChart" wire:ignore></canvas>
                        </div>

                        {{-- Table --}}
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Frequency</th>
                                        <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Count</th>
                                        <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Total Value</th>
                                        <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Avg Value</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($byFrequency as $freq)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $freq['frequency'] }}</td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ $this->formatNumber($freq['count']) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ $this->formatCurrency($freq['total_value']) }}</td>
                                            <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ $this->formatCurrency($freq['avg_value']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <script>
                        document.addEventListener('livewire:navigated', function() {
                            initFrequencyChart();
                        });

                        document.addEventListener('DOMContentLoaded', function() {
                            initFrequencyChart();
                        });

                        function initFrequencyChart() {
                            const freqCtx = document.getElementById('frequencyChart');
                            if (!freqCtx) return;

                            if (window.frequencyChart) {
                                window.frequencyChart.destroy();
                            }

                            const chartData = @json($chartData);

                            window.frequencyChart = new Chart(freqCtx, {
                                type: 'doughnut',
                                data: {
                                    labels: chartData.frequency.labels,
                                    datasets: chartData.frequency.datasets
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: true,
                                            position: 'right'
                                        }
                                    }
                                }
                            });
                        }

                        Livewire.on('chartDataUpdated', function() {
                            initFrequencyChart();
                        });
                    </script>
                @else
                    <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                        No frequency data available
                    </div>
                @endif
            </x-filament::section>

            {{-- Monthly Table --}}
            <x-filament::section id="section-movement">
                <x-slot name="heading">
                    Monthly Subscription Movement
                </x-slot>

                @if(count($monthlyTrend) > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Month</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">New</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Churned</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Reactivated</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Net Change</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">New MRR</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Lost MRR</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Net MRR</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($monthlyTrend as $month)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $month['month'] }}</td>
                                        <td class="px-4 py-3 text-right text-green-600 dark:text-green-400">+{{ $month['new_subscriptions'] }}</td>
                                        <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">-{{ $month['churned'] }}</td>
                                        <td class="px-4 py-3 text-right text-blue-600 dark:text-blue-400">+{{ $month['reactivated'] }}</td>
                                        <td class="px-4 py-3 text-right font-medium {{ $month['net_change'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ $month['net_change'] >= 0 ? '+' : '' }}{{ $month['net_change'] }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-green-600 dark:text-green-400">{{ $this->formatCurrency($month['new_mrr']) }}</td>
                                        <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">{{ $this->formatCurrency($month['lost_mrr']) }}</td>
                                        <td class="px-4 py-3 text-right font-medium {{ $month['net_mrr'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ $month['net_mrr'] >= 0 ? '+' : '' }}{{ $this->formatCurrency($month['net_mrr']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                        No monthly data available
                    </div>
                @endif
            </x-filament::section>
        @endif

        {{-- No Data State --}}
        @if(!$loading && !$error && $brandId && !$this->hasSubscriptionData())
            <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
                <p class="text-gray-600 dark:text-gray-400">No subscription data available for this brand</p>
            </div>
        @endif

        {{-- No Brand Selected --}}
        @if(!$loading && !$error && !$brandId)
            <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
                <p class="text-gray-600 dark:text-gray-400">Please select a Pet Heaven brand to view subscription data</p>
            </div>
        @endif
    @endunless
</x-filament-panels::page>
