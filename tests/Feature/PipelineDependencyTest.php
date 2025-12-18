<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\EntityType;
use App\Models\Pipeline;
use App\Pipelines\Modules\AiPromptProcessorModule;
use App\Pipelines\Modules\AttributesSourceModule;
use App\Services\PipelineDependencyService;
use Tests\TestCase;

class PipelineDependencyTest extends TestCase
{
    protected PipelineDependencyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PipelineDependencyService::class);
    }

    public function test_can_get_execution_order_for_independent_pipelines(): void
    {
        $entityType = EntityType::factory()->create();
        $attr1 = Attribute::factory()->create(['entity_type_id' => $entityType->id, 'name' => 'attr1']);
        $attr2 = Attribute::factory()->create(['entity_type_id' => $entityType->id, 'name' => 'attr2']);
        $attr3 = Attribute::factory()->create(['entity_type_id' => $entityType->id, 'name' => 'attr3']);

        // Pipeline 1: uses attr1
        $pipeline1 = Pipeline::create([
            'attribute_id' => $attr2->id,
            'entity_type_id' => $entityType->id,
        ]);
        $pipeline1->modules()->create([
            'order' => 1,
            'module_class' => AttributesSourceModule::class,
            'settings' => ['attribute_ids' => [$attr1->id]],
        ]);
        $pipeline1->modules()->create([
            'order' => 2,
            'module_class' => AiPromptProcessorModule::class,
            'settings' => ['prompt' => 'test', 'output_schema' => '{}', 'schema_template' => 'text', 'model' => 'gpt-4o-mini'],
        ]);

        // Pipeline 2: independent (uses attr1 also)
        $pipeline2 = Pipeline::create([
            'attribute_id' => $attr3->id,
            'entity_type_id' => $entityType->id,
        ]);
        $pipeline2->modules()->create([
            'order' => 1,
            'module_class' => AttributesSourceModule::class,
            'settings' => ['attribute_ids' => [$attr1->id]],
        ]);
        $pipeline2->modules()->create([
            'order' => 2,
            'module_class' => AiPromptProcessorModule::class,
            'settings' => ['prompt' => 'test', 'output_schema' => '{}', 'schema_template' => 'text', 'model' => 'gpt-4o-mini'],
        ]);

        $ordered = $this->service->getExecutionOrder($entityType);

        $this->assertCount(2, $ordered);
    }

    public function test_resolves_dependent_pipeline_order(): void
    {
        $entityType = EntityType::factory()->create();
        $attr1 = Attribute::factory()->create(['entity_type_id' => $entityType->id, 'name' => 'base']);
        $attr2 = Attribute::factory()->create(['entity_type_id' => $entityType->id, 'name' => 'derived']);

        // Pipeline 1: generates attr2 from attr1
        $pipeline1 = Pipeline::create([
            'attribute_id' => $attr2->id,
            'entity_type_id' => $entityType->id,
        ]);
        $pipeline1->modules()->create([
            'order' => 1,
            'module_class' => AttributesSourceModule::class,
            'settings' => ['attribute_ids' => [$attr1->id]],
        ]);
        $pipeline1->modules()->create([
            'order' => 2,
            'module_class' => AiPromptProcessorModule::class,
            'settings' => ['prompt' => 'test', 'output_schema' => '{}', 'schema_template' => 'text', 'model' => 'gpt-4o-mini'],
        ]);

        // Pipeline 2: generates attr3 from attr2 (depends on pipeline1)
        $attr3 = Attribute::factory()->create(['entity_type_id' => $entityType->id, 'name' => 'further_derived']);
        $pipeline2 = Pipeline::create([
            'attribute_id' => $attr3->id,
            'entity_type_id' => $entityType->id,
        ]);
        $pipeline2->modules()->create([
            'order' => 1,
            'module_class' => AttributesSourceModule::class,
            'settings' => ['attribute_ids' => [$attr2->id]], // Depends on pipeline1's output
        ]);
        $pipeline2->modules()->create([
            'order' => 2,
            'module_class' => AiPromptProcessorModule::class,
            'settings' => ['prompt' => 'test', 'output_schema' => '{}', 'schema_template' => 'text', 'model' => 'gpt-4o-mini'],
        ]);

        $ordered = $this->service->getExecutionOrder($entityType);

        $this->assertCount(2, $ordered);
        // Pipeline 1 should come before Pipeline 2
        $this->assertEquals($pipeline1->id, $ordered->first()->id);
        $this->assertEquals($pipeline2->id, $ordered->last()->id);
    }

    public function test_detects_circular_dependencies(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/circular/i');

        $entityType = EntityType::factory()->create();
        $attr1 = Attribute::factory()->create(['entity_type_id' => $entityType->id, 'name' => 'attr1']);
        $attr2 = Attribute::factory()->create(['entity_type_id' => $entityType->id, 'name' => 'attr2']);

        // Pipeline 1: attr1 depends on attr2
        $pipeline1 = Pipeline::create([
            'attribute_id' => $attr1->id,
            'entity_type_id' => $entityType->id,
        ]);
        $pipeline1->modules()->create([
            'order' => 1,
            'module_class' => AttributesSourceModule::class,
            'settings' => ['attribute_ids' => [$attr2->id]],
        ]);
        $pipeline1->modules()->create([
            'order' => 2,
            'module_class' => AiPromptProcessorModule::class,
            'settings' => ['prompt' => 'test', 'output_schema' => '{}', 'schema_template' => 'text', 'model' => 'gpt-4o-mini'],
        ]);

        // Pipeline 2: attr2 depends on attr1 (creates cycle)
        $pipeline2 = Pipeline::create([
            'attribute_id' => $attr2->id,
            'entity_type_id' => $entityType->id,
        ]);
        $pipeline2->modules()->create([
            'order' => 1,
            'module_class' => AttributesSourceModule::class,
            'settings' => ['attribute_ids' => [$attr1->id]], // Circular!
        ]);
        $pipeline2->modules()->create([
            'order' => 2,
            'module_class' => AiPromptProcessorModule::class,
            'settings' => ['prompt' => 'test', 'output_schema' => '{}', 'schema_template' => 'text', 'model' => 'gpt-4o-mini'],
        ]);

        // This should throw
        $this->service->getExecutionOrder($entityType);
    }

    public function test_can_get_dependencies_for_pipeline(): void
    {
        $entityType = EntityType::factory()->create();
        $attr1 = Attribute::factory()->create(['entity_type_id' => $entityType->id]);
        $attr2 = Attribute::factory()->create(['entity_type_id' => $entityType->id]);
        $attr3 = Attribute::factory()->create(['entity_type_id' => $entityType->id]);

        $pipeline = Pipeline::create([
            'attribute_id' => $attr3->id,
            'entity_type_id' => $entityType->id,
        ]);

        $pipeline->modules()->create([
            'order' => 1,
            'module_class' => AttributesSourceModule::class,
            'settings' => ['attribute_ids' => [$attr1->id, $attr2->id]],
        ]);

        $pipeline->modules()->create([
            'order' => 2,
            'module_class' => AiPromptProcessorModule::class,
            'settings' => ['prompt' => 'test', 'output_schema' => '{}', 'schema_template' => 'text', 'model' => 'gpt-4o-mini'],
        ]);

        $dependencies = $this->service->getDependencies($pipeline);

        $this->assertCount(2, $dependencies);
        $this->assertTrue($dependencies->contains($attr1->id));
        $this->assertTrue($dependencies->contains($attr2->id));
    }

    public function test_validates_pipeline_for_cycles(): void
    {
        $entityType = EntityType::factory()->create();
        $attr1 = Attribute::factory()->create(['entity_type_id' => $entityType->id]);

        // Self-referencing pipeline
        $pipeline = Pipeline::create([
            'attribute_id' => $attr1->id,
            'entity_type_id' => $entityType->id,
        ]);

        $pipeline->modules()->create([
            'order' => 1,
            'module_class' => AttributesSourceModule::class,
            'settings' => ['attribute_ids' => [$attr1->id]], // Depends on itself!
        ]);

        $pipeline->modules()->create([
            'order' => 2,
            'module_class' => AiPromptProcessorModule::class,
            'settings' => ['prompt' => 'test', 'output_schema' => '{}', 'schema_template' => 'text', 'model' => 'gpt-4o-mini'],
        ]);

        $errors = $this->service->validatePipeline($pipeline);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('circular', strtolower($errors[0]));
    }
}
