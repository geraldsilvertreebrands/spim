<x-filament-panels::page>
    @push('styles')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @endpush

    {{-- Premium Feature Gate --}}
    @unless(auth()->user()->hasRole('supplier-premium') || auth()->user()->hasRole('admin'))
        <x-premium-gate feature="Sales Forecasting">
            <p class="text-gray-600 dark:text-gray-400">
                Upgrade to Premium to access AI-powered sales forecasting with multiple scenarios,
                confidence intervals, and trend analysis to plan your business strategy.
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

                {{-- Scenario Selector --}}
                <div class="w-full sm:w-48">
                    <label for="scenarioSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Scenario
                    </label>
                    <select
                        wire:model.live="scenario"
                        id="scenarioSelect"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        @foreach($this->getScenarioOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Forecast Period --}}
                <div class="w-full sm:w-40">
                    <label for="forecastMonthsSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Forecast Period
                    </label>
                    <select
                        wire:model.live="forecastMonths"
                        id="forecastMonthsSelect"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        @foreach($this->getForecastPeriodOptions() as $value => $label)
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
                    'chartId' => 'forecastChart',
                    'chartFilename' => 'sales_forecast',
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
                <p class="mt-4 text-gray-600 dark:text-gray-400">Generating forecast...</p>
            </div>
        @endif

        {{-- Forecast Content --}}
        @if(!$loading && !$error && $brandId)
            {{-- Summary KPIs --}}
            <div class="mb-6 grid gap-4 grid-cols-2 md:grid-cols-4">
                {{-- Historical Average --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Monthly (Historical)</div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $this->formatCurrency($summaryStats['avg_historical'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Based on last 12 months
                    </div>
                </div>

                {{-- Forecast Total --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ ucfirst($scenario) }} Forecast Total
                    </div>
                    <div class="mt-2 text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ $this->formatCurrency($this->getSelectedScenarioTotal()) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Next {{ $forecastMonths }} months
                    </div>
                </div>

                {{-- Growth Rate --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Projected Growth</div>
                    <div class="mt-2 flex items-center gap-2">
                        <span class="text-2xl font-bold {{ $this->getTrendColorClass() }}">
                            {{ $this->getTrendIcon() }}
                            @if($summaryStats['growth_rate'] !== null)
                                {{ abs($summaryStats['growth_rate']) }}%
                            @else
                                N/A
                            @endif
                        </span>
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Month-over-month trend
                    </div>
                </div>

                {{-- Trend Status --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Trend</div>
                    <div class="mt-2 text-2xl font-bold {{ $this->getTrendColorClass() }}">
                        {{ ucfirst($summaryStats['trend'] ?? 'stable') }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Based on linear regression
                    </div>
                </div>
            </div>

            {{-- Forecast Chart --}}
            <x-filament::section class="mb-6">
                <x-slot name="heading">
                    Revenue Forecast
                </x-slot>
                <x-slot name="description">
                    Historical revenue with projected forecast and confidence intervals
                </x-slot>

                @if(count($historicalData) >= 3)
                    <div class="relative h-64 md:h-80 lg:h-96" wire:ignore>
                        <canvas id="forecastChart"></canvas>
                    </div>

                    @script
                    <script>
                        let forecastChartInstance = null;

                        function initForecastChart(chartData) {
                            const ctx = document.getElementById('forecastChart');
                            if (!ctx || !chartData || !chartData.labels) return;

                            if (forecastChartInstance) {
                                forecastChartInstance.destroy();
                            }

                            forecastChartInstance = new Chart(ctx, {
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
                                            labels: {
                                                filter: function(item) {
                                                    return item.text !== 'Lower Bound';
                                                }
                                            }
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    if (context.raw === null) return null;
                                                    return context.dataset.label + ': R' + context.raw.toLocaleString();
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        x: {
                                            display: true,
                                            title: {
                                                display: true,
                                                text: 'Month'
                                            }
                                        },
                                        y: {
                                            display: true,
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

                        // Initialize on page load
                        const initialData = @js($chartData);
                        if (typeof Chart !== 'undefined') {
                            initForecastChart(initialData);
                        } else {
                            document.addEventListener('DOMContentLoaded', () => initForecastChart(initialData));
                        }

                        // Listen for Livewire updates
                        $wire.on('forecast-data-updated', (event) => {
                            setTimeout(() => {
                                initForecastChart(event.chartData);
                            }, 100);
                        });
                    </script>
                    @endscript
                @else
                    <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                        <svg width="32" height="32" class="mx-auto w-8 h-8 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <p class="mt-2 text-sm">Insufficient historical data for forecasting</p>
                        <p class="text-xs">At least 3 months of data required</p>
                    </div>
                @endif
            </x-filament::section>

            {{-- Scenario Comparison Table --}}
            <x-filament::section class="mb-6">
                <x-slot name="heading">
                    Scenario Comparison
                </x-slot>
                <x-slot name="description">
                    Monthly breakdown by scenario with confidence intervals
                </x-slot>

                @if(count($forecastData) > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Month</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Pessimistic</th>
                                    <th class="px-4 py-3 text-right font-semibold text-blue-600 dark:text-blue-400">Baseline</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Optimistic</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">95% CI</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($forecastData as $row)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                            {{ $row['month'] }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">
                                            {{ $this->formatCurrency($row['pessimistic']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium text-blue-600 dark:text-blue-400">
                                            {{ $this->formatCurrency($row['baseline']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-green-600 dark:text-green-400">
                                            {{ $this->formatCurrency($row['optimistic']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-xs text-gray-500 dark:text-gray-400">
                                            {{ $this->formatCurrency($row['lower_bound']) }} - {{ $this->formatCurrency($row['upper_bound']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-gray-300 dark:border-gray-600">
                                <tr class="font-semibold">
                                    <td class="px-4 py-3 text-gray-900 dark:text-white">Total</td>
                                    <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">
                                        {{ $this->formatCurrency($summaryStats['total_forecast_pessimistic'] ?? 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-blue-600 dark:text-blue-400">
                                        {{ $this->formatCurrency($summaryStats['total_forecast_baseline'] ?? 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-green-600 dark:text-green-400">
                                        {{ $this->formatCurrency($summaryStats['total_forecast_optimistic'] ?? 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">-</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                        No forecast data available
                    </div>
                @endif
            </x-filament::section>

            {{-- Methodology Note --}}
            <x-filament::section>
                <x-slot name="heading">
                    Forecast Methodology
                </x-slot>

                <div class="prose prose-sm dark:prose-invert max-w-none">
                    <p class="text-gray-600 dark:text-gray-400">
                        This forecast uses linear regression analysis on your historical sales data to project future revenue.
                    </p>
                    <ul class="text-gray-600 dark:text-gray-400 text-sm mt-3 space-y-1">
                        <li><span class="font-medium text-blue-600 dark:text-blue-400">Baseline:</span> Linear trend projection based on historical data</li>
                        <li><span class="font-medium text-green-600 dark:text-green-400">Optimistic:</span> Baseline + 15% growth factor</li>
                        <li><span class="font-medium text-red-600 dark:text-red-400">Pessimistic:</span> Baseline - 10% reduction factor</li>
                        <li><span class="font-medium">95% Confidence Interval:</span> Statistical range within which actual values are expected to fall</li>
                    </ul>
                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-4">
                        Note: Forecasts are statistical projections and actual results may vary based on market conditions, seasonality, and other factors.
                    </p>
                </div>
            </x-filament::section>
        @endif

        {{-- No Brand Selected --}}
        @if(!$loading && !$error && !$brandId)
            <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
                <p class="text-gray-600 dark:text-gray-400">Please select a brand to view forecast data</p>
            </div>
        @endif
    @endunless
</x-filament-panels::page>
