<x-filament-panels::page>
    <div class="pricing-panel space-y-6">
        {{-- Summary Statistics --}}
        @php
            $stats = $this->getSummaryStats();
        @endphp

        <div class="pricing-margin-stats grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Total Products
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                        {{ $stats['total_products'] }}
                    </dd>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Avg Margin %
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                        {{ $this->formatPercent($stats['avg_margin_percent']) }}
                    </dd>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Total Margin
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                        {{ $this->formatCurrency($stats['total_margin_amount']) }}
                    </dd>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Lowest Margin %
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-red-600">
                        {{ $this->formatPercent($stats['lowest_margin_percent']) }}
                    </dd>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                        Highest Margin %
                    </dt>
                    <dd class="mt-1 text-3xl font-semibold text-green-600">
                        {{ $this->formatPercent($stats['highest_margin_percent']) }}
                    </dd>
                </div>
            </div>
        </div>

        {{-- Filters and Controls --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-3 sm:p-4">
            <div class="pricing-controls flex flex-col gap-4">
                {{-- Category Filter --}}
                <div class="w-full">
                    <label for="category-filter" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Filter by Category
                    </label>
                    <select
                        id="category-filter"
                        wire:model.live="categoryFilter"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm min-h-[44px]"
                    >
                        <option value="">All Categories</option>
                        @foreach ($this->getCategories() as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Sort Controls --}}
                <div class="pricing-sort-buttons flex flex-wrap gap-2">
                    <button
                        wire:click="updateSort('margin_percent')"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 min-h-[44px]"
                    >
                        Margin %
                        @if ($sortBy === 'margin_percent')
                            <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>

                    <button
                        wire:click="updateSort('margin_amount')"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 min-h-[44px]"
                    >
                        Margin R
                        @if ($sortBy === 'margin_amount')
                            <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </button>

                    {{-- Clear Filters --}}
                    @if ($categoryFilter)
                        <button
                            wire:click="clearFilters"
                            class="inline-flex items-center px-3 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 min-h-[44px]"
                        >
                            Clear
                        </button>
                    @endif

                    {{-- Refresh Button --}}
                    <button
                        wire:click="refresh"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 min-h-[44px]"
                    >
                        <svg class="h-4 w-4 sm:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span class="hidden sm:inline">Refresh</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Margin Data Table --}}
        @php
            $marginData = $this->getMarginData();
        @endphp

        @if ($marginData->isEmpty())
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 text-center">
                <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No margin data available</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Products need both 'price' and 'cost' attributes to calculate margins.
                </p>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                {{-- Mobile scroll hint --}}
                <p class="pricing-table-scroll-hint sm:hidden text-center text-xs text-gray-500 py-2">
                    <span class="scroll-indicator inline-block">&larr;</span>
                    Scroll horizontally to see more
                    <span class="scroll-indicator inline-block">&rarr;</span>
                </p>

                <div class="pricing-table-container overflow-x-auto">
                    <table class="pricing-table min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col" class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer" wire:click="updateSort('name')">
                                    Product
                                    @if ($sortBy === 'name')
                                        <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </th>
                                <th scope="col" class="px-3 sm:px-6 py-2 sm:py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hidden sm:table-cell" wire:click="updateSort('cost')">
                                    Cost
                                    @if ($sortBy === 'cost')
                                        <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </th>
                                <th scope="col" class="px-3 sm:px-6 py-2 sm:py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer" wire:click="updateSort('price')">
                                    Price
                                    @if ($sortBy === 'price')
                                        <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </th>
                                <th scope="col" class="px-3 sm:px-6 py-2 sm:py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hidden sm:table-cell" wire:click="updateSort('margin_amount')">
                                    Margin R
                                    @if ($sortBy === 'margin_amount')
                                        <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </th>
                                <th scope="col" class="px-3 sm:px-6 py-2 sm:py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer" wire:click="updateSort('margin_percent')">
                                    Margin %
                                    @if ($sortBy === 'margin_percent')
                                        <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </th>
                                <th scope="col" class="px-3 sm:px-6 py-2 sm:py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hidden sm:table-cell">
                                    Competitors
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($marginData as $item)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-3 sm:px-6 py-2 sm:py-4 text-xs sm:text-sm font-medium text-gray-900 dark:text-white">
                                        <span class="block max-w-[120px] sm:max-w-none truncate">{{ $item['product_name'] }}</span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-2 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-right text-gray-500 dark:text-gray-400 hidden sm:table-cell">
                                        {{ $this->formatCurrency($item['cost']) }}
                                    </td>
                                    <td class="px-3 sm:px-6 py-2 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-right font-medium text-gray-900 dark:text-white">
                                        {{ $this->formatCurrency($item['our_price']) }}
                                    </td>
                                    <td class="px-3 sm:px-6 py-2 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-right font-medium text-gray-900 dark:text-white hidden sm:table-cell">
                                        {{ $this->formatCurrency($item['margin_amount']) }}
                                    </td>
                                    <td class="px-3 sm:px-6 py-2 sm:py-4 whitespace-nowrap text-right">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs sm:text-sm font-medium {{ $this->getMarginColorClass($item['margin_percent']) }}">
                                            {{ $this->formatPercent($item['margin_percent']) }}
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-2 sm:py-4 text-center hidden sm:table-cell">
                                        @if ($item['competitor_margins']->isNotEmpty())
                                            <button
                                                @click="$dispatch('toggle-competitors-{{ $item['product_id'] }}')"
                                                class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 text-xs sm:text-sm font-medium min-h-[44px]"
                                            >
                                                View {{ $item['competitor_margins']->count() }}
                                            </button>
                                        @else
                                            <span class="text-gray-400 dark:text-gray-500 text-xs sm:text-sm">No data</span>
                                        @endif
                                    </td>
                                </tr>

                                {{-- Competitor Margin Details (Expandable) --}}
                                @if ($item['competitor_margins']->isNotEmpty())
                                    <tr
                                        x-data="{ show: false }"
                                        x-on:toggle-competitors-{{ $item['product_id'] }}.window="show = !show"
                                        x-show="show"
                                        x-transition
                                        class="bg-gray-50 dark:bg-gray-900"
                                        style="display: none;"
                                    >
                                        <td colspan="6" class="px-6 py-4">
                                            <div class="text-sm">
                                                <h4 class="font-medium text-gray-900 dark:text-white mb-3">
                                                    Margin Analysis if Matched to Competitor Prices
                                                </h4>
                                                <div class="overflow-x-auto">
                                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                        <thead class="bg-gray-100 dark:bg-gray-800">
                                                            <tr>
                                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Competitor</th>
                                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Their Price</th>
                                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Price Diff</th>
                                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Our Margin If Matched</th>
                                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Margin % If Matched</th>
                                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Margin Change</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                                            @foreach ($item['competitor_margins'] as $compMargin)
                                                                <tr>
                                                                    <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">
                                                                        {{ $compMargin['competitor'] }}
                                                                    </td>
                                                                    <td class="px-4 py-2 text-sm text-right text-gray-700 dark:text-gray-300">
                                                                        {{ $this->formatCurrency($compMargin['competitor_price']) }}
                                                                    </td>
                                                                    <td class="px-4 py-2 text-sm text-right {{ $compMargin['price_difference'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                                                                        {{ $compMargin['price_difference'] > 0 ? '+' : '' }}{{ $this->formatCurrency($compMargin['price_difference']) }}
                                                                    </td>
                                                                    <td class="px-4 py-2 text-sm text-right text-gray-700 dark:text-gray-300">
                                                                        {{ $this->formatCurrency($compMargin['margin_if_matched']) }}
                                                                    </td>
                                                                    <td class="px-4 py-2 text-right">
                                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $this->getMarginColorClass($compMargin['margin_percent_if_matched']) }}">
                                                                            {{ $this->formatPercent($compMargin['margin_percent_if_matched']) }}
                                                                        </span>
                                                                    </td>
                                                                    <td class="px-4 py-2 text-right">
                                                                        @php
                                                                            $marginDiff = $compMargin['margin_percent_if_matched'] - $item['margin_percent'];
                                                                        @endphp
                                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $this->getCompetitorMarginColorClass($item['margin_percent'], $compMargin['margin_percent_if_matched']) }}">
                                                                            {{ $marginDiff > 0 ? '+' : '' }}{{ $this->formatPercent($marginDiff) }}
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Legend --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mt-4">
                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Margin Color Legend</h4>
                <div class="flex flex-wrap gap-4 text-sm">
                    <div class="flex items-center">
                        <span class="inline-block px-2 py-1 rounded text-green-600 bg-green-50 mr-2">High</span>
                        <span class="text-gray-600 dark:text-gray-400">≥ 30% margin</span>
                    </div>
                    <div class="flex items-center">
                        <span class="inline-block px-2 py-1 rounded text-yellow-600 bg-yellow-50 mr-2">Medium</span>
                        <span class="text-gray-600 dark:text-gray-400">15-30% margin</span>
                    </div>
                    <div class="flex items-center">
                        <span class="inline-block px-2 py-1 rounded text-red-600 bg-red-50 mr-2">Low</span>
                        <span class="text-gray-600 dark:text-gray-400">&lt; 15% margin</span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
