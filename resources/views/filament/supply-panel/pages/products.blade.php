<x-filament-panels::page>
    @push('styles')
        <style>
            .compact-table {
                font-size: 11px;
                border-collapse: collapse;
            }
            .compact-table th,
            .compact-table td {
                padding: 4px 6px;
                white-space: nowrap;
            }
            .compact-table th {
                font-size: 10px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.025em;
            }
            .compact-table .sticky-col {
                position: sticky;
                background: inherit;
            }
            .compact-table .col-sku { left: 0; min-width: 70px; max-width: 70px; z-index: 20; }
            .compact-table .col-name { left: 70px; min-width: 120px; max-width: 120px; z-index: 20; }
            .compact-table .col-cat { min-width: 80px; max-width: 80px; }
            .compact-table .col-month { min-width: 50px; text-align: right; }
            .compact-table .col-total { min-width: 60px; text-align: right; font-weight: 700; }
            .compact-table tbody tr:hover { background-color: #f0f9ff; }
            .dark .compact-table tbody tr:hover { background-color: #1e293b; }
        </style>
    @endpush

    {{-- Filters --}}
    <div class="mb-4 flex flex-wrap gap-3 items-end">
        @if(count($this->getAvailableBrands()) > 1)
            <div class="w-48">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Brand</label>
                <select wire:model.live="brandId" class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5">
                    @foreach($this->getAvailableBrands() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="w-40">
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Time Period</label>
            <select wire:model.live="period" class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5">
                @foreach($this->getPeriodOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @if(!$loading && count($categories) > 0)
            <div class="w-48">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Category</label>
                <select wire:model.live="categoryFilter" class="w-full text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white py-1.5">
                    @foreach($this->getCategoryOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        @if(!$loading && !$error && count($products) > 0)
            <div class="flex gap-2 ml-auto">
                <button wire:click="exportToCsv" class="px-3 py-1.5 text-xs font-medium bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded">
                    CSV
                </button>
                <button onclick="window.print()" class="px-3 py-1.5 text-xs font-medium bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded">
                    Print
                </button>
            </div>
        @endif
    </div>

    {{-- Error --}}
    @if($error)
        <div class="mb-4 p-3 text-sm bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400 rounded">
            {{ $error }}
        </div>
    @endif

    {{-- Loading --}}
    @if($loading)
        <div class="p-8 text-center">
            <div class="inline-block h-6 w-6 animate-spin rounded-full border-2 border-primary-600 border-r-transparent"></div>
            <p class="mt-2 text-sm text-gray-500">Loading...</p>
        </div>
    @endif

    {{-- Table --}}
    @if(!$loading && !$error && $brandId)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Product Revenue by Month</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    @if($categoryFilter)
                        {{ count($products) }} of {{ count($allProducts) }} products (filtered)
                    @else
                        {{ count($products) }} products found
                    @endif
                </p>
            </div>

            <div class="overflow-x-auto" style="max-height: 70vh;">
                <table class="compact-table w-full">
                    <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0 z-30">
                        <tr class="border-b border-gray-300 dark:border-gray-600">
                            <th class="sticky-col col-sku bg-gray-50 dark:bg-gray-900 text-left text-gray-600 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700">SKU</th>
                            <th class="sticky-col col-name bg-gray-50 dark:bg-gray-900 text-left text-gray-600 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700">Name</th>
                            <th class="col-cat text-left text-gray-600 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700">Category</th>
                            @foreach($months as $month)
                                <th class="col-month text-gray-600 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700">
                                    {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('M\'y') }}
                                </th>
                            @endforeach
                            <th class="col-total text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-800">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800">
                        @forelse($products as $product)
                            <tr class="border-b border-gray-100 dark:border-gray-700">
                                <td class="sticky-col col-sku bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 font-mono border-r border-gray-100 dark:border-gray-700 truncate" title="{{ $product['sku'] ?? '' }}">
                                    {{ Str::limit($product['sku'] ?? '-', 10) }}
                                </td>
                                <td class="sticky-col col-name bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-medium border-r border-gray-100 dark:border-gray-700 truncate" title="{{ $product['name'] ?? '' }}">
                                    {{ Str::limit($product['name'] ?? '-', 18) }}
                                </td>
                                <td class="col-cat text-gray-500 dark:text-gray-400 border-r border-gray-100 dark:border-gray-700 truncate" title="{{ $product['category'] ?? '' }}">
                                    @php
                                        $cat = $product['category'] ?? '-';
                                        $parts = explode('/', $cat);
                                        $shortCat = count($parts) > 1 ? end($parts) : $cat;
                                    @endphp
                                    {{ Str::limit($shortCat, 12) }}
                                </td>
                                @foreach($months as $month)
                                    <td class="col-month text-gray-700 dark:text-gray-300 tabular-nums border-r border-gray-100 dark:border-gray-700">
                                        @if(isset($product['months'][$month]) && $product['months'][$month] > 0)
                                            @php $val = $product['months'][$month]; @endphp
                                            @if($val >= 1000000)
                                                R{{ number_format($val / 1000000, 1) }}M
                                            @elseif($val >= 1000)
                                                R{{ number_format($val / 1000, 0) }}k
                                            @else
                                                R{{ number_format($val, 0) }}
                                            @endif
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600">-</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="col-total text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 tabular-nums">
                                    @php $total = $product['total'] ?? 0; @endphp
                                    @if($total >= 1000000)
                                        R{{ number_format($total / 1000000, 1) }}M
                                    @elseif($total >= 1000)
                                        R{{ number_format($total / 1000, 0) }}k
                                    @else
                                        R{{ number_format($total, 0) }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 4 + count($months) }}" class="p-8 text-center text-gray-500">
                                    No products found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if(!$loading && !$error && !$brandId)
        <div class="p-8 text-center bg-gray-50 dark:bg-gray-800 rounded-lg">
            <p class="text-gray-500">Select a brand to view products</p>
        </div>
    @endif
</x-filament-panels::page>
