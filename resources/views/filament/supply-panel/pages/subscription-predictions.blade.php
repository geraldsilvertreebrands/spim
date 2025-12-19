<x-filament-panels::page>
    {{-- Premium + Pet Heaven Feature Gate --}}
    @unless((auth()->user()->hasRole('supplier-premium') || auth()->user()->hasRole('admin')) && count($this->getPetHeavenBrands()) > 0)
        <x-premium-gate feature="Subscription Predictions">
            <p class="text-gray-600 dark:text-gray-400">
                Subscription Predictions is a Pet Heaven Premium feature. View upcoming deliveries,
                at-risk subscriptions, and revenue forecasts to proactively manage your subscription business.
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
                <p class="mt-4 text-gray-600 dark:text-gray-400">Loading predictions...</p>
            </div>
        @endif

        {{-- Predictions Content --}}
        @if(!$loading && !$error && $brandId && $this->hasData())
            {{-- Summary KPIs --}}
            <div class="mb-6 grid gap-4 grid-cols-2 md:grid-cols-4">
                {{-- Next 7 Days --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Deliveries (7 Days)</div>
                    <div class="mt-2 text-3xl font-bold text-blue-600 dark:text-blue-400">
                        {{ $this->formatNumber($summary['deliveries_next_7_days'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $this->formatCurrency($summary['revenue_next_7_days'] ?? 0) }} expected
                    </div>
                </div>

                {{-- Next 30 Days --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Deliveries (30 Days)</div>
                    <div class="mt-2 text-3xl font-bold text-green-600 dark:text-green-400">
                        {{ $this->formatNumber($summary['deliveries_next_30_days'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $this->formatCurrency($summary['revenue_next_30_days'] ?? 0) }} expected
                    </div>
                </div>

                {{-- At Risk Count --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">At Risk Subscriptions</div>
                    <div class="mt-2 text-3xl font-bold {{ ($summary['at_risk_count'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                        {{ $this->formatNumber($summary['at_risk_count'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $this->formatCurrency($summary['at_risk_mrr'] ?? 0) }} MRR at risk
                    </div>
                </div>

                {{-- Revenue Forecast --}}
                <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">30-Day Revenue Forecast</div>
                    <div class="mt-2 text-3xl font-bold text-purple-600 dark:text-purple-400">
                        {{ $this->formatCurrency($summary['revenue_next_30_days'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Based on scheduled deliveries
                    </div>
                </div>
            </div>

            {{-- At Risk Section --}}
            @if($this->hasAtRisk())
                <x-filament::section class="mb-6">
                    <x-slot name="heading">
                        <span class="flex items-center gap-2">
                            <span class="inline-block w-3 h-3 rounded-full bg-red-500"></span>
                            At Risk Subscriptions
                        </span>
                    </x-slot>
                    <x-slot name="description">
                        Subscriptions showing churn signals that may need attention
                    </x-slot>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Customer</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Product</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Value</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Last Order</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Days Ago</th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">Skips</th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">Risk Reason</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($atRisk as $subscription)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $subscription['customer_name'] }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-gray-900 dark:text-white">{{ $subscription['product_name'] }}</div>
                                            <div class="text-xs text-gray-500 font-mono">{{ $subscription['sku'] }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                            {{ $this->formatCurrency($subscription['subscription_value']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">
                                            {{ $this->formatDate($subscription['last_order_date']) }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <span class="{{ $subscription['days_since_last_order'] > 60 ? 'text-red-600 dark:text-red-400' : 'text-orange-600 dark:text-orange-400' }}">
                                                {{ $subscription['days_since_last_order'] }} days
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @if($subscription['skip_count'] > 0)
                                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400 text-xs font-medium">
                                                    {{ $subscription['skip_count'] }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium {{ $this->getRiskReasonClass($subscription['risk_reason']) }}">
                                                {{ $subscription['risk_reason'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif

            {{-- Upcoming Deliveries --}}
            @if($this->hasUpcoming())
                <x-filament::section>
                    <x-slot name="heading">
                        <span class="flex items-center gap-2">
                            <span class="inline-block w-3 h-3 rounded-full bg-green-500"></span>
                            Upcoming Deliveries (Next 30 Days)
                        </span>
                    </x-slot>
                    <x-slot name="description">
                        Scheduled subscription deliveries
                    </x-slot>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Delivery Date</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Customer</th>
                                    <th class="px-4 py-3 text-left font-semibold text-gray-600 dark:text-gray-300">Product</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Value</th>
                                    <th class="px-4 py-3 text-center font-semibold text-gray-600 dark:text-gray-300">Frequency</th>
                                    <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">Orders to Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($upcoming as $delivery)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                        <td class="px-4 py-3">
                                            <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium {{ $this->getUrgencyColorClass($delivery['days_until_delivery']) }}">
                                                {{ $this->formatDate($delivery['next_delivery_date']) }}
                                            </span>
                                            <div class="text-xs text-gray-500 mt-1">
                                                {{ $delivery['days_until_delivery'] }} days
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $delivery['customer_name'] }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-gray-900 dark:text-white">{{ $delivery['product_name'] }}</div>
                                            <div class="text-xs text-gray-500 font-mono">{{ $delivery['sku'] }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                            {{ $this->formatCurrency($delivery['subscription_value']) }}
                                        </td>
                                        <td class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">
                                            {{ $delivery['delivery_frequency'] }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">
                                            {{ $delivery['orders_to_date'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif
        @endif

        {{-- No Data State --}}
        @if(!$loading && !$error && $brandId && !$this->hasData())
            <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
                <p class="text-gray-600 dark:text-gray-400">No prediction data available for this brand</p>
            </div>
        @endif

        {{-- No Brand Selected --}}
        @if(!$loading && !$error && !$brandId)
            <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
                <p class="text-gray-600 dark:text-gray-400">Please select a Pet Heaven brand to view predictions</p>
            </div>
        @endif
    @endunless
</x-filament-panels::page>
