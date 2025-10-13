<?php

namespace App\Console\Commands;

use App\Models\SyncResult;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CleanupOldSyncResults extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:cleanup {--days=30 : Number of days to keep sync results}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old sync results older than specified days (default 30)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        // Get the oldest result before deleting
        $toDelete = SyncResult::where('created_at', '<', $cutoffDate)->get();
        $oldest = $toDelete->sortBy('created_at')->first();

        $count = SyncResult::where('created_at', '<', $cutoffDate)->delete();

        $this->info("Cleaned up {$count} sync results older than {$days} days.");

        if ($count > 0 && $oldest) {
            $oldestDays = Carbon::now()->diffInDays(Carbon::parse($oldest->created_at));
            $this->line("(Oldest deleted result: {$oldestDays} days)");
        }

        return Command::SUCCESS;
    }
}
