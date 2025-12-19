<x-filament-panels::page>
    @push('styles')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @endpush

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

        {{-- Export Buttons --}}
        @if(!$loading && !$error && $brandId)
            @include('filament.shared.components.export-buttons', [
                'showCsv' => true,
                'showChart' => true,
                'chartId' => 'revenueTrendChart',
                'chartFilename' => 'revenue_trend',
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
            <p class="mt-4 text-gray-600 dark:text-gray-400">Loading trend data...</p>
        </div>
    @endif

    {{-- Charts --}}
    @if(!$loading && !$error && $brandId)
        <div class="grid gap-6">
            {{-- Revenue Trend Chart/Table --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between w-full">
                        <span>Revenue Trend</span>
                        <button
                            wire:click="toggleRevenueView"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            @if($showRevenueAsTable)
                                <x-heroicon-o-chart-bar class="h-4 w-4" />
                                <span>View Chart</span>
                            @else
                                <x-heroicon-o-table-cells class="h-4 w-4" />
                                <span>View Table</span>
                            @endif
                        </button>
                    </div>
                </x-slot>
                <x-slot name="description">
                    Monthly revenue over the selected period
                </x-slot>

                <div class="p-4" wire:key="revenue-view-{{ $showRevenueAsTable ? 'table' : 'chart' }}">
                    @if($showRevenueAsTable)
                        {{-- Table View --}}
                        <div class="overflow-x-auto">
                            <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr class="bg-gray-50 dark:bg-gray-800">
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Month
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Revenue
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                    @forelse($this->getRevenueTableData() as $row)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                            <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $row['month'] }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">
                                                R{{ number_format($row['revenue'], 0) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                                No data available
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        {{-- Chart View --}}
                        <div class="relative h-64 md:h-80" wire:ignore>
                            <canvas id="revenueTrendChart"></canvas>
                        </div>
                    @endif
                </div>
            </x-filament::section>

            {{-- Two-column layout for smaller charts --}}
            <div class="grid gap-6 md:grid-cols-2">
                {{-- Revenue by Category (Stacked Bar) --}}
                <x-filament::section>
                    <x-slot name="heading">
                        Revenue by Category
                    </x-slot>
                    <x-slot name="description">
                        Top 5 categories breakdown
                    </x-slot>

                    <div class="p-4">
                        <div class="relative h-64" wire:ignore>
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </x-filament::section>

                {{-- Units Sold Trend --}}
                <x-filament::section>
                    <x-slot name="heading">
                        Units Sold
                    </x-slot>
                    <x-slot name="description">
                        Monthly units sold
                    </x-slot>

                    <div class="p-4">
                        <div class="relative h-64" wire:ignore>
                            <canvas id="unitsChart"></canvas>
                        </div>
                    </div>
                </x-filament::section>
            </div>

            {{-- AOV Trend --}}
            <x-filament::section>
                <x-slot name="heading">
                    Average Order Value
                </x-slot>
                <x-slot name="description">
                    Monthly average order value trend
                </x-slot>

                <div class="p-4">
                    <div class="relative h-56" wire:ignore>
                        <canvas id="aovChart"></canvas>
                    </div>
                </div>
            </x-filament::section>
        </div>

        @script
        <script>
            const charts = {};

            function destroyChart(id) {
                if (charts[id]) {
                    charts[id].destroy();
                    charts[id] = null;
                }
            }

            function initTrendsCharts(revenueData, categoryData, unitsData, aovData) {
                // Revenue Trend Chart
                const revenueCtx = document.getElementById('revenueTrendChart');
                if (revenueCtx) {
                    destroyChart('revenue');
                    charts['revenue'] = new Chart(revenueCtx, {
                        type: 'line',
                        data: revenueData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => 'Revenue: R' + ctx.parsed.y.toLocaleString()
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

                // Category Chart
                const categoryCtx = document.getElementById('categoryChart');
                if (categoryCtx) {
                    destroyChart('category');
                    charts['category'] = new Chart(categoryCtx, {
                        type: 'bar',
                        data: categoryData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => ctx.dataset.label + ': R' + ctx.parsed.y.toLocaleString()
                                    }
                                }
                            },
                            scales: {
                                x: { stacked: true },
                                y: {
                                    stacked: true,
                                    beginAtZero: true,
                                    ticks: { callback: (v) => 'R' + v.toLocaleString() }
                                }
                            }
                        }
                    });
                }

                // Units Chart
                const unitsCtx = document.getElementById('unitsChart');
                if (unitsCtx) {
                    destroyChart('units');
                    charts['units'] = new Chart(unitsCtx, {
                        type: 'line',
                        data: unitsData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => 'Units: ' + ctx.parsed.y.toLocaleString()
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { callback: (v) => v.toLocaleString() }
                                }
                            }
                        }
                    });
                }

                // AOV Chart
                const aovCtx = document.getElementById('aovChart');
                if (aovCtx) {
                    destroyChart('aov');
                    charts['aov'] = new Chart(aovCtx, {
                        type: 'line',
                        data: aovData,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => 'AOV: R' + ctx.parsed.y.toLocaleString()
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: false,
                                    ticks: { callback: (v) => 'R' + v.toLocaleString() }
                                }
                            }
                        }
                    });
                }
            }

            // Initialize on page load
            const initialRevenueData = @js($revenueChartData);
            const initialCategoryData = @js($categoryChartData);
            const initialUnitsData = @js($unitsChartData);
            const initialAovData = @js($aovChartData);

            if (typeof Chart !== 'undefined') {
                initTrendsCharts(initialRevenueData, initialCategoryData, initialUnitsData, initialAovData);
            } else {
                document.addEventListener('DOMContentLoaded', () => {
                    initTrendsCharts(initialRevenueData, initialCategoryData, initialUnitsData, initialAovData);
                });
            }

            // Listen for Livewire updates
            $wire.on('trends-data-updated', (event) => {
                setTimeout(() => {
                    initTrendsCharts(event.revenueData, event.categoryData, event.unitsData, event.aovData);
                }, 100);
            });
        </script>
        @endscript
    @endif

    {{-- No Brand Selected State --}}
    @if(!$loading && !$error && !$brandId)
        <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
            <p class="text-gray-600 dark:text-gray-400">Please select a brand to view trends</p>
        </div>
    @endif
</x-filament-panels::page>
