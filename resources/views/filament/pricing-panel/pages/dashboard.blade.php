<x-filament-panels::page>
    <div class="pricing-panel">
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
                <p class="mt-4 text-gray-600 dark:text-gray-400">Loading pricing data...</p>
            </div>
        @endif

        {{-- Dashboard Content --}}
        @if(!$loading && !$error)
            {{-- KPI Tiles --}}
            <div class="pricing-kpi-grid mb-6 grid gap-3 sm:gap-4 grid-cols-2 lg:grid-cols-4">
            {{-- Products Tracked KPI --}}
            <x-filament::section class="text-center">
                <div class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Products Tracked
                </div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                    {{ number_format($kpis['products_tracked'] ?? 0, 0) }}
                </div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    With competitor pricing
                </div>
            </x-filament::section>

            {{-- Market Position KPI --}}
            <x-filament::section class="text-center">
                <div class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Avg Price Position
                </div>
                <div class="mt-2">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-lg font-semibold {{ $this->getPositionBadgeClass() }}">
                        {{ $this->getPositionLabel() }}
                    </span>
                </div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    vs competitors
                </div>
            </x-filament::section>

            {{-- Recent Price Changes KPI --}}
            <x-filament::section class="text-center">
                <div class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Price Changes
                </div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                    {{ number_format($kpis['recent_price_changes'] ?? 0, 0) }}
                </div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    This week
                </div>
            </x-filament::section>

            {{-- Active Alerts KPI --}}
            <x-filament::section class="text-center">
                <div class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Active Alerts
                </div>
                <div class="mt-2 text-3xl font-bold {{ ($kpis['active_alerts'] ?? 0) > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">
                    {{ number_format($kpis['active_alerts'] ?? 0, 0) }}
                </div>
                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Monitoring prices
                </div>
            </x-filament::section>
        </div>

            {{-- Secondary KPIs --}}
            <div class="pricing-secondary-stats mb-6 grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-3">
            {{-- Cheapest Products --}}
            <div class="rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                <div class="flex items-center gap-3">
                    <div class="rounded-full bg-green-100 p-2 dark:bg-green-800">
                        <x-heroicon-o-arrow-trending-down class="h-5 w-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="text-sm text-green-700 dark:text-green-300">Cheapest in Market</p>
                        <p class="text-2xl font-bold text-green-800 dark:text-green-200">{{ number_format($kpis['products_cheapest'] ?? 0) }}</p>
                    </div>
                </div>
            </div>

            {{-- Most Expensive Products --}}
            <div class="rounded-lg bg-red-50 p-4 dark:bg-red-900/20">
                <div class="flex items-center gap-3">
                    <div class="rounded-full bg-red-100 p-2 dark:bg-red-800">
                        <x-heroicon-o-arrow-trending-up class="h-5 w-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <p class="text-sm text-red-700 dark:text-red-300">Most Expensive</p>
                        <p class="text-2xl font-bold text-red-800 dark:text-red-200">{{ number_format($kpis['products_most_expensive'] ?? 0) }}</p>
                    </div>
                </div>
            </div>

            {{-- Competitor Undercuts --}}
            <div class="rounded-lg bg-amber-50 p-4 dark:bg-amber-900/20">
                <div class="flex items-center gap-3">
                    <div class="rounded-full bg-amber-100 p-2 dark:bg-amber-800">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <p class="text-sm text-amber-700 dark:text-amber-300">Competitor Undercuts</p>
                        <p class="text-2xl font-bold text-amber-800 dark:text-amber-200">{{ number_format($kpis['competitor_undercuts'] ?? 0) }}</p>
                    </div>
                </div>
            </div>
        </div>

            {{-- Charts Row --}}
            <div class="mb-6 grid gap-4 sm:gap-6 grid-cols-1 md:grid-cols-2">
            {{-- Price Position Histogram --}}
            <x-filament::section>
                <x-slot name="heading">
                    Price Position Distribution
                </x-slot>

                <div class="p-2 sm:p-4">
                    <div class="pricing-chart-container relative h-48 sm:h-64">
                        <canvas id="positionChart"></canvas>
                    </div>
                </div>
            </x-filament::section>

            {{-- Price Changes This Week --}}
            <x-filament::section>
                <x-slot name="heading">
                    Price Changes This Week
                </x-slot>

                <div class="p-2 sm:p-4">
                    <div class="pricing-chart-container relative h-48 sm:h-64">
                        <canvas id="priceChangesChart"></canvas>
                    </div>
                </div>
            </x-filament::section>
            </div>

            {{-- Recent Alerts Table --}}
            @if(count($recentAlerts) > 0)
                <x-filament::section>
                    <x-slot name="heading">
                        Recent Alert Activity
                    </x-slot>

                    {{-- Mobile scroll hint --}}
                    <p class="pricing-table-scroll-hint sm:hidden text-center text-xs text-gray-500 mb-2">
                        <span class="scroll-indicator inline-block">&larr;</span>
                        Scroll horizontally to see more
                        <span class="scroll-indicator inline-block">&rarr;</span>
                    </p>

                    <div class="pricing-table-container overflow-x-auto -mx-4 sm:mx-0">
                        <table class="pricing-table w-full divide-y divide-gray-200 dark:divide-gray-700 min-w-[500px] sm:min-w-0">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Alert Type
                                    </th>
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Product
                                    </th>
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 hide-mobile hidden sm:table-cell">
                                        Competitor
                                    </th>
                                    <th class="px-3 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Triggered
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                @foreach($recentAlerts as $alert)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                        <td class="whitespace-nowrap px-3 sm:px-4 py-2 sm:py-3">
                                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                                @switch($alert['type'])
                                                    @case('price_below')
                                                        bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300
                                                        @break
                                                    @case('competitor_beats')
                                                        bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300
                                                        @break
                                                    @case('price_change')
                                                        bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300
                                                        @break
                                                    @case('out_of_stock')
                                                        bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300
                                                        @break
                                                    @default
                                                        bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300
                                                @endswitch
                                            ">
                                                {{ $alert['type_label'] }}
                                            </span>
                                        </td>
                                        <td class="px-3 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-gray-900 dark:text-white max-w-[150px] truncate">
                                            {{ $alert['product_name'] }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-gray-700 dark:text-gray-300 hide-mobile hidden sm:table-cell">
                                            {{ $alert['competitor'] }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 sm:px-4 py-2 sm:py-3 text-xs sm:text-sm text-gray-500 dark:text-gray-400">
                                            {{ $alert['triggered_at'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif

            {{-- Refresh Button --}}
            <div class="mt-6 flex justify-center sm:justify-end">
                <x-filament::button wire:click="refresh" icon="heroicon-o-arrow-path" class="w-full sm:w-auto">
                    Refresh Data
                </x-filament::button>
            </div>
            @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Position Histogram Chart
                    const positionCtx = document.getElementById('positionChart');
                    if (positionCtx) {
                        const positionData = @js($positionChartData);
                        new Chart(positionCtx, {
                            type: 'bar',
                            data: positionData,
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                return context.parsed.y + ' products';
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            stepSize: 1
                                        }
                                    }
                                }
                            }
                        });
                    }

                    // Price Changes Chart
                    const changesCtx = document.getElementById('priceChangesChart');
                    if (changesCtx) {
                        const changesData = @js($priceChangesChartData);
                        new Chart(changesCtx, {
                            type: 'bar',
                            data: changesData,
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'bottom'
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                return context.dataset.label + ': ' + context.parsed.y;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        stacked: false
                                    },
                                    y: {
                                        beginAtZero: true,
                                        ticks: {
                                            stepSize: 1
                                        }
                                    }
                                }
                            }
                        });
                    }
                });

                // Reinitialize charts on Livewire updates
                document.addEventListener('livewire:navigated', function() {
                    // Charts will reinitialize via DOMContentLoaded
                });
            </script>
            @endpush
        @endif
    </div>
</x-filament-panels::page>
