<x-filament-panels::page>
    {{-- Premium Feature Gate --}}
    @unless(auth()->user()->hasRole('supplier-premium') || auth()->user()->hasRole('admin'))
        <x-premium-gate feature="Cohort Analysis">
            <p class="text-gray-600 dark:text-gray-400">
                Upgrade to Premium to access cohort analysis with customer retention tracking,
                acquisition trends, and detailed cohort matrices to understand customer behavior over time.
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

                {{-- Metric Selector --}}
                <div class="w-full sm:w-48">
                    <label for="metricSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Display Metric
                    </label>
                    <select
                        wire:model.live="metric"
                        id="metricSelect"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        @foreach($this->getMetricOptions() as $value => $label)
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
                <p class="mt-4 text-gray-600 dark:text-gray-400">Loading cohort data...</p>
            </div>
        @endif

        {{-- Cohort Content --}}
        @if(!$loading && !$error && $brandId)
            {{-- Summary KPIs --}}
            <div class="mb-6 grid gap-4 grid-cols-2 md:grid-cols-4">
                {{-- Total Cohorts --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Cohorts</div>
                    <div class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $summaryStats['total_cohorts'] ?? 0 }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Acquisition months tracked
                    </div>
                </div>

                {{-- Avg Month 1 Retention --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Month 1 Retention</div>
                    <div class="mt-2 text-2xl font-bold text-blue-600 dark:text-blue-400">
                        {{ $summaryStats['avg_month1_retention'] ?? 0 }}%
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Customers returning after 1 month
                    </div>
                </div>

                {{-- Avg Month 3 Retention --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg Month 3 Retention</div>
                    <div class="mt-2 text-2xl font-bold text-purple-600 dark:text-purple-400">
                        {{ $summaryStats['avg_month3_retention'] ?? 0 }}%
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Customers returning after 3 months
                    </div>
                </div>

                {{-- Retention Trend --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Retention Trend</div>
                    <div class="mt-2 flex items-center gap-2">
                        <span class="text-2xl font-bold {{ $this->getTrendColorClass() }}">
                            {{ $this->getTrendIcon() }}
                            {{ ucfirst($summaryStats['overall_retention_trend'] ?? 'stable') }}
                        </span>
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Recent cohorts vs older
                    </div>
                </div>
            </div>

            {{-- Cohort Highlights --}}
            @if($summaryStats['best_cohort'] || $summaryStats['worst_cohort'])
                <div class="mb-6 grid gap-4 md:grid-cols-2">
                    @if($summaryStats['best_cohort'])
                        <div class="rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                            <div class="flex items-center gap-2">
                                <svg width="20" height="20" class="w-5 h-5 flex-shrink-0 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                </svg>
                                <span class="font-medium text-green-800 dark:text-green-200">Best Performing Cohort</span>
                            </div>
                            <p class="mt-1 text-sm text-green-700 dark:text-green-300">
                                {{ $summaryStats['best_cohort'] }} had the highest month-1 retention rate
                            </p>
                        </div>
                    @endif

                    @if($summaryStats['worst_cohort'])
                        <div class="rounded-lg bg-amber-50 p-4 dark:bg-amber-900/20">
                            <div class="flex items-center gap-2">
                                <svg width="20" height="20" class="w-5 h-5 flex-shrink-0 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span class="font-medium text-amber-800 dark:text-amber-200">Needs Attention</span>
                            </div>
                            <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                                {{ $summaryStats['worst_cohort'] }} had the lowest month-1 retention rate
                            </p>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Cohort Matrix --}}
            <x-filament::section class="mb-6">
                <x-slot name="heading">
                    Cohort Retention Matrix
                </x-slot>
                <x-slot name="description">
                    @if($metric === 'retention')
                        Percentage of customers returning each month after acquisition
                    @elseif($metric === 'customers')
                        Number of active customers per cohort each month
                    @else
                        Average revenue per customer per cohort each month
                    @endif
                </x-slot>

                @if(count($cohortData) > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-3 py-3 text-left font-semibold text-gray-600 dark:text-gray-300 sticky left-0 bg-white dark:bg-gray-800 z-10">
                                        Cohort
                                    </th>
                                    <th class="px-3 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">
                                        Size
                                    </th>
                                    @for($m = 0; $m <= 12; $m++)
                                        <th class="px-3 py-3 text-center font-semibold text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                            M{{ $m }}
                                        </th>
                                    @endfor
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($cohortMonths as $cohortMonth)
                                    @if(isset($cohortData[$cohortMonth]))
                                        @php $cohort = $cohortData[$cohortMonth]; @endphp
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <td class="px-3 py-3 font-medium text-gray-900 dark:text-white whitespace-nowrap sticky left-0 bg-white dark:bg-gray-800 z-10">
                                                {{ $cohortMonth }}
                                            </td>
                                            <td class="px-3 py-3 text-center text-gray-600 dark:text-gray-400">
                                                {{ $this->formatNumber($cohort['size']) }}
                                            </td>
                                            @for($m = 0; $m <= 12; $m++)
                                                <td class="px-3 py-3 text-center">
                                                    @if($metric === 'retention' && isset($cohort['retention'][$m]))
                                                        <span class="inline-block px-2 py-1 rounded text-xs font-medium {{ $this->getRetentionColorClass($cohort['retention'][$m]) }}">
                                                            {{ $cohort['retention'][$m] }}%
                                                        </span>
                                                    @elseif($metric === 'customers' && isset($cohort['customers'][$m]))
                                                        <span class="text-gray-900 dark:text-white">
                                                            {{ $this->formatNumber($cohort['customers'][$m]) }}
                                                        </span>
                                                    @elseif($metric === 'revenue' && isset($cohort['revenue'][$m]))
                                                        <span class="text-gray-900 dark:text-white">
                                                            R{{ $this->formatNumber($cohort['revenue'][$m]) }}
                                                        </span>
                                                    @else
                                                        <span class="text-gray-300 dark:text-gray-600">-</span>
                                                    @endif
                                                </td>
                                            @endfor
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Legend --}}
                    @if($metric === 'retention')
                        <div class="mt-6 flex flex-wrap items-center gap-4 text-xs">
                            <span class="font-medium text-gray-600 dark:text-gray-400">Retention Legend:</span>
                            <span class="inline-flex items-center gap-1">
                                <span class="inline-block w-4 h-4 rounded bg-green-600"></span>
                                50%+
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span class="inline-block w-4 h-4 rounded bg-green-400"></span>
                                30-49%
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span class="inline-block w-4 h-4 rounded bg-yellow-400"></span>
                                20-29%
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span class="inline-block w-4 h-4 rounded bg-orange-400"></span>
                                10-19%
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span class="inline-block w-4 h-4 rounded bg-red-400"></span>
                                &lt;10%
                            </span>
                        </div>
                    @endif
                @else
                    <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                        <svg width="32" height="32" class="mx-auto w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <p class="mt-2 text-sm">No cohort data available</p>
                        <p class="text-xs">Cohort data requires customer purchase history</p>
                    </div>
                @endif
            </x-filament::section>

            {{-- How to Read Cohorts --}}
            <x-filament::section>
                <x-slot name="heading">
                    Understanding Cohort Analysis
                </x-slot>

                <div class="prose prose-sm dark:prose-invert max-w-none">
                    <p class="text-gray-600 dark:text-gray-400">
                        Cohort analysis groups customers by their acquisition month and tracks their behavior over time.
                    </p>
                    <ul class="text-gray-600 dark:text-gray-400 text-sm mt-3 space-y-1">
                        <li><span class="font-medium">Cohort:</span> The month when customers made their first purchase from your brand</li>
                        <li><span class="font-medium">Size:</span> Number of new customers acquired in that month</li>
                        <li><span class="font-medium">M0:</span> The acquisition month (always 100% for retention)</li>
                        <li><span class="font-medium">M1, M2, etc.:</span> Months after acquisition (1 month later, 2 months later, etc.)</li>
                        <li><span class="font-medium text-green-600 dark:text-green-400">Higher retention</span> in later months indicates strong customer loyalty</li>
                        <li><span class="font-medium text-red-600 dark:text-red-400">Declining retention</span> over time is normal, but the rate matters</li>
                    </ul>
                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-4">
                        Tip: Compare cohorts vertically (same column) to see if retention is improving over time for your brand.
                    </p>
                </div>
            </x-filament::section>
        @endif

        {{-- No Brand Selected --}}
        @if(!$loading && !$error && !$brandId)
            <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
                <p class="text-gray-600 dark:text-gray-400">Please select a brand to view cohort analysis</p>
            </div>
        @endif
    @endunless
</x-filament-panels::page>
