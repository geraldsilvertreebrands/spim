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
                <p class="mt-4 text-gray-600 dark:text-gray-400">Loading price comparison matrix...</p>
            </div>
        @endif

        {{-- Matrix Content --}}
        @if(!$loading && !$error)
            {{-- Controls Section --}}
            <x-filament::section class="mb-6">
                <div class="pricing-controls flex flex-col gap-4">
                    {{-- Category Filter --}}
                    <div class="w-full">
                        <label for="category-select" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Filter by Category
                        </label>
                        <select
                            id="category-select"
                            wire:model.live="selectedCategory"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white min-h-[44px]"
                        >
                            @foreach($this->getCategories() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Sort Options and Refresh --}}
                    <div class="flex flex-wrap gap-2 items-end">
                        <x-filament::button
                            wire:click="updateSort('name', 'asc')"
                            color="{{ $sortBy === 'name' && $sortDirection === 'asc' ? 'primary' : 'gray' }}"
                            size="sm"
                            class="min-h-[44px]"
                        >
                            Sort A-Z
                        </x-filament::button>

                        <x-filament::button
                            wire:click="updateSort('our_price', 'asc')"
                            color="{{ $sortBy === 'our_price' ? 'primary' : 'gray' }}"
                            size="sm"
                            class="min-h-[44px]"
                        >
                            Sort by Price
                        </x-filament::button>

                        <x-filament::button wire:click="refresh" icon="heroicon-o-arrow-path" color="gray" size="sm" class="min-h-[44px]">
                            Refresh
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>

            {{-- Legend --}}
            <x-filament::section class="mb-6">
                <x-slot name="heading">
                    Color Legend
                </x-slot>

                <div class="p-2 sm:p-4">
                    <div class="pricing-legend flex flex-wrap gap-3 sm:gap-6">
                    <div class="flex items-center gap-2">
                        <div class="h-8 w-8 rounded bg-green-100 dark:bg-green-900/30 border border-green-300 dark:border-green-700"></div>
                        <span class="text-sm text-gray-700 dark:text-gray-300">We're cheapest</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="h-8 w-8 rounded bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-700"></div>
                        <span class="text-sm text-gray-700 dark:text-gray-300">Mid-range price</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="h-8 w-8 rounded bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700"></div>
                        <span class="text-sm text-gray-700 dark:text-gray-300">We're most expensive</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="h-8 w-8 rounded bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-600"></div>
                        <span class="text-sm text-gray-700 dark:text-gray-300">No competitor data</span>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Price Comparison Matrix Table --}}
        @if(empty($matrixData))
            <x-filament::section>
                <div class="py-8 text-center">
                    <x-heroicon-o-table-cells class="mx-auto h-8 w-8 text-gray-400" />
                    <h3 class="mt-3 text-sm font-medium text-gray-900 dark:text-white">No Price Data Available</h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        No products with competitor price data found.
                    </p>
                </div>
            </x-filament::section>
            @else
                <x-filament::section>
                    <x-slot name="heading">
                        Price Comparison Matrix
                    </x-slot>
                    <x-slot name="description">
                        <span class="hidden sm:inline">Compare your prices against competitors. Color coding shows where you're most competitive.</span>
                        <span class="sm:hidden">Price vs competitors matrix</span>
                    </x-slot>

                    {{-- Mobile scroll hint --}}
                    <p class="pricing-table-scroll-hint sm:hidden text-center text-xs text-gray-500 mb-2">
                        <span class="scroll-indicator inline-block">&larr;</span>
                        Scroll horizontally to see more
                        <span class="scroll-indicator inline-block">&rarr;</span>
                    </p>

                    <div class="pricing-table-container overflow-x-auto -mx-4 sm:mx-0">
                        <table class="pricing-table w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs sm:text-sm min-w-[600px]">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-800">
                                    <th class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-800 px-2 sm:px-4 py-2 sm:py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">
                                        Product
                                    </th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-right text-xs font-medium uppercase tracking-wider text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/20">
                                        Ours
                                    </th>
                                    @foreach($competitors as $competitor)
                                        <th class="px-2 sm:px-4 py-2 sm:py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            {{ $competitor }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                @foreach($matrixData as $row)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        {{-- Product Name (Sticky) --}}
                                        <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 px-2 sm:px-4 py-2 sm:py-3 font-medium text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-700">
                                            <div class="max-w-[100px] sm:max-w-xs truncate" title="{{ $row['name'] }}">
                                                {{ $row['name'] }}
                                            </div>
                                            @if($row['category'] !== 'Uncategorized')
                                                <div class="text-xs text-gray-500 dark:text-gray-400 hidden sm:block">
                                                    {{ $row['category'] }}
                                                </div>
                                            @endif
                                        </td>

                                        {{-- Our Price --}}
                                        <td class="px-2 sm:px-4 py-2 sm:py-3 text-right whitespace-nowrap {{ $this->getOurPriceCellColorClass($row['position']) }}">
                                            <span class="font-semibold text-indigo-900 dark:text-indigo-100">
                                                {{ $this->formatPrice($row['our_price']) }}
                                            </span>
                                        </td>

                                        {{-- Competitor Prices --}}
                                        @foreach($competitors as $competitor)
                                            @php
                                                $competitorPrice = $row['competitor_prices'][$competitor] ?? null;
                                                $cellClass = $this->getCellColorClass($row['our_price'], $competitorPrice);
                                                $priceDiff = $this->getPriceDifference($row['our_price'], $competitorPrice);
                                            @endphp
                                            <td
                                                class="px-2 sm:px-4 py-2 sm:py-3 text-right whitespace-nowrap {{ $cellClass }}"
                                                title="{{ $priceDiff }}"
                                            >
                                                <span class="text-gray-900 dark:text-gray-100">
                                                    {{ $this->formatPrice($competitorPrice) }}
                                                </span>
                                                @if($competitorPrice !== null)
                                                    <div class="text-xs text-gray-600 dark:text-gray-400 hidden sm:block">
                                                        @if($row['our_price'] < $competitorPrice)
                                                            <span class="text-green-600 dark:text-green-400">
                                                                -R{{ number_format($competitorPrice - $row['our_price'], 2) }}
                                                            </span>
                                                        @elseif($row['our_price'] > $competitorPrice)
                                                            <span class="text-red-600 dark:text-red-400">
                                                                +R{{ number_format($row['our_price'] - $competitorPrice, 2) }}
                                                            </span>
                                                        @else
                                                            <span class="text-gray-500">
                                                                Same
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Summary Stats --}}
                    <div class="pricing-matrix-summary mt-6 grid gap-3 sm:gap-4 grid-cols-1 sm:grid-cols-3 border-t border-gray-200 dark:border-gray-700 pt-6">
                    @php
                        $cheapestCount = collect($matrixData)->where('position', 'cheapest')->count();
                        $middleCount = collect($matrixData)->where('position', 'middle')->count();
                        $expensiveCount = collect($matrixData)->where('position', 'most_expensive')->count();
                        $totalProducts = count($matrixData);
                    @endphp

                    <div class="text-center p-4 rounded-lg bg-green-50 dark:bg-green-900/20">
                        <div class="text-3xl font-bold text-green-700 dark:text-green-300">
                            {{ $cheapestCount }}
                        </div>
                        <div class="text-sm text-green-600 dark:text-green-400 mt-1">
                            Products Where We're Cheapest
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                            {{ $totalProducts > 0 ? round(($cheapestCount / $totalProducts) * 100, 1) : 0 }}% of products
                        </div>
                    </div>

                    <div class="text-center p-4 rounded-lg bg-yellow-50 dark:bg-yellow-900/20">
                        <div class="text-3xl font-bold text-yellow-700 dark:text-yellow-300">
                            {{ $middleCount }}
                        </div>
                        <div class="text-sm text-yellow-600 dark:text-yellow-400 mt-1">
                            Products Mid-Range
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                            {{ $totalProducts > 0 ? round(($middleCount / $totalProducts) * 100, 1) : 0 }}% of products
                        </div>
                    </div>

                    <div class="text-center p-4 rounded-lg bg-red-50 dark:bg-red-900/20">
                        <div class="text-3xl font-bold text-red-700 dark:text-red-300">
                            {{ $expensiveCount }}
                        </div>
                        <div class="text-sm text-red-600 dark:text-red-400 mt-1">
                            Products Where We're Most Expensive
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                            {{ $totalProducts > 0 ? round(($expensiveCount / $totalProducts) * 100, 1) : 0 }}% of products
                        </div>
                    </div>
                    </div>
                </x-filament::section>
            @endif
        @endif
    </div>
</x-filament-panels::page>
