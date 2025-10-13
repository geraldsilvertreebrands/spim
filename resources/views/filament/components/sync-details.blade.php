<div class="p-6">
    {{-- Basic Info --}}
    <div class="grid grid-cols-2 gap-6 mb-8">
        <div>
            <div class="text-sm font-medium text-gray-500 mb-1">Sync Type</div>
            <div class="text-sm text-gray-900">{{ ucfirst($syncRun->sync_type) }}</div>
        </div>
        <div>
            <div class="text-sm font-medium text-gray-500 mb-1">Entity Type</div>
            <div class="text-sm text-gray-900">{{ $syncRun->entityType->name ?? 'N/A' }}</div>
        </div>
        <div>
            <div class="text-sm font-medium text-gray-500 mb-1">Started</div>
            <div class="text-sm text-gray-900">{{ $syncRun->started_at->format('M j, Y H:i:s') }}</div>
        </div>
        <div>
            <div class="text-sm font-medium text-gray-500 mb-1">Completed</div>
            <div class="text-sm text-gray-900">
                @if($syncRun->completed_at)
                    {{ $syncRun->completed_at->format('M j, Y H:i:s') }}
                @else
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">In progress...</span>
                @endif
            </div>
        </div>
        <div>
            <div class="text-sm font-medium text-gray-500 mb-1">Duration</div>
            <div class="text-sm text-gray-900">{{ $syncRun->duration ? $syncRun->duration . 's' : 'N/A' }}</div>
        </div>
        <div>
            <div class="text-sm font-medium text-gray-500 mb-1">Triggered By</div>
            <div class="text-sm text-gray-900">{{ $syncRun->user ? $syncRun->user->name : ucfirst($syncRun->triggered_by) }}</div>
        </div>
    </div>

    {{-- Statistics --}}
    <div class="border-t border-gray-200 pt-6">
        <h4 class="text-sm font-medium text-gray-900 mb-4">Statistics</h4>
        <div class="grid grid-cols-4 gap-4">
            <div class="text-center">
                <div class="text-sm text-gray-500">Total</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ $syncRun->total_items ?? 0 }}</div>
            </div>
            <div class="text-center">
                <div class="text-sm text-gray-500">Successful</div>
                <div class="mt-1 text-2xl font-semibold text-green-600">{{ $syncRun->successful_items ?? 0 }}</div>
            </div>
            <div class="text-center">
                <div class="text-sm text-gray-500">Errors</div>
                <div class="mt-1 text-2xl font-semibold text-red-600">{{ $syncRun->failed_items ?? 0 }}</div>
            </div>
            <div class="text-center">
                <div class="text-sm text-gray-500">Skipped</div>
                <div class="mt-1 text-2xl font-semibold text-gray-500">{{ $syncRun->skipped_items ?? 0 }}</div>
            </div>
        </div>
    </div>

    @if($syncRun->error_summary)
        <div class="border-t border-gray-200 pt-4">
            <h4 class="text-sm font-medium text-gray-900 mb-2">Error Summary</h4>
            <div class="rounded-lg bg-red-50 p-3">
                <p class="text-sm text-red-800">{{ $syncRun->error_summary }}</p>
            </div>
        </div>
    @endif
</div>
