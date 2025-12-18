@if($type === 'table')
    {{-- Table Skeleton --}}
    <div class="animate-pulse">
        {{-- Table Header --}}
        <div class="bg-gray-100 dark:bg-gray-800 rounded-t-lg p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="grid gap-4" style="grid-template-columns: repeat({{ $columns }}, 1fr);">
                @for($i = 0; $i < $columns; $i++)
                    <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded"></div>
                @endfor
            </div>
        </div>

        {{-- Table Rows --}}
        @for($row = 0; $row < $rows; $row++)
            <div class="bg-white dark:bg-gray-900 p-4 border-b border-gray-200 dark:border-gray-700 {{ $row === $rows - 1 ? 'rounded-b-lg' : '' }}">
                <div class="grid gap-4" style="grid-template-columns: repeat({{ $columns }}, 1fr);">
                    @for($col = 0; $col < $columns; $col++)
                        <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded" style="width: {{ rand(60, 100) }}%"></div>
                    @endfor
                </div>
            </div>
        @endfor
    </div>

@elseif($type === 'chart')
    {{-- Chart Skeleton --}}
    <div class="animate-pulse bg-white dark:bg-gray-900 rounded-lg p-6" style="height: {{ $height }}">
        <div class="flex items-end justify-between h-full gap-2">
            @for($i = 0; $i < 12; $i++)
                <div class="bg-gray-200 dark:bg-gray-700 rounded-t flex-1" style="height: {{ rand(30, 100) }}%"></div>
            @endfor
        </div>
    </div>

@elseif($type === 'stats')
    {{-- Stats/KPI Tiles Skeleton --}}
    <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        @for($i = 0; $i < $count; $i++)
            <div class="animate-pulse bg-white dark:bg-gray-900 rounded-lg p-6 shadow">
                <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/2 mb-4"></div>
                <div class="h-8 bg-gray-300 dark:bg-gray-600 rounded w-3/4 mb-2"></div>
                <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/3"></div>
            </div>
        @endfor
    </div>

@else
    {{-- Generic Card Skeleton --}}
    <div class="animate-pulse bg-white dark:bg-gray-900 rounded-lg p-6 shadow">
        <div class="space-y-4">
            <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-3/4"></div>
            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-5/6"></div>
            <div class="grid grid-cols-3 gap-4 mt-6">
                <div class="h-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                <div class="h-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                <div class="h-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
            </div>
        </div>
    </div>
@endif
