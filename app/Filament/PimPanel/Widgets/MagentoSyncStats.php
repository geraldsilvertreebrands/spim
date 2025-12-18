<?php

namespace App\Filament\PimPanel\Widgets;

use App\Models\SyncRun;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class MagentoSyncStats extends BaseWidget
{
    protected function getStats(): array
    {
        $lastRun = SyncRun::latest()->first();
        $runningSync = SyncRun::where('status', 'running')->first();

        // Count products pending sync (where value_approved != value_live)
        $pendingCount = DB::table('eav_versioned as v')
            ->join('attributes as a', 'v.attribute_id', '=', 'a.id')
            ->whereIn('a.is_sync', ['from_external', 'to_external'])
            ->whereRaw('COALESCE(v.value_approved, "") != COALESCE(v.value_live, "")')
            ->distinct('v.entity_id')
            ->count('v.entity_id');

        // Get today's sync stats
        $todayStats = SyncRun::whereDate('started_at', today())
            ->selectRaw('
                COUNT(*) as total_runs,
                SUM(successful_items) as total_success,
                SUM(failed_items) as total_errors
            ')
            ->first();

        return [
            Stat::make('Last Sync', $lastRun ? $lastRun->completed_at?->diffForHumans() ?? 'Running...' : 'Never')
                ->description($lastRun ? ucfirst($lastRun->status) : '-')
                ->descriptionIcon($this->getStatusIcon($lastRun?->status))
                ->color($this->getStatusColor($lastRun?->status)),

            Stat::make('Products Pending Sync', $pendingCount)
                ->description('Approved but not live')
                ->descriptionIcon('heroicon-o-clock')
                ->color($pendingCount > 0 ? 'warning' : 'success'),

            Stat::make('Today\'s Syncs', $todayStats->total_runs ?? 0)
                ->description(sprintf(
                    '%d successful, %d errors',
                    $todayStats->total_success ?? 0,
                    $todayStats->total_errors ?? 0
                ))
                ->descriptionIcon('heroicon-o-calendar')
                ->color($todayStats->total_errors > 0 ? 'danger' : 'success'),

            Stat::make('Active Sync', $runningSync ? 'In Progress' : 'Idle')
                ->description($runningSync ? ($runningSync->entityType->name.' - '.ucfirst($runningSync->sync_type)) : 'No active syncs')
                ->descriptionIcon($runningSync ? 'heroicon-o-arrow-path' : 'heroicon-o-check-circle')
                ->color($runningSync ? 'info' : 'gray'),
        ];
    }

    private function getStatusIcon(?string $status): string
    {
        return match ($status) {
            'completed' => 'heroicon-o-check-circle',
            'partial' => 'heroicon-o-exclamation-triangle',
            'failed' => 'heroicon-o-x-circle',
            'running' => 'heroicon-o-arrow-path',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    private function getStatusColor(?string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'partial' => 'warning',
            'failed' => 'danger',
            'running' => 'info',
            default => 'gray',
        };
    }

    protected static ?int $sort = -1; // Show at top
}
