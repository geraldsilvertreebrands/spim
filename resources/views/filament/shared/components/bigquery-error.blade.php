<div class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4">
    <div class="flex items-start gap-3">
        {{-- Error Icon --}}
        <div class="flex-shrink-0">
            <div class="w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/50 flex items-center justify-center">
                <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-red-600 dark:text-red-400" />
            </div>
        </div>

        <div class="flex-1 min-w-0">
            {{-- Error Message --}}
            <h3 class="text-sm font-semibold text-red-800 dark:text-red-200 mb-1">
                Data Load Failed
            </h3>
            <p class="text-sm text-red-700 dark:text-red-300 mb-4">
                {{ $message }}
            </p>

            {{-- Technical Details (for admins) --}}
            @if($showTechnicalDetails && $technicalDetails)
                <details class="mb-4">
                    <summary class="text-xs text-red-600 dark:text-red-400 cursor-pointer hover:text-red-700 dark:hover:text-red-300">
                        Technical Details
                    </summary>
                    <pre class="mt-2 text-xs text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-900/30 p-2 rounded overflow-x-auto">{{ $technicalDetails }}</pre>
                </details>
            @endif

            {{-- Action Buttons --}}
            <div class="flex gap-2">
                @if($retryAction)
                    <button
                        wire:click="{{ $retryAction }}"
                        type="button"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600 rounded-md transition-colors"
                    >
                        <x-heroicon-o-arrow-path class="w-4 h-4" />
                        Try Again
                    </button>
                @endif

                <a
                    href="mailto:support@silvertreebrands.com?subject=Analytics Error"
                    class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium text-red-700 dark:text-red-300 hover:text-red-800 dark:hover:text-red-200 border border-red-300 dark:border-red-700 rounded-md hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors"
                >
                    <x-heroicon-o-envelope class="w-4 h-4" />
                    Contact Support
                </a>
            </div>
        </div>
    </div>
</div>
