<?php

namespace Tests\Feature\Pipeline;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\Pipeline;
use App\Models\PipelineModule;
use App\Pipelines\Modules\AttributesSourceModule;
use App\Pipelines\Modules\CalculationProcessorModule;
use App\Services\EavWriter;
use App\Services\PipelineExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineEntityFilterTest extends TestCase
{
    use RefreshDatabase;

    protected EntityType $entityType;

    protected Attribute $statusAttribute;

    protected Attribute $targetAttribute;

    protected Attribute $inputAttribute;

    protected Pipeline $pipeline;

    protected function setUp(): void
    {
        parent::setUp();

        // Create entity type with unique name
        $this->entityType = EntityType::factory()->create(['name' => 'Product_'.uniqid()]);

        // Create attributes
        $this->statusAttribute = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'status',
            'display_name' => 'Status',
            'data_type' => 'select',
            'allowed_values' => ['1' => 'Enabled', '2' => 'Disabled'],
        ]);

        $this->inputAttribute = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'price',
            'display_name' => 'Price',
            'data_type' => 'integer',
        ]);

        $this->targetAttribute = Attribute::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'double_price',
            'display_name' => 'Double Price',
            'data_type' => 'integer',
        ]);

        // Create pipeline with filter
        $this->pipeline = Pipeline::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'attribute_id' => $this->targetAttribute->id,
            'entity_filter' => [
                'attribute_id' => $this->statusAttribute->id,
                'operator' => '=',
                'value' => '1',
            ],
        ]);

        // Add source module
        PipelineModule::create([
            'pipeline_id' => $this->pipeline->id,
            'order' => 1,
            'module_class' => AttributesSourceModule::class,
            'settings' => [
                'attribute_ids' => [$this->inputAttribute->id],
            ],
        ]);

        // Add calculation processor
        PipelineModule::create([
            'pipeline_id' => $this->pipeline->id,
            'order' => 2,
            'module_class' => CalculationProcessorModule::class,
            'settings' => [
                'code' => 'return { value: ($json.price || 0) * 2, justification: "Doubled", confidence: 1.0 };',
            ],
        ]);
    }

    public function test_pipeline_filters_entities_by_status_equals()
    {
        $eavWriter = app(EavWriter::class);

        // Create 3 entities with different statuses
        $entity1 = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'PROD-001',
        ]);
        $eavWriter->upsertVersioned($entity1->id, $this->statusAttribute->id, '1'); // Enabled
        $eavWriter->upsertVersioned($entity1->id, $this->inputAttribute->id, 100);

        $entity2 = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'PROD-002',
        ]);
        $eavWriter->upsertVersioned($entity2->id, $this->statusAttribute->id, '2'); // Disabled
        $eavWriter->upsertVersioned($entity2->id, $this->inputAttribute->id, 200);

        $entity3 = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'PROD-003',
        ]);
        $eavWriter->upsertVersioned($entity3->id, $this->statusAttribute->id, '1'); // Enabled
        $eavWriter->upsertVersioned($entity3->id, $this->inputAttribute->id, 300);

        // Execute pipeline
        $executionService = app(PipelineExecutionService::class);
        $stats = $executionService->executeBatch(
            $this->pipeline,
            collect([$entity1->id, $entity2->id, $entity3->id])
        );

        // Should process 2 entities (status = 1) and skip 1 (status = 2)
        $this->assertEquals(2, $stats['processed']);
        $this->assertEquals(1, $stats['skipped']);
        $this->assertEquals(0, $stats['failed']);

        // Check that only enabled entities were processed
        $result1 = \DB::table('eav_versioned')
            ->where('entity_id', $entity1->id)
            ->where('attribute_id', $this->targetAttribute->id)
            ->first();
        $this->assertNotNull($result1);
        $this->assertEquals('200', $result1->value_current); // 100 * 2

        $result2 = \DB::table('eav_versioned')
            ->where('entity_id', $entity2->id)
            ->where('attribute_id', $this->targetAttribute->id)
            ->first();
        $this->assertNull($result2); // Should not be processed

        $result3 = \DB::table('eav_versioned')
            ->where('entity_id', $entity3->id)
            ->where('attribute_id', $this->targetAttribute->id)
            ->first();
        $this->assertNotNull($result3);
        $this->assertEquals('600', $result3->value_current); // 300 * 2
    }

    public function test_pipeline_without_filter_processes_all_entities()
    {
        // Remove filter
        $this->pipeline->update(['entity_filter' => null]);

        $eavWriter = app(EavWriter::class);

        // Create 2 entities
        $entity1 = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'PROD-001',
        ]);
        $eavWriter->upsertVersioned($entity1->id, $this->statusAttribute->id, '1');
        $eavWriter->upsertVersioned($entity1->id, $this->inputAttribute->id, 100);

        $entity2 = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'PROD-002',
        ]);
        $eavWriter->upsertVersioned($entity2->id, $this->statusAttribute->id, '2');
        $eavWriter->upsertVersioned($entity2->id, $this->inputAttribute->id, 200);

        // Execute pipeline
        $executionService = app(PipelineExecutionService::class);
        $stats = $executionService->executeBatch(
            $this->pipeline,
            collect([$entity1->id, $entity2->id])
        );

        // Should process all entities
        $this->assertEquals(2, $stats['processed']);
        $this->assertEquals(0, $stats['skipped']);
        $this->assertEquals(0, $stats['failed']);
    }

    public function test_pipeline_filter_with_in_operator()
    {
        // Update filter to use 'in' operator
        $this->pipeline->update([
            'entity_filter' => [
                'attribute_id' => $this->statusAttribute->id,
                'operator' => 'in',
                'value' => ['1', '2'], // Both enabled and disabled
            ],
        ]);

        $eavWriter = app(EavWriter::class);

        // Create 2 entities
        $entity1 = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'PROD-001',
        ]);
        $eavWriter->upsertVersioned($entity1->id, $this->statusAttribute->id, '1');
        $eavWriter->upsertVersioned($entity1->id, $this->inputAttribute->id, 100);

        $entity2 = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'PROD-002',
        ]);
        $eavWriter->upsertVersioned($entity2->id, $this->statusAttribute->id, '2');
        $eavWriter->upsertVersioned($entity2->id, $this->inputAttribute->id, 200);

        // Execute pipeline
        $executionService = app(PipelineExecutionService::class);
        $stats = $executionService->executeBatch(
            $this->pipeline,
            collect([$entity1->id, $entity2->id])
        );

        // Should process all entities (both match the 'in' filter)
        $this->assertEquals(2, $stats['processed']);
        $this->assertEquals(0, $stats['skipped']);
    }

    public function test_max_entities_limit_applies_after_filtering()
    {
        $eavWriter = app(EavWriter::class);

        // Create 5 entities: 3 enabled, 2 disabled
        $enabledEntities = [];
        for ($i = 1; $i <= 3; $i++) {
            $entity = Entity::factory()->create([
                'entity_type_id' => $this->entityType->id,
                'entity_id' => "PROD-ENABLED-{$i}",
            ]);
            $eavWriter->upsertVersioned($entity->id, $this->statusAttribute->id, '1'); // Enabled
            $eavWriter->upsertVersioned($entity->id, $this->inputAttribute->id, 100 * $i);
            $enabledEntities[] = $entity;
        }

        for ($i = 1; $i <= 2; $i++) {
            $entity = Entity::factory()->create([
                'entity_type_id' => $this->entityType->id,
                'entity_id' => "PROD-DISABLED-{$i}",
            ]);
            $eavWriter->upsertVersioned($entity->id, $this->statusAttribute->id, '2'); // Disabled
            $eavWriter->upsertVersioned($entity->id, $this->inputAttribute->id, 100 * $i);
        }

        // Dispatch job with maxEntities = 2
        \App\Jobs\Pipeline\RunPipelineBatch::dispatch(
            pipeline: $this->pipeline,
            triggeredBy: 'manual',
            maxEntities: 2
        );

        // Process the job synchronously
        \Illuminate\Support\Facades\Queue::fake();
        $job = new \App\Jobs\Pipeline\RunPipelineBatch(
            pipeline: $this->pipeline,
            triggeredBy: 'manual',
            maxEntities: 2,
            force: true // Force to ensure all entities are processed
        );
        $job->handle(app(\App\Services\PipelineExecutionService::class));

        // Should have processed 2 enabled entities (not including disabled ones in the count)
        $run = \App\Models\PipelineRun::latest()->first();
        $this->assertNotNull($run);
        $this->assertEquals(2, $run->entities_processed);

        // Verify only 2 entities got processed
        $processedCount = \DB::table('eav_versioned')
            ->where('attribute_id', $this->targetAttribute->id)
            ->count();
        $this->assertEquals(2, $processedCount);
    }

    public function test_select_attribute_filter_with_multiple_values()
    {
        // Update filter to use 'in' operator with multiple select values
        $this->pipeline->update([
            'entity_filter' => [
                'attribute_id' => $this->statusAttribute->id,
                'operator' => 'in',
                'value' => ['1'], // Array format like it comes from multi-select
            ],
        ]);

        $eavWriter = app(EavWriter::class);

        // Create entities with different statuses
        $entity1 = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'PROD-001',
        ]);
        $eavWriter->upsertVersioned($entity1->id, $this->statusAttribute->id, '1'); // Enabled
        $eavWriter->upsertVersioned($entity1->id, $this->inputAttribute->id, 100);

        $entity2 = Entity::factory()->create([
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'PROD-002',
        ]);
        $eavWriter->upsertVersioned($entity2->id, $this->statusAttribute->id, '2'); // Disabled
        $eavWriter->upsertVersioned($entity2->id, $this->inputAttribute->id, 200);

        // Execute pipeline
        $executionService = app(PipelineExecutionService::class);
        $stats = $executionService->executeBatch(
            $this->pipeline,
            collect([$entity1->id, $entity2->id])
        );

        // Should process 1 entity (status in ['1'])
        $this->assertEquals(1, $stats['processed']);
        $this->assertEquals(1, $stats['skipped']);
    }
}
