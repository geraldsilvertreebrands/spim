<div class="space-y-4">
    @foreach($errors as $error)
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-danger-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $error->item_identifier ?? 'Unknown item' }}
                    </p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ $error->error_message }}
                    </p>
                    @if($error->operation)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">
                            Operation: {{ ucfirst($error->operation) }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

