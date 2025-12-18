<x-filament-panels::page>
    @push('styles')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @endpush

    {{-- Brand and Period Filters --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
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
        <div class="w-full sm:w-64">
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
            <p class="mt-4 text-gray-600 dark:text-gray-400">Loading dashboard data...</p>
        </div>
    @endif

    {{-- Dashboard Content --}}
    @if(!$loading && !$error && $brandId)
        {{-- KPI Tiles --}}
        <div class="mb-6 grid gap-4 grid-cols-2 md:grid-cols-4">
            {{-- Revenue KPI --}}
            <x-filament::section class="text-center">
                <div class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Net Revenue
                </div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                    R{{ number_format($kpis['revenue'] ?? 0, 0) }}
                </div>
                @if(isset($kpis['revenue_change']))
                    <div class="mt-2 text-sm {{ $kpis['revenue_change'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $kpis['revenue_change'] >= 0 ? '▲' : '▼' }}
                        {{ number_format(abs($kpis['revenue_change']), 1) }}% MoM
                    </div>
                @endif
            </x-filament::section>

            {{-- Orders KPI --}}
            <x-filament::section class="text-center">
                <div class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Total Orders
                </div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                    {{ number_format($kpis['orders'] ?? 0, 0) }}
                </div>
                @if(isset($kpis['orders_change']))
                    <div class="mt-2 text-sm {{ $kpis['orders_change'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $kpis['orders_change'] >= 0 ? '▲' : '▼' }}
                        {{ number_format(abs($kpis['orders_change']), 1) }}% MoM
                    </div>
                @endif
            </x-filament::section>

            {{-- AOV KPI --}}
            <x-filament::section class="text-center">
                <div class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Avg Order Value
                </div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                    R{{ number_format($kpis['aov'] ?? 0, 0) }}
                </div>
                @if(isset($kpis['aov_change']))
                    <div class="mt-2 text-sm {{ $kpis['aov_change'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $kpis['aov_change'] >= 0 ? '▲' : '▼' }}
                        {{ number_format(abs($kpis['aov_change']), 1) }}% MoM
                    </div>
                @endif
            </x-filament::section>

            {{-- Units KPI --}}
            <x-filament::section class="text-center">
                <div class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Units Sold
                </div>
                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                    {{ number_format($kpis['units'] ?? 0, 0) }}
                </div>
                @if(isset($kpis['units_change']))
                    <div class="mt-2 text-sm {{ $kpis['units_change'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ $kpis['units_change'] >= 0 ? '▲' : '▼' }}
                        {{ number_format(abs($kpis['units_change']), 1) }}% MoM
                    </div>
                @endif
            </x-filament::section>
        </div>

        {{-- Revenue Trend Chart --}}
        <x-filament::section class="mb-6">
            <x-slot name="heading">
                Revenue Trend (12 Months)
            </x-slot>

            <div class="p-4">
                <div class="relative h-64 md:h-80" wire:ignore>
                    <canvas id="revenueTrendChart"></canvas>
                </div>
            </div>
        </x-filament::section>

        {{-- Top Products Table --}}
        <x-filament::section>
            <x-slot name="heading">
                Top 5 Products
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800">
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Product Name
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Revenue
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Units
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Growth
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        @forelse($topProducts as $product)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $product['name'] ?? 'N/A' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">
                                    R{{ number_format($product['revenue'] ?? 0, 0) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-700 dark:text-gray-300">
                                    {{ number_format($product['units'] ?? 0, 0) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm {{ ($product['growth'] ?? 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                    {{ ($product['growth'] ?? 0) >= 0 ? '▲' : '▼' }}
                                    {{ number_format(abs($product['growth'] ?? 0), 1) }}%
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No product data available
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        @script
        <script>
            let chartInstance = null;

            function initDashboardChart(data) {
                const ctx = document.getElementById('revenueTrendChart');
                if (!ctx) return;

                if (chartInstance) {
                    chartInstance.destroy();
                }

                chartInstance = new Chart(ctx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Revenue: R' + context.parsed.y.toLocaleString();
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return 'R' + value.toLocaleString();
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
                initDashboardChart(initialData);
            } else {
                document.addEventListener('DOMContentLoaded', () => initDashboardChart(initialData));
            }

            // Listen for Livewire updates
            $wire.on('dashboard-data-updated', (event) => {
                setTimeout(() => {
                    initDashboardChart(event.chartData);
                }, 100);
            });
        </script>
        @endscript
    @endif

    {{-- No Brand Selected State --}}
    @if(!$loading && !$error && !$brandId)
        <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
            <p class="text-gray-600 dark:text-gray-400">Please select a brand to view dashboard</p>
        </div>
    @endif
</x-filament-panels::page>
