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
                <p class="mt-4 text-gray-600 dark:text-gray-400">Loading competitor prices...</p>
            </div>
        @endif

        {{-- Competitor Prices Content --}}
        @if(!$loading && !$error)
            {{-- Filters and Controls --}}
            <div class="mb-6">
                <x-filament::section>
                    <div class="pricing-controls flex flex-col gap-4">
                        {{-- Category Filter --}}
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <label for="category-filter" class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                Category:
                            </label>
                            <select
                                id="category-filter"
                                wire:model.live="selectedCategory"
                                wire:change="updateCategory($event.target.value)"
                                class="w-full sm:w-auto rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                @foreach($this->getCategories() as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Sort Controls --}}
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <label class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                Sort by:
                            </label>
                            <div class="pricing-sort-buttons flex flex-wrap gap-2">
                                <button
                                    wire:click="updateSort('name', 'asc')"
                                    class="rounded px-3 py-2 text-sm min-h-[44px] {{ $sortBy === 'name' ? 'bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}"
                                >
                                    Name
                                </button>
                                <button
                                    wire:click="updateSort('our_price', 'asc')"
                                    class="rounded px-3 py-2 text-sm min-h-[44px] {{ $sortBy === 'our_price' ? 'bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}"
                                >
                                    Our Price
                                </button>
                                <button
                                    wire:click="updateSort('price_difference', 'desc')"
                                    class="rounded px-3 py-2 text-sm min-h-[44px] {{ $sortBy === 'price_difference' ? 'bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}"
                                >
                                    Price Diff
                                </button>

                                {{-- Refresh Button --}}
                                <button
                                    wire:click="refresh"
                                    class="rounded bg-gray-100 px-3 py-2 text-sm text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 min-h-[44px]"
                                    title="Refresh data"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </x-filament::section>
            </div>

            {{-- Summary Stats --}}
            <div class="pricing-secondary-stats mb-6 grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-3">
            <x-filament::section class="text-center">
                <div class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Total Products
                </div>
                <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                    {{ count($productPrices) }}
                </div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Competitors
                </div>
                <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                    {{ count($competitors) }}
                </div>
            </x-filament::section>

            <x-filament::section class="text-center">
                <div class="text-sm font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    We're More Expensive
                </div>
                <div class="mt-2 text-2xl font-bold text-red-600 dark:text-red-400">
                    {{ collect($productPrices)->where('is_more_expensive', true)->count() }}
                </div>
            </x-filament::section>
        </div>

            {{-- Price Comparison Table --}}
            <x-filament::section>
                @if(count($productPrices) === 0)
                    <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                        <p>No competitor pricing data available.</p>
                        <p class="mt-2 text-sm">Import price scrapes to see competitor comparisons.</p>
                    </div>
                @else
                    {{-- Mobile scroll hint --}}
                    <p class="pricing-table-scroll-hint sm:hidden text-center text-xs text-gray-500 mb-2">
                        <span class="scroll-indicator inline-block">&larr;</span>
                        Scroll horizontally to see more
                        <span class="scroll-indicator inline-block">&rarr;</span>
                    </p>

                    <div class="pricing-table-container overflow-x-auto -mx-4 sm:mx-0">
                        <table class="pricing-table w-full divide-y divide-gray-200 dark:divide-gray-700 min-w-[600px]">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th scope="col" class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Product
                                    </th>
                                    <th scope="col" class="px-3 sm:px-6 py-2 sm:py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Our Price
                                    </th>
                                    @foreach($competitors as $competitor)
                                        <th scope="col" class="px-3 sm:px-6 py-2 sm:py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            {{ $competitor }}
                                        </th>
                                    @endforeach
                                    <th scope="col" class="px-3 sm:px-6 py-2 sm:py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Position
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                @foreach($productPrices as $product)
                                    <tr class="{{ $product['is_more_expensive'] ? 'bg-red-50 dark:bg-red-900/10' : '' }}">
                                        {{-- Product Name --}}
                                        <td class="px-3 sm:px-6 py-2 sm:py-4">
                                            <div class="text-xs sm:text-sm font-medium text-gray-900 dark:text-white max-w-[120px] sm:max-w-none truncate">
                                                {{ $product['name'] }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 hidden sm:block">
                                                {{ $product['category'] }}
                                            </div>
                                        </td>

                                        {{-- Our Price --}}
                                        <td class="whitespace-nowrap px-3 sm:px-6 py-2 sm:py-4 text-right">
                                            <div class="text-xs sm:text-sm font-bold text-gray-900 dark:text-white">
                                                {{ $this->formatPrice($product['our_price']) }}
                                            </div>
                                            @if($product['price_difference'] != 0)
                                                <div class="text-xs {{ $product['is_more_expensive'] ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                                    {{ $product['is_more_expensive'] ? '+' : '' }}{{ $this->formatPrice($product['price_difference']) }}
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Competitor Prices --}}
                                        @foreach($competitors as $competitor)
                                            <td class="whitespace-nowrap px-3 sm:px-6 py-2 sm:py-4 text-right">
                                                @php
                                                    $price = $product['competitor_prices'][$competitor] ?? null;
                                                @endphp
                                                @if($price !== null)
                                                    <div class="text-xs sm:text-sm text-gray-700 dark:text-gray-300">
                                                        {{ $this->formatPrice($price) }}
                                                    </div>
                                                @else
                                                    <div class="text-xs sm:text-sm text-gray-400 dark:text-gray-600">
                                                        -
                                                    </div>
                                                @endif
                                            </td>
                                        @endforeach

                                        {{-- Position Badge --}}
                                        <td class="whitespace-nowrap px-3 sm:px-6 py-2 sm:py-4 text-center">
                                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $this->getPositionBadgeClass($product['position']) }}">
                                                {{ $this->getPositionLabel($product['position']) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
