<?php

namespace App\Console\Commands;

use App\Models\EntityType;
use App\Services\MagentoApiClient;
use App\Services\Sync\AttributeOptionSync;
use Illuminate\Console\Command;
use RuntimeException;

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

        $this->info("Starting attribute option sync for entity type: {$entityTypeName}");

        // Find entity type
        $entityType = EntityType::where('name', $entityTypeName)->first();

        if (!$entityType) {
            $this->error("Entity type '{$entityTypeName}' not found");
            return Command::FAILURE;
        }

        try {
            // Create sync service
            $sync = app(AttributeOptionSync::class, [
                'entityType' => $entityType,
            ]);

            // Run sync
            $result = $sync->sync();
            $stats = $result['stats'];

            // Display results
            $this->newLine();
            $this->info('Option sync completed successfully!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Created', $stats['created']],
                    ['Updated', $stats['updated']],
                    ['Errors', $stats['errors']],
                    ['Skipped', $stats['skipped']],
                ]
            );

            return Command::SUCCESS;

        } catch (RuntimeException $e) {
            $this->newLine();
            $this->error('Option sync failed with conflicts:');
            $this->error($e->getMessage());
            $this->newLine();
            $this->warn('Please resolve conflicts manually before proceeding with product sync.');

            return Command::FAILURE;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error('Option sync failed:');
            $this->error($e->getMessage());

            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}



