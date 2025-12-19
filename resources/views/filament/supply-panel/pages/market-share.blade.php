<x-filament-panels::page>
    {{-- Filters Row --}}
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

            {{-- Search --}}
            <div class="w-full sm:w-64">
                <label for="searchInput" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Search Categories
                </label>
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    id="searchInput"
                    placeholder="Search categories..."
                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400">
            </div>
        </div>

        {{-- Expand/Collapse Buttons --}}
        @if(!$loading && !$error && count($categoryTree) > 0)
            <div class="flex gap-2">
                <button
                    wire:click="expandAll"
                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                    <svg width="16" height="16" class="w-4 h-4 mr-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Expand All
                </button>
                <button
                    wire:click="collapseAll"
                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600">
                    <svg width="16" height="16" class="w-4 h-4 mr-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                    </svg>
                    Collapse All
                </button>
            </div>
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
            <p class="mt-4 text-gray-600 dark:text-gray-400">Loading market share data...</p>
        </div>
    @endif

    {{-- Market Share Tree --}}
    @if(!$loading && !$error && $brandId)
        <x-filament::section>
            <x-slot name="heading">
                Market Share by Category
            </x-slot>
            <x-slot name="description">
                Your brand's market share compared to competitors (anonymized)
            </x-slot>

            {{-- Legend --}}
            <div class="mb-4 flex flex-wrap gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded bg-primary-500"></div>
                    <span class="text-gray-700 dark:text-gray-300">Your Brand</span>
                </div>
                @foreach($competitorLabels as $label)
                    <div class="flex items-center gap-2">
                        <div class="w-4 h-4 rounded {{ $loop->index === 0 ? 'bg-blue-400' : ($loop->index === 1 ? 'bg-yellow-400' : 'bg-red-400') }}"></div>
                        <span class="text-gray-700 dark:text-gray-300">{{ $label }}</span>
                    </div>
                @endforeach
            </div>

            {{-- Tree Table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 w-1/3">
                                Category
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                Your Brand
                            </th>
                            @foreach($competitorLabels as $label)
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ $label }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($this->getFilteredTree() as $category => $data)
                            {{-- Parent Category Row --}}
                            <tr class="bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer"
                                wire:click="toggleCategory('{{ $category }}')"
                                wire:key="category-{{ $loop->index }}">
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                    <div class="flex items-center gap-2">
                                        @if(count($data['children']) > 0)
                                            <svg width="16" height="16" class="w-4 h-4 text-gray-400 transition-transform flex-shrink-0 {{ $this->isExpanded($category) ? 'rotate-90' : '' }}"
                                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        @else
                                            <span class="w-4"></span>
                                        @endif
                                        {{ $data['name'] }}
                                        @if(count($data['children']) > 0)
                                            <span class="text-xs text-gray-400">({{ count($data['children']) }})</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                                            <div class="bg-primary-500 h-2.5 rounded-full" style="width: {{ min($data['brand_share'], 100) }}%"></div>
                                        </div>
                                        <span class="w-12 text-right font-semibold text-gray-900 dark:text-white">{{ number_format($data['brand_share'], 1) }}%</span>
                                    </div>
                                </td>
                                @foreach($competitorLabels as $index => $label)
                                    @php
                                        $share = $data['competitor_shares'][$label] ?? 0;
                                        $barColor = $index === 0 ? 'bg-blue-400' : ($index === 1 ? 'bg-yellow-400' : 'bg-red-400');
                                    @endphp
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                                                <div class="{{ $barColor }} h-2.5 rounded-full" style="width: {{ min($share, 100) }}%"></div>
                                            </div>
                                            <span class="w-12 text-right text-gray-600 dark:text-gray-400">{{ number_format($share, 1) }}%</span>
                                        </div>
                                    </td>
                                @endforeach
                            </tr>

                            {{-- Subcategory Rows (expandable) --}}
                            @if($this->isExpanded($category) && count($data['children']) > 0)
                                @foreach($data['children'] as $subcategory => $childData)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30"
                                        wire:key="subcategory-{{ $loop->parent->index }}-{{ $loop->index }}">
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                            <div class="flex items-center gap-2 pl-8">
                                                <span class="w-4 text-gray-300 dark:text-gray-600">└</span>
                                                {{ $childData['name'] }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-2">
                                            <div class="flex items-center justify-end gap-2">
                                                <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                    <div class="bg-primary-400 h-2 rounded-full" style="width: {{ min($childData['brand_share'], 100) }}%"></div>
                                                </div>
                                                <span class="w-12 text-right text-gray-700 dark:text-gray-300">{{ number_format($childData['brand_share'], 1) }}%</span>
                                            </div>
                                        </td>
                                        @foreach($competitorLabels as $index => $label)
                                            @php
                                                $share = $childData['competitor_shares'][$label] ?? 0;
                                                $barColor = $index === 0 ? 'bg-blue-300' : ($index === 1 ? 'bg-yellow-300' : 'bg-red-300');
                                            @endphp
                                            <td class="px-4 py-2">
                                                <div class="flex items-center justify-end gap-2">
                                                    <div class="w-24 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                        <div class="{{ $barColor }} h-2 rounded-full" style="width: {{ min($share, 100) }}%"></div>
                                                    </div>
                                                    <span class="w-12 text-right text-gray-500 dark:text-gray-400">{{ number_format($share, 1) }}%</span>
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endif
                        @empty
                            <tr>
                                <td colspan="{{ 2 + count($competitorLabels) }}" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    @if($search)
                                        No categories match your search "{{ $search }}"
                                    @else
                                        No market share data available for this period
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Summary Stats --}}
            @if(count($this->getFilteredTree()) > 0)
                <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                        <div>
                            <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                                {{ count($categoryTree) }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Categories</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-700 dark:text-gray-300">
                                {{ collect($categoryTree)->sum(fn($cat) => count($cat['children'])) }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Subcategories</div>
                        </div>
                        <div>
                            @php
                                $avgBrandShare = count($categoryTree) > 0
                                    ? collect($categoryTree)->avg('brand_share')
                                    : 0;
                            @endphp
                            <div class="text-2xl font-bold text-primary-600 dark:text-primary-400">
                                {{ number_format($avgBrandShare, 1) }}%
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Avg Your Share</div>
                        </div>
                        <div>
                            @php
                                $leadingCategories = collect($categoryTree)->filter(function($cat) use ($competitorLabels) {
                                    $maxCompetitor = 0;
                                    foreach ($competitorLabels as $label) {
                                        $maxCompetitor = max($maxCompetitor, $cat['competitor_shares'][$label] ?? 0);
                                    }
                                    return $cat['brand_share'] > $maxCompetitor;
                                })->count();
                            @endphp
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                {{ $leadingCategories }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 uppercase">Categories Leading</div>
                        </div>
                    </div>
                </div>
            @endif
        </x-filament::section>
    @endif

    {{-- No Brand Selected State --}}
    @if(!$loading && !$error && !$brandId)
        <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
            <p class="text-gray-600 dark:text-gray-400">Please select a brand to view market share data</p>
        </div>
    @endif
</x-filament-panels::page>
