<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\Pipeline;
use App\Models\PipelineEval;
use App\Models\PipelineModule;
use App\Models\PipelineRun;
use App\Pipelines\Modules\AttributesSourceModule;
use Tests\TestCase;

class PipelineModelTest extends TestCase
{
    public function test_can_create_pipeline(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create(['entity_type_id' => $entityType->id]);

        $pipeline = Pipeline::create([
            'attribute_id' => $attribute->id,
            'entity_type_id' => $entityType->id,
            'name' => 'Test Pipeline',
        ]);

        $this->assertDatabaseHas('pipelines', [
            'id' => $pipeline->id,
            'attribute_id' => $attribute->id,
            'pipeline_version' => 1,
        ]);
    }

    public function test_pipeline_has_relationships(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create(['entity_type_id' => $entityType->id]);

        $pipeline = Pipeline::create([
            'attribute_id' => $attribute->id,
            'entity_type_id' => $entityType->id,
        ]);

        $this->assertInstanceOf(Attribute::class, $pipeline->attribute);
        $this->assertInstanceOf(EntityType::class, $pipeline->entityType);
    }

    public function test_can_add_modules_to_pipeline(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create(['entity_type_id' => $entityType->id]);

        $pipeline = Pipeline::create([
            'attribute_id' => $attribute->id,
            'entity_type_id' => $entityType->id,
        ]);

        $module = $pipeline->modules()->create([
            'order' => 1,
            'module_class' => AttributesSourceModule::class,
            'settings' => ['attribute_ids' => [1, 2]],
        ]);

        $this->assertDatabaseHas('pipeline_modules', [
            'pipeline_id' => $pipeline->id,
            'order' => 1,
        ]);

        $this->assertEquals([1, 2], $module->fresh()->settings['attribute_ids']);
    }

    public function test_module_changes_bump_pipeline_version(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create(['entity_type_id' => $entityType->id]);

        $pipeline = Pipeline::create([
            'attribute_id' => $attribute->id,
            'entity_type_id' => $entityType->id,
        ]);

        $this->assertEquals(1, $pipeline->pipeline_version);

        $module = $pipeline->modules()->create([
            'order' => 1,
            'module_class' => AttributesSourceModule::class,
            'settings' => [],
        ]);

        $pipeline->refresh();
        $this->assertEquals(2, $pipeline->pipeline_version);

        $module->update(['settings' => ['new' => 'value']]);

        $pipeline->refresh();
        $this->assertEquals(3, $pipeline->pipeline_version);
    }

    public function test_can_create_pipeline_run(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create(['entity_type_id' => $entityType->id]);

        $pipeline = Pipeline::create([
            'attribute_id' => $attribute->id,
            'entity_type_id' => $entityType->id,
        ]);

        $run = $pipeline->runs()->create([
            'pipeline_version' => 1,
            'triggered_by' => 'manual',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $this->assertDatabaseHas('pipeline_runs', [
            'pipeline_id' => $pipeline->id,
            'status' => 'running',
        ]);
    }

    public function test_pipeline_run_can_be_marked_completed(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create(['entity_type_id' => $entityType->id]);

        $pipeline = Pipeline::create([
            'attribute_id' => $attribute->id,
            'entity_type_id' => $entityType->id,
        ]);

        $run = $pipeline->runs()->create([
            'pipeline_version' => 1,
            'triggered_by' => 'manual',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $run->markCompleted();

        $this->assertEquals('completed', $run->fresh()->status);
        $this->assertNotNull($run->fresh()->completed_at);
    }

    public function test_pipeline_run_can_track_tokens(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create(['entity_type_id' => $entityType->id]);

        $pipeline = Pipeline::create([
            'attribute_id' => $attribute->id,
            'entity_type_id' => $entityType->id,
        ]);

        $run = $pipeline->runs()->create([
            'pipeline_version' => 1,
            'triggered_by' => 'manual',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $run->addTokens(100, 50);
        $run->addTokens(200, 75);

        $this->assertEquals(300, $run->fresh()->tokens_in);
        $this->assertEquals(125, $run->fresh()->tokens_out);
    }

    public function test_can_create_eval(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create(['entity_type_id' => $entityType->id]);
        $entity = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        $pipeline = Pipeline::create([
            'attribute_id' => $attribute->id,
            'entity_type_id' => $entityType->id,
        ]);

        $eval = $pipeline->evals()->create([
            'entity_id' => $entity->id,
            'desired_output' => ['value' => 'expected'],
            'notes' => 'Test case',
        ]);

        $this->assertDatabaseHas('pipeline_evals', [
            'pipeline_id' => $pipeline->id,
            'entity_id' => $entity->id,
        ]);
    }

    public function test_eval_can_check_if_passing(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create(['entity_type_id' => $entityType->id]);
        $entity = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        $pipeline = Pipeline::create([
            'attribute_id' => $attribute->id,
            'entity_type_id' => $entityType->id,
        ]);

        $eval = $pipeline->evals()->create([
            'entity_id' => $entity->id,
            'desired_output' => ['value' => 'expected'],
            'actual_output' => ['value' => 'expected'],
        ]);

        $this->assertTrue($eval->isPassing());

        $eval->update(['actual_output' => ['value' => 'different']]);
        $this->assertFalse($eval->fresh()->isPassing());
    }

    public function test_pipeline_can_calculate_token_usage(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create(['entity_type_id' => $entityType->id]);

        $pipeline = Pipeline::create([
            'attribute_id' => $attribute->id,
            'entity_type_id' => $entityType->id,
        ]);

        // Create some runs with token data
        $pipeline->runs()->create([
            'pipeline_version' => 1,
            'triggered_by' => 'schedule',
            'status' => 'completed',
            'started_at' => now()->subDays(5),
            'completed_at' => now()->subDays(5),
            'tokens_in' => 100,
            'tokens_out' => 50,
            'entities_processed' => 10,
        ]);

        $pipeline->runs()->create([
            'pipeline_version' => 1,
            'triggered_by' => 'schedule',
            'status' => 'completed',
            'started_at' => now()->subDays(10),
            'completed_at' => now()->subDays(10),
            'tokens_in' => 200,
            'tokens_out' => 100,
            'entities_processed' => 20,
        ]);

        $usage = $pipeline->getTokenUsage(30);

        $this->assertEquals(450, $usage['total_tokens']); // 100+50+200+100
        $this->assertEquals(300, $usage['tokens_in']);
        $this->assertEquals(150, $usage['tokens_out']);
        $this->assertEquals(15, $usage['avg_tokens_per_entity']); // 450/30
    }
}

