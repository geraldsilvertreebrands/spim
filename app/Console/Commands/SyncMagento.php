<?php

namespace App\Console\Commands;

use App\Jobs\Sync\SyncAllProducts;
use App\Jobs\Sync\SyncSingleProduct;
use App\Models\Entity;
use App\Models\EntityType;
use Illuminate\Console\Command;

class SyncMagento extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:magento {entityType : The entity type name to sync} {--sku= : Optional SKU to sync a single product}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bi-directionally sync products between SPIM and Magento';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $entityTypeName = $this->argument('entityType');
        $sku = $this->option('sku');

        // Find entity type
        $entityType = EntityType::where('name', $entityTypeName)->first();

        if (!$entityType) {
            $this->error("Entity type '{$entityTypeName}' not found");
            return Command::FAILURE;
        }

        // If SKU is provided, sync single product
        if ($sku) {
            // Check if entity exists
            $entity = Entity::where('entity_type_id', $entityType->id)
                ->where('entity_id', $sku)
                ->first();

            if (!$entity) {
                $this->error("Entity with SKU '{$sku}' not found");
                return Command::FAILURE;
            }

            // Dispatch single product sync job
            SyncSingleProduct::dispatch(
                $entity, // Pass Entity object, not EntityType
                null, // userId
                'schedule' // triggeredBy
            );

            $this->info("Product sync for SKU {$sku} queued.");
        } else {
            // Dispatch all products sync job
            SyncAllProducts::dispatch(
                $entityType,
                null, // userId
                'schedule' // triggeredBy
            );

            $this->info("Full product sync for {$entityTypeName} queued.");
        }

        return Command::SUCCESS;
    }
}

