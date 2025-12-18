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

        {{-- Export Buttons --}}
        @if(!$loading && !$error && count($orders) > 0)
            @include('filament.shared.components.export-buttons', [
                'showCsv' => true,
                'showChart' => false,
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
            <p class="mt-4 text-gray-600 dark:text-gray-400">Loading purchase order data...</p>
        </div>
    @endif

    {{-- Content --}}
    @if(!$loading && !$error && $brandId)
        {{-- Summary KPIs --}}
        <div class="grid gap-4 grid-cols-2 md:grid-cols-4 mb-6">
            {{-- Total POs --}}
            <div class="bg-white rounded-lg shadow p-6 text-center dark:bg-gray-800">
                <div class="text-sm text-gray-500 uppercase tracking-wide dark:text-gray-400">Total POs</div>
                <div class="text-4xl font-bold text-gray-900 mt-2 dark:text-white">{{ number_format($summary['total_pos']) }}</div>
            </div>

            {{-- On-Time % --}}
            <div class="bg-white rounded-lg shadow p-6 text-center dark:bg-gray-800">
                <div class="text-sm text-gray-500 uppercase tracking-wide dark:text-gray-400">On-Time Delivery</div>
                <div class="text-4xl font-bold mt-2 {{ $summary['on_time_pct'] >= 90 ? 'text-green-600' : ($summary['on_time_pct'] >= 75 ? 'text-yellow-600' : 'text-red-600') }}">
                    {{ number_format($summary['on_time_pct'], 1) }}%
                </div>
            </div>

            {{-- In-Full % --}}
            <div class="bg-white rounded-lg shadow p-6 text-center dark:bg-gray-800">
                <div class="text-sm text-gray-500 uppercase tracking-wide dark:text-gray-400">In-Full Delivery</div>
                <div class="text-4xl font-bold mt-2 {{ $summary['in_full_pct'] >= 90 ? 'text-green-600' : ($summary['in_full_pct'] >= 75 ? 'text-yellow-600' : 'text-red-600') }}">
                    {{ number_format($summary['in_full_pct'], 1) }}%
                </div>
            </div>

            {{-- OTIF % --}}
            <div class="bg-white rounded-lg shadow p-6 text-center dark:bg-gray-800">
                <div class="text-sm text-gray-500 uppercase tracking-wide dark:text-gray-400">OTIF (On-Time & In-Full)</div>
                <div class="text-4xl font-bold mt-2 {{ $summary['otif_pct'] >= 90 ? 'text-green-600' : ($summary['otif_pct'] >= 75 ? 'text-yellow-600' : 'text-red-600') }}">
                    {{ number_format($summary['otif_pct'], 1) }}%
                </div>
            </div>
        </div>

        {{-- Chart --}}
        @if(count($chartData['labels']) > 0)
            <x-filament::section class="mb-6">
                <x-slot name="heading">
                    PO Metrics Over Time
                </x-slot>
                <x-slot name="description">
                    Monthly purchase order count and delivery performance
                </x-slot>

                <div class="h-80">
                    <canvas id="poChart" wire:ignore></canvas>
                </div>
            </x-filament::section>
        @endif

        {{-- Orders Table --}}
        <x-filament::section>
            <x-slot name="heading">
                Purchase Orders
            </x-slot>
            <x-slot name="description">
                {{ count($orders) }} orders found
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800">
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                PO Number
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Order Date
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Status
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Lines
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Total Value
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                On-Time
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                In-Full
                            </th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        @forelse($orders as $order)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $order['po_number'] ?? 'N/A' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-400">
                                    {{ $order['order_date'] ? \Carbon\Carbon::parse($order['order_date'])->format('d M Y') : 'N/A' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $this->getStatusBadgeClass($order['status'] ?? 'pending') }}">
                                        {{ ucfirst($order['status'] ?? 'pending') }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center text-gray-600 dark:text-gray-400">
                                    {{ $order['line_count'] ?? 0 }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                    R{{ number_format($order['total_value'] ?? 0, 0) }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    @if($order['delivered_on_time'] ?? false)
                                        <span class="text-green-600">&#10003;</span>
                                    @else
                                        <span class="text-red-600">&#10005;</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    @if($order['delivered_in_full'] ?? false)
                                        <span class="text-green-600">&#10003;</span>
                                    @else
                                        <span class="text-red-600">&#10005;</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    <button
                                        wire:click="openPoDetail('{{ $order['po_number'] }}')"
                                        class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300 font-medium text-sm">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No purchase orders found for this period
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- No Brand Selected State --}}
    @if(!$loading && !$error && !$brandId)
        <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
            <p class="text-gray-600 dark:text-gray-400">Please select a brand to view purchase orders</p>
        </div>
    @endif

    {{-- PO Detail Modal --}}
    @if($showDetailModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
                {{-- Background overlay --}}
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closePoDetail"></div>

                {{-- Modal panel --}}
                <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:align-middle dark:bg-gray-800">
                    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4 dark:bg-gray-800">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-semibold leading-6 text-gray-900 dark:text-white" id="modal-title">
                                    Purchase Order: {{ $selectedPoNumber }}
                                </h3>
                                @if($selectedPoDetails)
                                    <div class="mt-2 grid grid-cols-2 gap-4 text-sm text-gray-500 dark:text-gray-400">
                                        <div>
                                            <span class="font-medium">Order Date:</span>
                                            {{ $selectedPoDetails['order_date'] ? \Carbon\Carbon::parse($selectedPoDetails['order_date'])->format('d M Y') : 'N/A' }}
                                        </div>
                                        <div>
                                            <span class="font-medium">Status:</span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $this->getStatusBadgeClass($selectedPoDetails['status'] ?? 'pending') }}">
                                                {{ ucfirst($selectedPoDetails['status'] ?? 'pending') }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="font-medium">Expected Delivery:</span>
                                            {{ $selectedPoDetails['expected_delivery_date'] ? \Carbon\Carbon::parse($selectedPoDetails['expected_delivery_date'])->format('d M Y') : 'N/A' }}
                                        </div>
                                        <div>
                                            <span class="font-medium">Actual Delivery:</span>
                                            {{ $selectedPoDetails['actual_delivery_date'] ? \Carbon\Carbon::parse($selectedPoDetails['actual_delivery_date'])->format('d M Y') : 'N/A' }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <button
                                wire:click="closePoDetail"
                                class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:bg-gray-800">
                                <span class="sr-only">Close</span>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {{-- Line Items --}}
                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Line Items</h4>
                            <div class="overflow-x-auto">
                                <table class="w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead>
                                        <tr class="bg-gray-50 dark:bg-gray-700">
                                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                                Line
                                            </th>
                                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                                SKU
                                            </th>
                                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                                Product
                                            </th>
                                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                                Ordered
                                            </th>
                                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                                Delivered
                                            </th>
                                            <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                                Unit Price
                                            </th>
                                            <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                        @forelse($selectedPoLines as $line)
                                            <tr>
                                                <td class="whitespace-nowrap px-3 py-2 text-gray-600 dark:text-gray-400">
                                                    {{ $line['line_number'] }}
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">
                                                    {{ $line['sku'] }}
                                                </td>
                                                <td class="px-3 py-2 text-gray-900 dark:text-white max-w-xs truncate">
                                                    {{ $line['product_name'] }}
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-600 dark:text-gray-400">
                                                    {{ number_format($line['quantity_ordered']) }}
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2 text-right {{ $line['quantity_delivered'] < $line['quantity_ordered'] ? 'text-red-600' : 'text-green-600' }}">
                                                    {{ number_format($line['quantity_delivered']) }}
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2 text-right text-gray-600 dark:text-gray-400">
                                                    R{{ number_format($line['unit_price'], 2) }}
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-2">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $this->getStatusBadgeClass($line['delivery_status'] ?? 'pending') }}">
                                                        {{ ucfirst($line['delivery_status'] ?? 'pending') }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                    No line items available
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 dark:bg-gray-700">
                        <button
                            type="button"
                            wire:click="closePoDetail"
                            class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto dark:bg-gray-600 dark:text-white dark:ring-gray-500 dark:hover:bg-gray-500">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Chart.js Script --}}
    @if(!$loading && !$error && count($chartData['labels']) > 0)
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                document.addEventListener('livewire:navigated', function() {
                    initPoChart();
                });

                document.addEventListener('DOMContentLoaded', function() {
                    initPoChart();
                });

                function initPoChart() {
                    const chartEl = document.getElementById('poChart');
                    if (!chartEl) return;

                    // Destroy existing chart if any
                    if (window.poChartInstance) {
                        window.poChartInstance.destroy();
                    }

                    const ctx = chartEl.getContext('2d');
                    const chartData = @json($chartData);

                    window.poChartInstance = new Chart(ctx, {
                        data: {
                            labels: chartData.labels,
                            datasets: chartData.datasets
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            plugins: {
                                legend: {
                                    position: 'top',
                                },
                            },
                            scales: {
                                y: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    title: {
                                        display: true,
                                        text: 'PO Count'
                                    },
                                    beginAtZero: true
                                },
                                y1: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    title: {
                                        display: true,
                                        text: 'Percentage'
                                    },
                                    min: 0,
                                    max: 100,
                                    grid: {
                                        drawOnChartArea: false,
                                    },
                                },
                            }
                        }
                    });
                }
            </script>
        @endpush
    @endif
</x-filament-panels::page>
