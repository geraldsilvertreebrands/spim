<x-filament-panels::page>
    {{-- Brand and Period Filters --}}
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

            {{-- Period Filter --}}
            <div class="w-full sm:w-48">
                <label for="periodSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Time Period
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

        <div class="flex items-center gap-4">
            {{-- Info Badge --}}
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <svg width="16" height="16" class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Competitor names are anonymized</span>
            </div>

            {{-- Export Buttons --}}
            @if(!$loading && !$error && $brandId)
                @include('filament.shared.components.export-buttons', [
                    'showCsv' => false,
                    'showChart' => true,
                    'chartId' => 'trendComparisonChart',
                    'chartFilename' => 'benchmark_trends',
                    'showPrint' => true,
                ])
            @endif
        </div>
    </div>

    {{-- Loading State --}}
    @if($loading)
        <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
            <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-primary-600 border-r-transparent"></div>
            <p class="mt-4 text-gray-600 dark:text-gray-400">Loading benchmark data...</p>
        </div>
    @elseif($error)
        {{-- Error State - show full message to prevent empty state --}}
        <div class="rounded-lg bg-yellow-50 p-6 dark:bg-yellow-900/20">
            <div class="flex items-start gap-3">
                <svg width="20" height="20" class="w-5 h-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <p class="font-medium text-yellow-800 dark:text-yellow-300">Notice</p>
                    <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-400">{{ $error }}</p>
                    @if(str_contains($error, 'No competitor'))
                        <p class="mt-3 text-sm text-yellow-600 dark:text-yellow-500">Contact your account manager to set up competitor benchmarking.</p>
                    @endif
                </div>
            </div>
        </div>
    @elseif(!$brandId)
        {{-- No Brand Selected --}}
        <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
            <p class="text-gray-600 dark:text-gray-400">Please select a brand to view benchmarks</p>
        </div>
    @else
        {{-- Benchmark Content --}}
        <div class="grid gap-6">
            {{-- Revenue Comparison Chart (Bar) --}}
            <x-filament::section>
                <x-slot name="heading">
                    Revenue Comparison
                </x-slot>
                <x-slot name="description">
                    Compare your revenue against competitors for the selected period
                </x-slot>

                <div class="p-4">
                    <div class="relative h-48 md:h-64 lg:h-80">
                        <canvas id="revenueComparisonChart"></canvas>
                    </div>
                </div>
            </x-filament::section>

            {{-- Trend Comparison Chart/Table --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between w-full">
                        <span>Revenue Trend Comparison</span>
                        <button
                            wire:click="toggleTrendView"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            @if($showTrendAsTable)
                                <svg width="16" height="16" class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                <span>View Chart</span>
                            @else
                                <svg width="16" height="16" class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <span>View Table</span>
                            @endif
                        </button>
                    </div>
                </x-slot>
                <x-slot name="description">
                    Monthly revenue trends compared to competitors
                </x-slot>

                <div class="p-4" wire:key="benchmark-trend-view-{{ $showTrendAsTable ? 'table' : 'chart' }}">
                    @if($showTrendAsTable)
                        {{-- Table View --}}
                        <div class="overflow-x-auto">
                            <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr class="bg-gray-50 dark:bg-gray-800">
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Month
                                        </th>
                                        @foreach($this->getTrendDatasetLabels() as $label)
                                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                                {{ $label }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                    @forelse($this->getTrendTableData() as $row)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $row['month'] }}
                                            </td>
                                            @foreach($this->getTrendDatasetLabels() as $label)
                                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">
                                                    R{{ number_format($row[$label] ?? 0, 0) }}
                                                </td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ count($this->getTrendDatasetLabels()) + 1 }}" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                                No data available
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        {{-- Chart View --}}
                        <div class="relative h-48 md:h-64 lg:h-80">
                            <canvas id="trendComparisonChart"></canvas>
                        </div>
                    @endif
                </div>
            </x-filament::section>

            {{-- Market Share by Category Table --}}
            <x-filament::section>
                <x-slot name="heading">
                    Market Share by Category
                </x-slot>
                <x-slot name="description">
                    Your market share compared to competitors by product category
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Category
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-primary-600 dark:text-primary-400">
                                    Your Brand
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-blue-600 dark:text-blue-400">
                                    Competitor A
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-amber-600 dark:text-amber-400">
                                    Competitor B
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-red-600 dark:text-red-400">
                                    Competitor C
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                            @forelse($marketShareData as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <td class="px-4 py-3 text-gray-900 dark:text-white">
                                        <span class="font-medium">{{ $row['category'] }}</span>
                                        @if($row['subcategory'])
                                            <span class="text-gray-500 dark:text-gray-400"> / {{ $row['subcategory'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900/30 dark:text-primary-400">
                                            {{ number_format($row['brand_share'], 1) }}%
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                        {{ number_format($row['competitor_shares']['Competitor A'] ?? 0, 1) }}%
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                        {{ number_format($row['competitor_shares']['Competitor B'] ?? 0, 1) }}%
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                        {{ number_format($row['competitor_shares']['Competitor C'] ?? 0, 1) }}%
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No market share data available
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            {{-- Legend --}}
            <div class="flex flex-wrap gap-4 justify-center text-sm">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-primary-600"></span>
                    <span class="text-gray-600 dark:text-gray-400">Your Brand</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                    <span class="text-gray-600 dark:text-gray-400">Competitor A</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                    <span class="text-gray-600 dark:text-gray-400">Competitor B</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-red-500"></span>
                    <span class="text-gray-600 dark:text-gray-400">Competitor C</span>
                </div>
            </div>
        </div>

        @push('styles')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        @endpush

        @script
        <script>
            const benchmarkCharts = {};

            function destroyBenchmarkChart(id) {
                if (benchmarkCharts[id]) {
                    benchmarkCharts[id].destroy();
                    benchmarkCharts[id] = null;
                }
            }

            function initBenchmarkCharts(revenueData, trendData) {
                // Revenue Comparison Chart (Bar)
                const revenueCtx = document.getElementById('revenueComparisonChart');
                if (revenueCtx) {
                    destroyBenchmarkChart('revenue');
                    benchmarkCharts['revenue'] = new Chart(revenueCtx, {
                        type: 'bar',
                        data: revenueData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => 'Revenue: R' + ctx.parsed.x.toLocaleString()
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    ticks: { callback: (v) => 'R' + v.toLocaleString() }
                                }
                            }
                        }
                    });
                }

                // Trend Comparison Chart (Line)
                const trendCtx = document.getElementById('trendComparisonChart');
                if (trendCtx) {
                    destroyBenchmarkChart('trend');
                    benchmarkCharts['trend'] = new Chart(trendCtx, {
                        type: 'line',
                        data: trendData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { boxWidth: 12, padding: 20 }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => ctx.dataset.label + ': R' + ctx.parsed.y.toLocaleString()
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { callback: (v) => 'R' + v.toLocaleString() }
                                }
                            }
                        }
                    });
                }
            }

            // Initialize on page load
            const initialRevenueData = @js($revenueComparisonData);
            const initialTrendData = @js($trendComparisonData);

            if (typeof Chart !== 'undefined') {
                initBenchmarkCharts(initialRevenueData, initialTrendData);
            } else {
                document.addEventListener('DOMContentLoaded', () => {
                    initBenchmarkCharts(initialRevenueData, initialTrendData);
                });
            }

            // Listen for Livewire updates
            $wire.on('benchmarks-data-updated', (event) => {
                setTimeout(() => {
                    initBenchmarkCharts(event.revenueData, event.trendData);
                }, 100);
            });
        </script>
        @endscript
    @endif
</x-filament-panels::page>
