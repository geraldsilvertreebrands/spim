{{-- Export Buttons Component --}}
{{-- Usage: @include('filament.shared.components.export-buttons', ['showCsv' => true, 'showChart' => true, 'chartId' => 'myChart']) --}}

<div class="flex flex-wrap items-center gap-2 print:hidden">
    {{-- CSV Export Button --}}
    @if($showCsv ?? false)
        <button
            wire:click="exportToCsv"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-50 cursor-not-allowed"
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 dark:focus:ring-gray-700"
            title="Export to CSV"
        >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span wire:loading.remove wire:target="exportToCsv">CSV</span>
            <span wire:loading wire:target="exportToCsv">Exporting...</span>
        </button>
    @endif

    {{-- Chart Export Button --}}
    @if(($showChart ?? false) && ($chartId ?? null))
        <button
            onclick="exportChart('{{ $chartId }}', '{{ $chartFilename ?? 'chart' }}')"
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 dark:focus:ring-gray-700"
            title="Export chart as PNG"
        >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            PNG
        </button>
    @endif

    {{-- Print Button --}}
    @if($showPrint ?? true)
        <button
            onclick="window.print()"
            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 dark:focus:ring-gray-700"
            title="Print this page"
        >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Print
        </button>
    @endif
</div>

@once
    @push('scripts')
    <script>
        function exportChart(chartId, filename) {
            const chartInstance = Chart.getChart(chartId);
            if (!chartInstance) {
                console.error('Chart not found:', chartId);
                return;
            }

            // Create a temporary link element
            const link = document.createElement('a');
            link.download = filename + '_' + new Date().toISOString().slice(0, 10) + '.png';
            link.href = chartInstance.toBase64Image('image/png', 1);
            link.click();
        }
    </script>
    @endpush
@endonce
