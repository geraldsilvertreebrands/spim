<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\Pipeline;
use App\Pipelines\Modules\AttributesSourceModule;
use App\Pipelines\Modules\CalculationProcessorModule;
use App\Services\EavWriter;
use App\Services\PipelineExecutionService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PipelineExecutionTest extends TestCase
{
    protected PipelineExecutionService $service;

    protected EavWriter $eavWriter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PipelineExecutionService::class);
        $this->eavWriter = app(EavWriter::class);
    }

    public function test_can_execute_pipeline_for_single_entity(): void
    {
        $entityType = EntityType::factory()->create();
        $inputAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'price',
            'data_type' => 'integer',
        ]);
        $outputAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'doubled_price',
            'data_type' => 'integer',
        ]);

        $entity = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        // Set input value
        $this->eavWriter->upsertVersioned($entity->id, $inputAttr->id, '100');

        // Create pipeline
        $pipeline = Pipeline::create([
            'attribute_id' => $outputAttr->id,
            'entity_type_id' => $entityType->id,
        ]);

        // Add source module
        $pipeline->modules()->create([
            'order' => 1,
            'module_class' => AttributesSourceModule::class,
            'settings' => ['attribute_ids' => [$inputAttr->id]],
        ]);

        // Add calculation module that doubles the price
        $pipeline->modules()->create([
            'order' => 2,
            'module_class' => CalculationProcessorModule::class,
            'settings' => [
                'code' => 'return { value: ($json.price || 0) * 2, confidence: 1.0 };',
            ],
        ]);

        // Execute
        $stats = $this->service->executeForSingleEntity($pipeline, $entity->id);

        $this->assertEquals(1, $stats['processed']);
        $this->assertEquals(0, $stats['failed']);

        // Check result
        $result = DB::table('eav_versioned')
            ->where('entity_id', $entity->id)
            ->where('attribute_id', $outputAttr->id)
            ->first();

        $this->assertNotNull($result);
        $this->assertEquals('200', $result->value_current);
        $this->assertEquals(1, $result->pipeline_version);
    }

    public function test_skips_entities_with_unchanged_inputs(): void
    {
        $entityType = EntityType::factory()->create();
        $inputAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'input',
        ]);
        $outputAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'output',
        ]);

        $entity = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        $this->eavWriter->upsertVersioned($entity->id, $inputAttr->id, 'test value');

        $pipeline = Pipeline::create([
            'attribute_id' => $outputAttr->id,
            'entity_type_id' => $entityType->id,
        ]);

        $pipeline->modules()->create([
            'order' => 1,
            'module_class' => AttributesSourceModule::class,
            'settings' => ['attribute_ids' => [$inputAttr->id]],
        ]);

        $pipeline->modules()->create([
            'order' => 2,
            'module_class' => CalculationProcessorModule::class,
            'settings' => ['code' => 'return { value: "processed", confidence: 1.0 };'],
        ]);

        // First execution
        $stats1 = $this->service->executeForSingleEntity($pipeline, $entity->id);
        $this->assertEquals(1, $stats1['processed']);
        $this->assertEquals(0, $stats1['skipped']);

        // Second execution with no changes - should skip
        $stats2 = $this->service->executeForSingleEntity($pipeline, $entity->id);
        $this->assertEquals(0, $stats2['processed']);
        $this->assertEquals(1, $stats2['skipped']);
    }

    public function test_reprocesses_when_pipeline_version_changes(): void
    {
        $entityType = EntityType::factory()->create();
        $inputAttr = Attribute::factory()->create(['entity_type_id' => $entityType->id]);
        $outputAttr = Attribute::factory()->create(['entity_type_id' => $entityType->id]);

        $entity = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $this->eavWriter->upsertVersioned($entity->id, $inputAttr->id, 'test');

        $pipeline = Pipeline::create([
            'attribute_id' => $outputAttr->id,
            'entity_type_id' => $entityType->id,
        ]);

        $pipeline->modules()->create([
            'order' => 1,
            'module_class' => AttributesSourceModule::class,
            'settings' => ['attribute_ids' => [$inputAttr->id]],
        ]);

        $pipeline->modules()->create([
            'order' => 2,
            'module_class' => CalculationProcessorModule::class,
            'settings' => ['code' => 'return { value: "v1", confidence: 1.0 };'],
        ]);

        // First run
        $this->service->executeForSingleEntity($pipeline, $entity->id);

        // Update module (bumps version)
        $pipeline->modules()->first()->update(['settings' => ['attribute_ids' => [$inputAttr->id]]]);
        $pipeline->refresh();

        // Second run - should process even though input unchanged
        $stats = $this->service->executeForSingleEntity($pipeline, $entity->id);
        $this->assertEquals(1, $stats['processed']);
    }

    public function test_can_execute_batch(): void
    {
        $entityType = EntityType::factory()->create();
        $inputAttr = Attribute::factory()->create(['entity_type_id' => $entityType->id]);
        $outputAttr = Attribute::factory()->create(['entity_type_id' => $entityType->id]);

        $entities = Entity::factory()->count(3)->create(['entity_type_id' => $entityType->id]);

        foreach ($entities as $entity) {
            $this->eavWriter->upsertVersioned($entity->id, $inputAttr->id, 'test');
        }

        $pipeline = Pipeline::create([
            'attribute_id' => $outputAttr->id,
            'entity_type_id' => $entityType->id,
        ]);

        $pipeline->modules()->create([
            'order' => 1,
            'module_class' => AttributesSourceModule::class,
            'settings' => ['attribute_ids' => [$inputAttr->id]],
        ]);

        $pipeline->modules()->create([
            'order' => 2,
            'module_class' => CalculationProcessorModule::class,
            'settings' => ['code' => 'return { value: "batch", confidence: 1.0 };'],
        ]);

        $stats = $this->service->executeBatch($pipeline, $entities->pluck('id'));

        $this->assertEquals(3, $stats['processed']);
        $this->assertEquals(0, $stats['failed']);
    }

    public function test_calculates_input_hash_correctly(): void
    {
        $entityType = EntityType::factory()->create();
        $inputAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'test_input',
        ]);
        $outputAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'test_output',
        ]);

        $entity = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $this->eavWriter->upsertVersioned($entity->id, $inputAttr->id, 'test');

        $pipeline = Pipeline::create([
            'attribute_id' => $outputAttr->id,
            'entity_type_id' => $entityType->id,
        ]);

        $pipeline->modules()->create([
            'order' => 1,
            'module_class' => AttributesSourceModule::class,
            'settings' => ['attribute_ids' => [$inputAttr->id]],
        ]);

        $pipeline->modules()->create([
            'order' => 2,
            'module_class' => CalculationProcessorModule::class,
            'settings' => ['code' => 'return { value: $json.test_input + "_processed", confidence: 1.0 };'],
        ]);

        // First run
        $stats1 = $this->service->executeForSingleEntity($pipeline, $entity->id);
        $this->assertEquals(1, $stats1['processed']);

        $result1 = DB::table('eav_versioned')
            ->where('entity_id', $entity->id)
            ->where('attribute_id', $outputAttr->id)
            ->first();

        $hash1 = $result1->input_hash;
        $this->assertNotNull($hash1);

        // Change input value
        $this->eavWriter->upsertVersioned($entity->id, $inputAttr->id, 'changed');

        // Second run - should detect change and reprocess
        $stats2 = $this->service->executeForSingleEntity($pipeline, $entity->id);
        $this->assertEquals(1, $stats2['processed'], 'Should reprocess when input changes');
        $this->assertEquals(0, $stats2['skipped']);

        $result2 = DB::table('eav_versioned')
            ->where('entity_id', $entity->id)
            ->where('attribute_id', $outputAttr->id)
            ->first();

        $hash2 = $result2->input_hash;

        // Hashes should be different because input changed
        $this->assertNotEquals($hash1, $hash2, 'Input hash should change when inputs change');

        // And the output value should also have changed
        $this->assertEquals('changed_processed', $result2->value_current);
    }
}
