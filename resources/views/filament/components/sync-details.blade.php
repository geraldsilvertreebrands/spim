<div class="p-6" x-data="{
    syncRunId: {{ $syncRun->id }},
    isRunning: {{ $syncRun->isRunning() ? 'true' : 'false' }},
    stats: {
        total: {{ $syncRun->total_items ?? 0 }},
        successful: {{ $syncRun->successful_items ?? 0 }},
        failed: {{ $syncRun->failed_items ?? 0 }},
        skipped: {{ $syncRun->skipped_items ?? 0 }},
    },
    completed_at: {{ $syncRun->completed_at ? "'" . $syncRun->completed_at->format('M j, Y H:i:s') . "'" : 'null' }},
    error_summary: {{ $syncRun->error_summary ? "'" . addslashes($syncRun->error_summary) . "'" : 'null' }},
    async refreshStats() {
        if (!this.isRunning) return;

        try {
            const response = await fetch('/admin/api/sync-runs/' + this.syncRunId);
            const data = await response.json();

            this.stats.total = data.total_items || 0;
            this.stats.successful = data.successful_items || 0;
            this.stats.failed = data.failed_items || 0;
            this.stats.skipped = data.skipped_items || 0;

            if (data.status !== 'running') {
                this.isRunning = false;
                this.completed_at = data.completed_at;
                this.error_summary = data.error_summary;
            }
        } catch (error) {
            console.error('Failed to refresh sync stats:', error);
        }
    },
    init() {
        if (this.isRunning) {
            // Poll every 2 seconds while running
            this.interval = setInterval(() => this.refreshStats(), 2000);
        }
    }
}" x-init="init()">
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
                <span x-show="!isRunning && completed_at" x-text="completed_at"></span>
                <span x-show="isRunning" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                    In progress...
                </span>
                <span x-show="!isRunning && !completed_at">N/A</span>
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
        <h4 class="text-sm font-medium text-gray-900 mb-4">
            Statistics
            <span x-show="isRunning" class="ml-2 inline-flex items-center">
                <svg class="animate-spin h-4 w-4 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="ml-1 text-xs text-amber-600">Auto-updating...</span>
            </span>
        </h4>
        <div class="grid grid-cols-4 gap-4">
            <div class="text-center">
                <div class="text-sm text-gray-500">Total</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900" x-text="stats.total"></div>
            </div>
            <div class="text-center">
                <div class="text-sm text-gray-500">Successful</div>
                <div class="mt-1 text-2xl font-semibold text-green-600" x-text="stats.successful"></div>
            </div>
            <div class="text-center">
                <div class="text-sm text-gray-500">Errors</div>
                <div class="mt-1 text-2xl font-semibold text-red-600" x-text="stats.failed"></div>
            </div>
            <div class="text-center">
                <div class="text-sm text-gray-500">Skipped</div>
                <div class="mt-1 text-2xl font-semibold text-gray-500" x-text="stats.skipped"></div>
            </div>
        </div>
    </div>

    <div x-show="!isRunning && error_summary" class="border-t border-gray-200 pt-4 mt-4">
        <h4 class="text-sm font-medium text-gray-900 mb-2">Error Summary</h4>
        <div class="rounded-lg bg-red-50 p-3">
            <p class="text-sm text-red-800" x-text="error_summary"></p>
        </div>
    </div>
</div>
