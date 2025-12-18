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
            <p class="mt-4 text-gray-600 dark:text-gray-400">Loading supply chain data...</p>
        </div>
    @endif

    {{-- Supply Chain Tables --}}
    @if(!$loading && !$error && $brandId)
        {{-- Sell-In Table --}}
        <x-filament::section class="mb-6">
            <x-slot name="heading">
                <div class="flex items-center justify-between">
                    <span>Sell-In (Units Received)</span>
                    @if(count($sellInData) > 0)
                        <button
                            wire:click="exportSellInToCsv"
                            class="inline-flex items-center px-3 py-1 text-xs font-medium text-primary-600 bg-primary-50 rounded-lg hover:bg-primary-100 dark:bg-primary-900/20 dark:text-primary-400 dark:hover:bg-primary-900/40">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Export
                        </button>
                    @endif
                </div>
            </x-slot>
            <x-slot name="description">
                Units received from supplier per month
            </x-slot>

            @include('filament.supply-panel.pages.partials.supply-table', [
                'data' => $sellInData,
                'months' => $months,
                'emptyMessage' => 'No sell-in data available'
            ])
        </x-filament::section>

        {{-- Sell-Out Table --}}
        <x-filament::section class="mb-6">
            <x-slot name="heading">
                <div class="flex items-center justify-between">
                    <span>Sell-Out (Units Sold)</span>
                    @if(count($sellOutData) > 0)
                        <button
                            wire:click="exportSellOutToCsv"
                            class="inline-flex items-center px-3 py-1 text-xs font-medium text-primary-600 bg-primary-50 rounded-lg hover:bg-primary-100 dark:bg-primary-900/20 dark:text-primary-400 dark:hover:bg-primary-900/40">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Export
                        </button>
                    @endif
                </div>
            </x-slot>
            <x-slot name="description">
                Units sold per month
            </x-slot>

            @include('filament.supply-panel.pages.partials.supply-table', [
                'data' => $sellOutData,
                'months' => $months,
                'emptyMessage' => 'No sell-out data available'
            ])
        </x-filament::section>

        {{-- Closing Stock Table --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between">
                    <span>Closing Stock</span>
                    @if(count($closingStockData) > 0)
                        <button
                            wire:click="exportClosingStockToCsv"
                            class="inline-flex items-center px-3 py-1 text-xs font-medium text-primary-600 bg-primary-50 rounded-lg hover:bg-primary-100 dark:bg-primary-900/20 dark:text-primary-400 dark:hover:bg-primary-900/40">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Export
                        </button>
                    @endif
                </div>
            </x-slot>
            <x-slot name="description">
                End-of-month inventory levels
            </x-slot>

            @include('filament.supply-panel.pages.partials.supply-table', [
                'data' => $closingStockData,
                'months' => $months,
                'emptyMessage' => 'No closing stock data available'
            ])
        </x-filament::section>
    @endif

    {{-- No Brand Selected State --}}
    @if(!$loading && !$error && !$brandId)
        <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
            <p class="text-gray-600 dark:text-gray-400">Please select a brand to view supply chain data</p>
        </div>
    @endif
</x-filament-panels::page>
