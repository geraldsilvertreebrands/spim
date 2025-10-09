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

        $this->info("Deleting sync results older than {$days} days (before {$cutoffDate->toDateString()})...");

        $count = SyncResult::where('created_at', '<', $cutoffDate)->delete();

        $this->info("Deleted {$count} old sync result(s).");

        return Command::SUCCESS;
    }
}
