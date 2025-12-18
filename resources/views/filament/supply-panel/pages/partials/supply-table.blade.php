{{-- Supply Chain Table Partial --}}
{{-- Usage: @include('filament.supply-panel.pages.partials.supply-table', ['data' => $sellInData, 'months' => $months, 'emptyMessage' => 'No data']) --}}

<div class="overflow-x-auto">
    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
        <thead>
            <tr class="bg-gray-50 dark:bg-gray-800">
                <th class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-800 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    SKU
                </th>
                <th class="sticky left-24 z-10 bg-gray-50 dark:bg-gray-800 px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Name
                </th>
                @foreach($months as $month)
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 whitespace-nowrap">
                        {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('M Y') }}
                    </th>
                @endforeach
                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700">
                    Total
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
            @forelse($data as $product)
                @php
                    $total = array_sum($product['months'] ?? []);
                    $lastMonth = end($months);
                    $momChange = null;
                    if ($lastMonth && count($product['months'] ?? []) >= 2) {
                        $sortedMonths = array_keys($product['months']);
                        sort($sortedMonths);
                        $lastIndex = array_search($lastMonth, $sortedMonths);
                        if ($lastIndex !== false && $lastIndex > 0) {
                            $prevMonth = $sortedMonths[$lastIndex - 1];
                            $currentValue = $product['months'][$lastMonth] ?? 0;
                            $prevValue = $product['months'][$prevMonth] ?? 0;
                            if ($prevValue > 0) {
                                $momChange = round((($currentValue - $prevValue) / $prevValue) * 100, 1);
                            }
                        }
                    }
                @endphp
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                    <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">
                        {{ $product['sku'] ?? 'N/A' }}
                    </td>
                    <td class="sticky left-24 z-10 bg-white dark:bg-gray-900 px-4 py-3 font-medium text-gray-900 dark:text-white max-w-xs truncate">
                        {{ $product['name'] ?? 'N/A' }}
                    </td>
                    @foreach($months as $index => $month)
                        @php
                            $value = $product['months'][$month] ?? 0;
                            $prevMonth = $index > 0 ? $months[$index - 1] : null;
                            $prevValue = $prevMonth ? ($product['months'][$prevMonth] ?? 0) : null;
                            $cellMomChange = null;
                            if ($prevValue !== null && $prevValue > 0) {
                                $cellMomChange = round((($value - $prevValue) / $prevValue) * 100, 1);
                            }
                        @endphp
                        <td class="whitespace-nowrap px-4 py-3 text-right text-gray-700 dark:text-gray-300">
                            @if($value > 0)
                                <span class="block">{{ number_format($value, 0) }}</span>
                                @if($cellMomChange !== null)
                                    <span class="text-xs {{ $cellMomChange >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $cellMomChange >= 0 ? '+' : '' }}{{ $cellMomChange }}%
                                    </span>
                                @endif
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                    @endforeach
                    <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-800">
                        {{ number_format($total, 0) }}
                        @if($momChange !== null)
                            <span class="block text-xs {{ $momChange >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $momChange >= 0 ? '+' : '' }}{{ $momChange }}% MoM
                            </span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 3 + count($months) }}" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ $emptyMessage ?? 'No data available' }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
