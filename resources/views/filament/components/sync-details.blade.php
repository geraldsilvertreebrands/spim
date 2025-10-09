<div class="space-y-4">
    <dl class="grid grid-cols-2 gap-4">
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Sync Type</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ ucfirst($syncRun->sync_type) }}</dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Entity Type</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $syncRun->entityType->name ?? 'N/A' }}</dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Started</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $syncRun->started_at->format('M j, Y H:i:s') }}</dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Completed</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                {{ $syncRun->completed_at ? $syncRun->completed_at->format('M j, Y H:i:s') : 'In progress...' }}
            </dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Duration</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $syncRun->duration ? $syncRun->duration . 's' : 'N/A' }}</dd>
        </div>
        <div>
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Triggered By</dt>
            <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                {{ $syncRun->user ? $syncRun->user->name : ucfirst($syncRun->triggered_by) }}
            </dd>
        </div>
    </dl>

    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Statistics</h4>
        <dl class="grid grid-cols-4 gap-4">
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Total</dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $syncRun->total_items }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Successful</dt>
                <dd class="mt-1 text-2xl font-semibold text-success-600">{{ $syncRun->successful_items }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Errors</dt>
                <dd class="mt-1 text-2xl font-semibold text-danger-600">{{ $syncRun->failed_items }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Skipped</dt>
                <dd class="mt-1 text-2xl font-semibold text-gray-600 dark:text-gray-400">{{ $syncRun->skipped_items }}</dd>
            </div>
        </dl>
    </div>

    @if($syncRun->error_summary)
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Error Summary</h4>
            <div class="rounded-lg bg-danger-50 dark:bg-danger-900/20 p-3">
                <p class="text-sm text-danger-800 dark:text-danger-200">{{ $syncRun->error_summary }}</p>
            </div>
        </div>
    @endif
</div>

