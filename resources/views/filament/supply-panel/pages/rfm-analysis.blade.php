<x-filament-panels::page>
    {{-- Premium Feature Gate --}}
    @unless(auth()->user()->hasRole('supplier-premium') || auth()->user()->hasRole('admin'))
        <x-premium-gate feature="RFM Analysis">
            <p class="text-gray-600 dark:text-gray-400">
                Upgrade to Premium to access RFM (Recency, Frequency, Monetary) analysis with
                customer segmentation, actionable insights, and targeted marketing recommendations.
            </p>
        </x-premium-gate>
    @else
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

                {{-- Period Selector --}}
                <div class="w-full sm:w-48">
                    <label for="monthsBackSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Time Period
                    </label>
                    <select
                        wire:model.live="monthsBack"
                        id="monthsBackSelect"
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
                <p class="mt-4 text-gray-600 dark:text-gray-400">Loading RFM analysis...</p>
            </div>
        @endif

        {{-- RFM Content --}}
        @if(!$loading && !$error && $brandId)
            {{-- Summary KPIs --}}
            <div class="mb-6 grid gap-4 grid-cols-2 md:grid-cols-4">
                {{-- Total Customers --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Customers</div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $this->formatNumber($summaryStats['total_customers'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Analyzed in this period
                    </div>
                </div>

                {{-- Champions --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Champions</div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-green-600 dark:text-green-400">
                            {{ $this->formatNumber($summaryStats['champions_count'] ?? 0) }}
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            ({{ $summaryStats['champions_pct'] ?? 0 }}%)
                        </span>
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Your best customers
                    </div>
                </div>

                {{-- At Risk --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">At Risk / Hibernating / Lost</div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-red-600 dark:text-red-400">
                            {{ $this->formatNumber($summaryStats['at_risk_count'] ?? 0) }}
                        </span>
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            ({{ $summaryStats['at_risk_pct'] ?? 0 }}%)
                        </span>
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Need re-engagement
                    </div>
                </div>

                {{-- Average Scores --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg RFM Scores</div>
                    <div class="mt-2 flex items-center gap-3">
                        <div class="text-center">
                            <div class="text-lg font-bold text-blue-600 dark:text-blue-400">{{ $summaryStats['avg_recency_score'] ?? 0 }}</div>
                            <div class="text-xs text-gray-500">R</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-purple-600 dark:text-purple-400">{{ $summaryStats['avg_frequency_score'] ?? 0 }}</div>
                            <div class="text-xs text-gray-500">F</div>
                        </div>
                        <div class="text-center">
                            <div class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ $summaryStats['avg_monetary_score'] ?? 0 }}</div>
                            <div class="text-xs text-gray-500">M</div>
                        </div>
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Scale: 1 (low) to 5 (high)
                    </div>
                </div>
            </div>

            {{-- Segment Distribution --}}
            <x-filament::section class="mb-6">
                <x-slot name="heading">
                    Customer Segments
                </x-slot>
                <x-slot name="description">
                    Distribution of customers across RFM segments with recommended actions
                </x-slot>

                @if(count($segments) > 0)
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach($segments as $segmentName => $segment)
                            @php
                                $definition = $this->getSegmentDefinitions()[$segmentName] ?? null;
                            @endphp
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 {{ $this->getSegmentBgColor($segmentName) }}">
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h3 class="font-semibold {{ $this->getSegmentColor($segmentName) }}">
                                            {{ $segmentName }}
                                        </h3>
                                        @if($definition)
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                                {{ $definition['description'] }}
                                            </p>
                                        @endif
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold {{ $this->getSegmentColor($segmentName) }}">
                                            {{ $this->formatNumber($segment['count']) }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            customers
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center gap-4 text-xs">
                                    <div>
                                        <span class="text-gray-500 dark:text-gray-400">Avg Revenue:</span>
                                        <span class="font-medium text-gray-900 dark:text-white ml-1">
                                            {{ $this->formatCurrency($segment['avg_revenue']) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                                            R:{{ $segment['r_avg'] }}
                                        </span>
                                        <span class="px-1.5 py-0.5 rounded bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300">
                                            F:{{ $segment['f_avg'] }}
                                        </span>
                                        <span class="px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300">
                                            M:{{ $segment['m_avg'] }}
                                        </span>
                                    </div>
                                </div>

                                @if($definition)
                                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                                        <div class="flex items-start gap-2">
                                            <svg class="h-4 w-4 text-gray-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                                <span class="font-medium">Action:</span> {{ $definition['action'] }}
                                            </p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                        <svg class="mx-auto h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <p class="mt-2 text-sm">No RFM data available</p>
                        <p class="text-xs">RFM analysis requires customer purchase history</p>
                    </div>
                @endif
            </x-filament::section>

            {{-- RFM Explanation --}}
            <x-filament::section>
                <x-slot name="heading">
                    Understanding RFM Analysis
                </x-slot>

                <div class="prose prose-sm dark:prose-invert max-w-none">
                    <p class="text-gray-600 dark:text-gray-400">
                        RFM analysis segments customers based on three key metrics:
                    </p>

                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4">
                            <h4 class="font-semibold text-blue-700 dark:text-blue-300 flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-200 dark:bg-blue-800 text-sm font-bold">R</span>
                                Recency
                            </h4>
                            <p class="text-sm text-blue-600 dark:text-blue-400 mt-2">
                                How recently did the customer make a purchase? More recent = higher score.
                            </p>
                        </div>

                        <div class="rounded-lg bg-purple-50 dark:bg-purple-900/20 p-4">
                            <h4 class="font-semibold text-purple-700 dark:text-purple-300 flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-purple-200 dark:bg-purple-800 text-sm font-bold">F</span>
                                Frequency
                            </h4>
                            <p class="text-sm text-purple-600 dark:text-purple-400 mt-2">
                                How often do they purchase? More frequent = higher score.
                            </p>
                        </div>

                        <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 p-4">
                            <h4 class="font-semibold text-emerald-700 dark:text-emerald-300 flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-200 dark:bg-emerald-800 text-sm font-bold">M</span>
                                Monetary
                            </h4>
                            <p class="text-sm text-emerald-600 dark:text-emerald-400 mt-2">
                                How much do they spend? Higher spend = higher score.
                            </p>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-4">
                        Each customer is scored 1-5 on each dimension using quintile distribution. Segments are then assigned based on score combinations.
                    </p>
                </div>
            </x-filament::section>
        @endif

        {{-- No Brand Selected --}}
        @if(!$loading && !$error && !$brandId)
            <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
                <p class="text-gray-600 dark:text-gray-400">Please select a brand to view RFM analysis</p>
            </div>
        @endif
    @endunless
</x-filament-panels::page>
