<div style="padding: 1.5rem;">
    {{-- Basic Info --}}
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
        <div>
            <div style="font-size: 0.875rem; font-weight: 500; color: #6b7280; margin-bottom: 0.25rem;">Sync Type</div>
            <div style="font-size: 0.875rem; color: #111827;">{{ ucfirst($syncRun->sync_type) }}</div>
        </div>
        <div>
            <div style="font-size: 0.875rem; font-weight: 500; color: #6b7280; margin-bottom: 0.25rem;">Entity Type</div>
            <div style="font-size: 0.875rem; color: #111827;">{{ $syncRun->entityType->name ?? 'N/A' }}</div>
        </div>
        <div>
            <div style="font-size: 0.875rem; font-weight: 500; color: #6b7280; margin-bottom: 0.25rem;">Started</div>
            <div style="font-size: 0.875rem; color: #111827;">{{ $syncRun->started_at->format('M j, Y H:i:s') }}</div>
        </div>
        <div>
            <div style="font-size: 0.875rem; font-weight: 500; color: #6b7280; margin-bottom: 0.25rem;">Completed</div>
            <div style="font-size: 0.875rem; color: #111827;">
                @if($syncRun->completed_at)
                    {{ $syncRun->completed_at->format('M j, Y H:i:s') }}
                @else
                    <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background-color: #fef3c7; color: #92400e;">In progress...</span>
                @endif
            </div>
        </div>
        <div>
            <div style="font-size: 0.875rem; font-weight: 500; color: #6b7280; margin-bottom: 0.25rem;">Duration</div>
            <div style="font-size: 0.875rem; color: #111827;">{{ $syncRun->duration ? $syncRun->duration . 's' : 'N/A' }}</div>
        </div>
        <div>
            <div style="font-size: 0.875rem; font-weight: 500; color: #6b7280; margin-bottom: 0.25rem;">Triggered By</div>
            <div style="font-size: 0.875rem; color: #111827;">{{ $syncRun->user ? $syncRun->user->name : ucfirst($syncRun->triggered_by) }}</div>
        </div>
    </div>

    {{-- Statistics --}}
    <div style="border-top: 1px solid #e5e7eb; padding-top: 1.5rem;">
        <h4 style="font-size: 0.875rem; font-weight: 500; color: #111827; margin-bottom: 1rem;">Statistics</h4>
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
            <div style="text-align: center;">
                <div style="font-size: 0.875rem; color: #6b7280;">Total</div>
                <div style="margin-top: 0.25rem; font-size: 1.5rem; font-weight: 600; color: #111827;">{{ $syncRun->total_items ?? 0 }}</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 0.875rem; color: #6b7280;">Successful</div>
                <div style="margin-top: 0.25rem; font-size: 1.5rem; font-weight: 600; color: #059669;">{{ $syncRun->successful_items ?? 0 }}</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 0.875rem; color: #6b7280;">Errors</div>
                <div style="margin-top: 0.25rem; font-size: 1.5rem; font-weight: 600; color: #dc2626;">{{ $syncRun->failed_items ?? 0 }}</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 0.875rem; color: #6b7280;">Skipped</div>
                <div style="margin-top: 0.25rem; font-size: 1.5rem; font-weight: 600; color: #6b7280;">{{ $syncRun->skipped_items ?? 0 }}</div>
            </div>
        </div>
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

