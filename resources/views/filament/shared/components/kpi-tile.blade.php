<div class="rounded-xl bg-white dark:bg-gray-800 p-6 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
    <div class="flex items-start justify-between">
        <div class="flex-1">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ $label }}
            </p>

            <div class="mt-2 flex items-baseline gap-1">
                @if($prefix)
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $prefix }}</span>
                @endif

                <span class="text-3xl font-bold text-gray-900 dark:text-white">
                    {{ is_numeric($value) ? number_format($value) : $value }}
                </span>

                @if($suffix)
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $suffix }}</span>
                @endif
            </div>

            @if($change !== null)
                <div class="mt-2 flex items-center gap-1 text-sm {{ $trendColor }}">
                    @if($trendDirection === 'up')
                        <x-heroicon-m-arrow-trending-up class="w-4 h-4" />
                    @elseif($trendDirection === 'down')
                        <x-heroicon-m-arrow-trending-down class="w-4 h-4" />
                    @else
                        <x-heroicon-m-minus class="w-4 h-4" />
                    @endif

                    <span class="font-medium">{{ $formattedChange() }}</span>
                    <span class="text-gray-500 dark:text-gray-400">{{ $changePeriod }}</span>
                </div>
            @endif
        </div>

        @if($icon)
            <div class="flex-shrink-0 rounded-lg bg-{{ $color }}-50 dark:bg-{{ $color }}-900/50 p-3">
                <x-dynamic-component :component="$icon" class="w-6 h-6 text-{{ $color }}-600 dark:text-{{ $color }}-400" />
            </div>
        @endif
    </div>
</div>
