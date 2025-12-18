<x-filament-panels::page>
    {{-- Premium + Pet Heaven Feature Gate --}}
    @unless((auth()->user()->hasRole('supplier-premium') || auth()->user()->hasRole('admin')) && count($this->getPetHeavenBrands()) > 0)
        <x-premium-gate feature="Subscription Products">
            <p class="text-gray-600 dark:text-gray-400">
                Subscription Products is a Pet Heaven Premium feature. View which products have the most active subscriptions,
                revenue contribution, and churn rates.
            </p>
        </x-premium-gate>
    @else
        {{-- Filters Row --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                {{-- Brand Selector --}}
                @if(count($this->getPetHeavenBrands()) > 1)
                    <div class="w-full sm:w-64">
                        <label for="brandSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Pet Heaven Brand
                        </label>
                        <select
                            wire:model.live="brandId"
                            id="brandSelect"
                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            @foreach($this->getPetHeavenBrands() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Time Period Selector --}}
                <div class="w-full sm:w-48">
                    <label for="monthsBackSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Time Period
                    </label>
                    <select
                        wire:model.live="monthsBack"
                        id="monthsBackSelect"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        @foreach($this->getMonthsBackOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
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
                <p class="mt-4 text-gray-600 dark:text-gray-400">Loading subscription products...</p>
            </div>
        @endif

        {{-- Products Content --}}
        @if(!$loading && !$error && $brandId && $this->hasProducts())
            {{-- Summary KPIs --}}
            <div class="mb-6 grid gap-4 sm:grid-cols-4">
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Products with Subscriptions</div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                        {{ count($products) }}
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Active Subscriptions</div>
                    <div class="mt-2 text-2xl font-bold text-green-600 dark:text-green-400">
                        {{ $this->formatNumber($totals['active_subscriptions'] ?? 0) }}
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total MRR</div>
                    <div class="mt-2 text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ $this->formatCurrency($totals['mrr'] ?? 0) }}
                    </div>
                </div>

                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Unique Subscribers</div>
                    <div class="mt-2 text-2xl font-bold text-purple-600 dark:text-purple-400">
                        {{ $this->formatNumber($totals['subscribers'] ?? 0) }}
                    </div>
                </div>
            </div>

            {{-- Products Table --}}
            <x-filament::section>
                <x-slot name="heading">
                    Subscription Products
                </x-slot>
                <x-slot name="description">
                    Click column headers to sort
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">
                                    <button wire:click="sortBy('product_name')" class="flex items-center gap-1 hover:text-primary-600">
                                        Product {{ $this->getSortIcon('product_name') }}
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">SKU</th>
                                <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Category</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">
                                    <button wire:click="sortBy('active_subscriptions')" class="flex items-center gap-1 ml-auto hover:text-primary-600">
                                        Active {{ $this->getSortIcon('active_subscriptions') }}
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">
                                    <button wire:click="sortBy('mrr')" class="flex items-center gap-1 ml-auto hover:text-primary-600">
                                        MRR {{ $this->getSortIcon('mrr') }}
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">
                                    <button wire:click="sortBy('subscribers')" class="flex items-center gap-1 ml-auto hover:text-primary-600">
                                        Subscribers {{ $this->getSortIcon('subscribers') }}
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">
                                    <button wire:click="sortBy('avg_ltv')" class="flex items-center gap-1 ml-auto hover:text-primary-600">
                                        Avg LTV {{ $this->getSortIcon('avg_ltv') }}
                                    </button>
                                </th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">
                                    <button wire:click="sortBy('churn_rate')" class="flex items-center gap-1 ml-auto hover:text-primary-600">
                                        Churn {{ $this->getSortIcon('churn_rate') }}
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($products as $product)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $product['product_name'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400 font-mono text-xs">
                                        {{ $product['sku'] }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                        {{ $product['category'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="font-medium text-green-600 dark:text-green-400">
                                            {{ $product['active_subscriptions'] }}
                                        </span>
                                        <span class="text-xs text-gray-400">
                                            / {{ $product['total_subscriptions'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                        {{ $this->formatCurrency($product['mrr']) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                                        {{ $this->formatNumber($product['subscribers']) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-purple-600 dark:text-purple-400">
                                        {{ $this->formatCurrency($product['avg_ltv']) }}
                                    </td>
                                    <td class="px-4 py-3 text-right {{ $this->getChurnColorClass($product['churn_rate']) }}">
                                        {{ $this->formatPercent($product['churn_rate']) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        {{-- No Data State --}}
        @if(!$loading && !$error && $brandId && !$this->hasProducts())
            <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
                <p class="text-gray-600 dark:text-gray-400">No subscription products found for this brand</p>
            </div>
        @endif

        {{-- No Brand Selected --}}
        @if(!$loading && !$error && !$brandId)
            <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
                <p class="text-gray-600 dark:text-gray-400">Please select a Pet Heaven brand to view subscription products</p>
            </div>
        @endif
    @endunless
</x-filament-panels::page>
