<div class="relative rounded-xl overflow-hidden">
    {{-- Blurred background content placeholder --}}
    <div class="blur-sm pointer-events-none select-none opacity-50 p-6 bg-gray-100 dark:bg-gray-800 min-h-[200px]">
        <div class="animate-pulse space-y-4">
            <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-3/4"></div>
            <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-1/2"></div>
            <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-5/6"></div>
            <div class="grid grid-cols-3 gap-4 mt-6">
                <div class="h-20 bg-gray-300 dark:bg-gray-600 rounded"></div>
                <div class="h-20 bg-gray-300 dark:bg-gray-600 rounded"></div>
                <div class="h-20 bg-gray-300 dark:bg-gray-600 rounded"></div>
            </div>
        </div>
    </div>

    {{-- Lock overlay --}}
    <div class="absolute inset-0 flex items-center justify-center bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm">
        <div class="text-center p-4 max-w-md">
            <div class="mx-auto w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/50 flex items-center justify-center mb-3">
                <x-heroicon-o-lock-closed class="w-5 h-5 text-amber-600 dark:text-amber-400" />
            </div>

            <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">
                {{ $title }}
            </h3>

            <p class="text-xs text-gray-600 dark:text-gray-400 mb-3">
                {{ $description }} {{ $feature }}.
            </p>

            <div class="space-y-1">
                <p class="text-[10px] text-gray-500 dark:text-gray-500">
                    Contact us to upgrade:
                </p>
                <a
                    href="mailto:{{ $contactEmail }}"
                    class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                >
                    <x-heroicon-o-envelope class="w-3 h-3" />
                    {{ $contactEmail }}
                </a>
            </div>
        </div>
    </div>
</div>
