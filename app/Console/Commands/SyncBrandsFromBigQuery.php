<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\BigQueryService;
use Illuminate\Console\Command;

class SyncBrandsFromBigQuery extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'brands:sync
                            {--company= : Override company ID from config}
                            {--dry-run : Show what would be synced without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync brands from BigQuery dim_product table to local database';

    /**
     * Execute the console command.
     */
    public function handle(BigQueryService $bigQuery): int
    {
        $companyId = $this->option('company') ?? $bigQuery->getCompanyId();
        $dryRun = $this->option('dry-run');

        $this->info("Syncing brands for company ID: {$companyId}");

        if ($dryRun) {
            $this->warn('DRY RUN - No changes will be made');
        }

        // Check if BigQuery is configured
        if (! $bigQuery->isConfigured()) {
            $this->error('BigQuery is not configured. Please check your credentials.');
            $this->line('');
            $this->line('Required configuration:');
            $this->line('  - BIGQUERY_PROJECT_ID in .env');
            $this->line('  - GOOGLE_APPLICATION_CREDENTIALS pointing to a valid JSON file');

            return Command::FAILURE;
        }

        try {
            $this->info('Fetching brands from BigQuery...');
            $brands = $bigQuery->getBrands();

            if ($brands->isEmpty()) {
                $this->warn('No brands found in BigQuery for this company.');

                return Command::SUCCESS;
            }

            $this->info("Found {$brands->count()} brands in BigQuery");

            if ($dryRun) {
                $this->table(
                    ['Brand Name'],
                    $brands->map(fn ($name) => [$name])->toArray()
                );

                return Command::SUCCESS;
            }

            $bar = $this->output->createProgressBar($brands->count());
            $bar->start();

            $created = 0;
            $updated = 0;

            foreach ($brands as $brandName) {
                $brand = Brand::updateOrCreate(
                    ['name' => $brandName, 'company_id' => $companyId],
                    ['synced_at' => now()]
                );

                if ($brand->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $this->info('Brand sync complete!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total brands', $brands->count()],
                    ['Created', $created],
                    ['Updated', $updated],
                ]
            );

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->newLine();
            $this->error("Sync failed: {$e->getMessage()}");

            if ($this->getOutput()->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
