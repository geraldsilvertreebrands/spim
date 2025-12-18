<x-filament-panels::page>
    <div class="pricing-panel">
        {{-- Error Message --}}
        @if($error)
            <div class="mb-6 rounded-lg bg-red-50 p-4 text-red-800 dark:bg-red-900/20 dark:text-red-400">
                <p class="font-medium">Error</p>
                <p class="mt-1 text-sm">{{ $error }}</p>
            </div>
        @endif

        {{-- No Products Message --}}
        @if(empty($products))
            <x-filament::section>
                <div class="py-8 text-center">
                    <x-heroicon-o-chart-bar class="mx-auto h-8 w-8 text-gray-400" />
                    <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No Price Data Available</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        No products with competitor price data found.
                    </p>
                </div>
            </x-filament::section>
        @else
            {{-- Controls Section --}}
            <x-filament::section class="mb-6">
                <div class="pricing-controls flex flex-col gap-4">
                    {{-- Product Selector --}}
                    <div class="w-full">
                        <label for="product-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Select Product
                        </label>
                        <select
                            id="product-select"
                            wire:model.live="selectedProductId"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white min-h-[44px]"
                        >
                            @foreach($products as $product)
                                <option value="{{ $product['id'] }}">{{ $product['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4">
                        {{-- Date Range Selector --}}
                        <div class="flex-1 sm:max-w-xs">
                            <label for="date-range-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Date Range
                            </label>
                            <select
                                id="date-range-select"
                                wire:model.live="dateRange"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white min-h-[44px]"
                            >
                                @foreach($this->getDateRangeOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Refresh Button --}}
                        <div class="flex items-end">
                            <x-filament::button wire:click="refresh" icon="heroicon-o-arrow-path" color="gray" class="w-full sm:w-auto min-h-[44px]">
                                Refresh
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </x-filament::section>

            {{-- Loading State --}}
            @if($loading)
                <x-filament::section>
                    <div class="py-12 text-center">
                        <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-primary-600 border-r-transparent"></div>
                        <p class="mt-4 text-gray-600 dark:text-gray-400">Loading price history...</p>
                    </div>
                </x-filament::section>
            @elseif($selectedProductId && !empty($chartData))
                {{-- Product Info --}}
                <div class="pricing-secondary-stats mb-6 grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-3">
                {{-- Selected Product --}}
                <x-filament::section class="text-center">
                    <div class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Product
                    </div>
                    <div class="mt-2 text-lg font-bold text-gray-900 dark:text-white truncate" title="{{ $this->getSelectedProductName() }}">
                        {{ $this->getSelectedProductName() ?? 'Not Selected' }}
                    </div>
                </x-filament::section>

                {{-- Our Price --}}
                <x-filament::section class="text-center">
                    <div class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Our Price
                    </div>
                    <div class="mt-2 text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                        {{ $this->formatPrice($ourPrice) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Reference line on chart
                    </div>
                </x-filament::section>

                {{-- Competitors Tracked --}}
                <x-filament::section class="text-center">
                    <div class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Competitors Tracked
                    </div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                        {{ count($competitors) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ implode(', ', array_slice($competitors, 0, 3)) }}{{ count($competitors) > 3 ? '...' : '' }}
                    </div>
                </x-filament::section>
            </div>

                {{-- Price History Chart --}}
                <x-filament::section>
                    <x-slot name="heading">
                        Price History Over Time
                    </x-slot>
                    <x-slot name="description">
                        <span class="hidden sm:inline">Compare competitor prices over the selected date range. Our price is shown as a dashed reference line.</span>
                        <span class="sm:hidden">Competitor price history chart</span>
                    </x-slot>

                    <div class="p-2 sm:p-4">
                        <div class="pricing-chart-container relative h-64 sm:h-80 lg:h-96">
                            <canvas id="priceHistoryChart"></canvas>
                        </div>
                    </div>
                </x-filament::section>

                {{-- Legend / Help Section --}}
                <div class="mt-6">
                    <x-filament::section>
                        <x-slot name="heading">
                            Chart Legend
                        </x-slot>

                        <div class="p-2 sm:p-4">
                            <div class="pricing-legend flex flex-col sm:flex-row flex-wrap items-start sm:items-center gap-3 sm:gap-6">
                            {{-- Our Price Legend --}}
                            <div class="flex items-center gap-2">
                                <div class="h-1 w-8 rounded" style="background-color: rgb(99, 102, 241); border: 2px dashed rgb(99, 102, 241);"></div>
                                <span class="text-sm text-gray-700 dark:text-gray-300">Our Price (Reference)</span>
                            </div>

                            {{-- Competitor Lines --}}
                            @foreach($competitors as $index => $competitor)
                                @php
                                    $colors = [
                                        'rgb(239, 68, 68)',    // Red
                                        'rgb(34, 197, 94)',    // Green
                                        'rgb(251, 191, 36)',   // Amber
                                        'rgb(168, 85, 247)',   // Purple
                                        'rgb(14, 165, 233)',   // Sky blue
                                        'rgb(236, 72, 153)',   // Pink
                                        'rgb(245, 158, 11)',   // Orange
                                        'rgb(20, 184, 166)',   // Teal
                                    ];
                                    $color = $colors[$index % count($colors)];
                                @endphp
                                <div class="flex items-center gap-2">
                                    <div class="h-1 w-8 rounded" style="background-color: {{ $color }};"></div>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $competitor }}</span>
                                </div>
                            @endforeach
                        </div>

                            <div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                <strong>Note:</strong> <span class="hidden sm:inline">Price data is shown for days where competitor prices were recorded. Gaps indicate no price data was collected for that day.</span>
                                <span class="sm:hidden">Gaps indicate missing data.</span>
                            </div>
                        </div>
                    </x-filament::section>
                </div>

                @push('scripts')
                <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        initPriceHistoryChart();
                    });

                    // Store chart instance globally so we can destroy it on updates
                    let priceHistoryChartInstance = null;

                    function initPriceHistoryChart() {
                        const ctx = document.getElementById('priceHistoryChart');
                        if (!ctx) return;

                        // Destroy existing chart if it exists
                        if (priceHistoryChartInstance) {
                            priceHistoryChartInstance.destroy();
                        }

                        const chartData = @js($chartData);

                        if (!chartData || !chartData.labels || chartData.labels.length === 0) {
                            return;
                        }

                        priceHistoryChartInstance = new Chart(ctx, {
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
                                        position: 'bottom',
                                        labels: {
                                            usePointStyle: true,
                                            padding: 20
                                        }
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                if (context.parsed.y === null) {
                                                    return context.dataset.label + ': No data';
                                                }
                                                return context.dataset.label + ': R' + context.parsed.y.toFixed(2);
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        display: true,
                                        title: {
                                            display: true,
                                            text: 'Date'
                                        },
                                        grid: {
                                            display: false
                                        }
                                    },
                                    y: {
                                        display: true,
                                        title: {
                                            display: true,
                                            text: 'Price (ZAR)'
                                        },
                                        beginAtZero: false,
                                        ticks: {
                                            callback: function(value) {
                                                return 'R' + value.toFixed(0);
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }

                    // Reinitialize chart on Livewire updates
                    document.addEventListener('livewire:navigated', function() {
                        setTimeout(initPriceHistoryChart, 100);
                    });

                    // Listen for Livewire updates to reinitialize chart
                    Livewire.hook('morph.updated', ({ el, component }) => {
                        if (el.id === 'priceHistoryChart' || el.querySelector('#priceHistoryChart')) {
                            setTimeout(initPriceHistoryChart, 100);
                        }
                    });
                </script>
            @endpush
        @elseif($selectedProductId && empty($chartData))
                {{-- No Chart Data --}}
                <x-filament::section>
                    <div class="py-8 text-center">
                        <x-heroicon-o-chart-bar class="mx-auto h-8 w-8 text-gray-400" />
                        <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No Price History</h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            No competitor price history data available for this product.
                        </p>
                    </div>
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
