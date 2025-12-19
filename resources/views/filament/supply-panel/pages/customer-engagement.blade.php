<x-filament-panels::page>
    {{-- Premium Feature Gate --}}
    @unless(auth()->user()->hasRole('supplier-premium') || auth()->user()->hasRole('admin'))
        <x-premium-gate feature="Customer Engagement Analytics">
            <p class="text-gray-600 dark:text-gray-400">
                Upgrade to Premium to access detailed customer engagement metrics including reorder rates,
                purchase frequency, and promotional analysis for each of your products.
            </p>
        </x-premium-gate>
    @else
        {{-- Brand and Period Filters --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-wrap gap-4 items-end">
                {{-- Brand Selector --}}
                @if(count($this->getAvailableBrands()) > 1)
                    <div class="w-full sm:w-48">
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
                <div class="w-full sm:w-40">
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

                {{-- Metric Threshold Filters --}}
                @if(!$loading && count($allEngagementData) > 0)
                    <div class="w-full sm:w-36">
                        <label for="minReorderRate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Min Reorder Rate
                        </label>
                        <select
                            wire:model.live="minReorderRate"
                            id="minReorderRate"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach($this->getReorderRateOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="w-full sm:w-36">
                        <label for="maxPromoIntensity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Max Promo %
                        </label>
                        <select
                            wire:model.live="maxPromoIntensity"
                            id="maxPromoIntensity"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach($this->getPromoIntensityOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($this->hasActiveFilters())
                        <button
                            wire:click="clearFilters"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Clear
                        </button>
                    @endif
                @endif
            </div>

            {{-- Export Button --}}
            @if(count($engagementData) > 0)
                <button
                    wire:click="exportToCsv"
                    class="inline-flex items-center px-4 py-2 text-sm font-medium text-primary-600 bg-primary-50 rounded-lg hover:bg-primary-100 dark:bg-primary-900/20 dark:text-primary-400 dark:hover:bg-primary-900/40">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export CSV
                </button>
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
                <p class="mt-4 text-gray-600 dark:text-gray-400">Loading customer engagement data...</p>
            </div>
        @endif

        {{-- Customer Engagement Table --}}
        @if(!$loading && !$error && $brandId)
            <x-filament::section>
                <x-slot name="heading">
                    Customer Engagement Metrics
                </x-slot>
                <x-slot name="description">
                    @if($this->hasActiveFilters())
                        Showing {{ count($engagementData) }} of {{ count($allEngagementData) }} products (filtered)
                    @else
                        Analyze customer behavior patterns for each product in your catalog
                    @endif
                </x-slot>

                @if(count($engagementData) > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    {{-- SKU Column --}}
                                    <th class="px-4 py-3 whitespace-nowrap">
                                        <button
                                            wire:click="sortBy('sku')"
                                            class="flex items-center gap-1 font-semibold text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                                            SKU
                                            <span class="{{ $this->getSortIconClass('sku') }}">{{ $this->getSortIcon('sku') }}</span>
                                        </button>
                                    </th>

                                    {{-- Name Column --}}
                                    <th class="px-4 py-3">
                                        <button
                                            wire:click="sortBy('name')"
                                            class="flex items-center gap-1 font-semibold text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                                            Product Name
                                            <span class="{{ $this->getSortIconClass('name') }}">{{ $this->getSortIcon('name') }}</span>
                                        </button>
                                    </th>

                                    {{-- Avg Qty per Order --}}
                                    @php $metric = $this->getMetricDefinitions()['avg_qty_per_order']; @endphp
                                    <th class="px-4 py-3 text-right whitespace-nowrap">
                                        <button
                                            wire:click="sortBy('avg_qty_per_order')"
                                            class="flex items-center gap-1 font-semibold text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 ml-auto"
                                            title="{{ $metric['description'] }}">
                                            {{ $metric['title'] }}
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span class="{{ $this->getSortIconClass('avg_qty_per_order') }}">{{ $this->getSortIcon('avg_qty_per_order') }}</span>
                                        </button>
                                    </th>

                                    {{-- Reorder Rate --}}
                                    @php $metric = $this->getMetricDefinitions()['reorder_rate']; @endphp
                                    <th class="px-4 py-3 text-right whitespace-nowrap">
                                        <button
                                            wire:click="sortBy('reorder_rate')"
                                            class="flex items-center gap-1 font-semibold text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 ml-auto"
                                            title="{{ $metric['description'] }}">
                                            {{ $metric['title'] }}
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span class="{{ $this->getSortIconClass('reorder_rate') }}">{{ $this->getSortIcon('reorder_rate') }}</span>
                                        </button>
                                    </th>

                                    {{-- Avg Frequency --}}
                                    @php $metric = $this->getMetricDefinitions()['avg_frequency_months']; @endphp
                                    <th class="px-4 py-3 text-right whitespace-nowrap">
                                        <button
                                            wire:click="sortBy('avg_frequency_months')"
                                            class="flex items-center gap-1 font-semibold text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 ml-auto"
                                            title="{{ $metric['description'] }}">
                                            {{ $metric['title'] }}
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span class="{{ $this->getSortIconClass('avg_frequency_months') }}">{{ $this->getSortIcon('avg_frequency_months') }}</span>
                                        </button>
                                    </th>

                                    {{-- Promo Intensity --}}
                                    @php $metric = $this->getMetricDefinitions()['promo_intensity']; @endphp
                                    <th class="px-4 py-3 text-right whitespace-nowrap">
                                        <button
                                            wire:click="sortBy('promo_intensity')"
                                            class="flex items-center gap-1 font-semibold text-gray-600 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 ml-auto"
                                            title="{{ $metric['description'] }}">
                                            {{ $metric['title'] }}
                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span class="{{ $this->getSortIconClass('promo_intensity') }}">{{ $this->getSortIcon('promo_intensity') }}</span>
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($engagementData as $product)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">
                                            {{ $product['sku'] }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-white">
                                            {{ $product['name'] }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                            {{ $this->formatMetric('avg_qty_per_order', $product['avg_qty_per_order']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium {{ $this->getReorderRateColor($product['reorder_rate']) }}">
                                            {{ $this->formatMetric('reorder_rate', $product['reorder_rate']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                            {{ $this->formatMetric('avg_frequency_months', $product['avg_frequency_months']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium {{ $this->getPromoIntensityColor($product['promo_intensity']) }}">
                                            {{ $this->formatMetric('promo_intensity', $product['promo_intensity']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Metric Legend --}}
                    <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Metric Guide</h4>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach($this->getMetricDefinitions() as $key => $metric)
                                <div class="text-xs">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $metric['title'] }}:</span>
                                    <span class="text-gray-500 dark:text-gray-400">{{ $metric['description'] }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Color Legend --}}
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <h5 class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">Color Indicators</h5>
                            <div class="flex flex-wrap gap-4 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block w-3 h-3 rounded-full bg-green-500"></span>
                                    <span class="text-gray-600 dark:text-gray-400">Good (Reorder >30% or Promo <20%)</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-block w-3 h-3 rounded-full bg-yellow-500"></span>
                                    <span class="text-gray-600 dark:text-gray-400">Moderate (Reorder 15-30% or Promo 20-50%)</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="inline-block w-3 h-3 rounded-full bg-red-500"></span>
                                    <span class="text-gray-600 dark:text-gray-400">Needs Attention (Reorder <15% or Promo >50%)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="py-6 text-center text-gray-500 dark:text-gray-400">
                        <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <p class="mt-2 text-sm">No customer engagement data available for this brand</p>
                    </div>
                @endif
            </x-filament::section>

            {{-- Customer Demographics - Coming Soon --}}
            <x-filament::section class="mt-6">
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <svg width="20" height="20" class="w-5 h-5 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Customer Demographics
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                            Coming Soon
                        </span>
                    </div>
                </x-slot>

                <div class="py-8 text-center">
                    <div class="mx-auto w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-4">
                        <svg width="32" height="32" class="w-8 h-8 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                        Customer Insights Coming Soon
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-4">
                        We're working on bringing you detailed customer demographics including age distribution,
                        geographic breakdown, and purchase frequency patterns.
                    </p>
                    <div class="flex flex-wrap justify-center gap-3 text-xs text-gray-400 dark:text-gray-500">
                        <span class="inline-flex items-center gap-1">
                            <svg width="16" height="16" class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Geographic Distribution
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <svg width="16" height="16" class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Purchase Frequency
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <svg width="16" height="16" class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Customer Segments
                        </span>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- No Brand Selected State --}}
        @if(!$loading && !$error && !$brandId)
            <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
                <p class="text-gray-600 dark:text-gray-400">Please select a brand to view customer engagement data</p>
            </div>
        @endif
    @endunless
</x-filament-panels::page>
