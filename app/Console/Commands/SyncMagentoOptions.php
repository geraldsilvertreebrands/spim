<?php

namespace App\Console\Commands;

use App\Models\EntityType;
use Illuminate\Console\Command;

class SyncMagentoOptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:magento:options {entityType : The entity type name to sync options for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bi-directionally sync attribute options between SPIM and Magento';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $entityTypeName = $this->argument('entityType');

        // Find entity type
        $entityType = EntityType::where('name', $entityTypeName)->first();

        if (! $entityType) {
            $this->error("Entity type '{$entityTypeName}' not found");

            return Command::FAILURE;
        }

        // Dispatch job to queue
        \App\Jobs\Sync\SyncAttributeOptions::dispatch(
            $entityType,
            null, // userId
            'schedule' // triggeredBy
        );

        $this->info("Attribute option sync for {$entityTypeName} queued.");

        return Command::SUCCESS;
    }
}
