<?php

namespace Tests\Unit;

use App\Pipelines\Modules\AiPromptProcessorModule;
use App\Pipelines\Modules\AttributesSourceModule;
use App\Pipelines\Modules\CalculationProcessorModule;
use App\Pipelines\PipelineModuleRegistry;
use PHPUnit\Framework\TestCase;

class PipelineModuleRegistryTest extends TestCase
{
    protected PipelineModuleRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new PipelineModuleRegistry;
    }

    public function test_can_register_module(): void
    {
        $this->registry->register(AttributesSourceModule::class);

        $this->assertTrue($this->registry->has('attributes_source'));
    }

    public function test_can_get_module_definition(): void
    {
        $this->registry->register(AttributesSourceModule::class);

        $definition = $this->registry->getDefinition('attributes_source');

        $this->assertEquals('attributes_source', $definition->id);
        $this->assertEquals('Attributes', $definition->label);
        $this->assertEquals('source', $definition->type);
    }

    public function test_can_filter_sources(): void
    {
        $this->registry->register(AttributesSourceModule::class);
        $this->registry->register(AiPromptProcessorModule::class);

        $sources = $this->registry->sources();

        $this->assertCount(1, $sources);
        $this->assertEquals('source', $sources->first()->type);
    }

    public function test_can_filter_processors(): void
    {
        $this->registry->register(AttributesSourceModule::class);
        $this->registry->register(AiPromptProcessorModule::class);
        $this->registry->register(CalculationProcessorModule::class);

        $processors = $this->registry->processors();

        $this->assertCount(2, $processors);
        $this->assertTrue($processors->every(fn ($def) => $def->type === 'processor'));
    }

    public function test_validates_pipeline_structure(): void
    {
        $this->registry->register(AttributesSourceModule::class);
        $this->registry->register(AiPromptProcessorModule::class);

        // Mock module collection with source first
        $modules = collect([
            (object) [
                'module_class' => AttributesSourceModule::class,
                'order' => 1,
            ],
            (object) [
                'module_class' => AiPromptProcessorModule::class,
                'order' => 2,
            ],
        ]);

        $errors = $this->registry->validatePipeline($modules);

        $this->assertEmpty($errors);
    }

    public function test_rejects_pipeline_without_source_first(): void
    {
        $this->registry->register(AttributesSourceModule::class);
        $this->registry->register(AiPromptProcessorModule::class);

        // Processor first - invalid
        $modules = collect([
            (object) [
                'module_class' => AiPromptProcessorModule::class,
                'order' => 1,
            ],
        ]);

        $errors = $this->registry->validatePipeline($modules);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('First module must be a source', $errors[0]);
    }

    public function test_rejects_pipeline_without_processor(): void
    {
        $this->registry->register(AttributesSourceModule::class);

        // Only source, no processor
        $modules = collect([
            (object) [
                'module_class' => AttributesSourceModule::class,
                'order' => 1,
            ],
        ]);

        $errors = $this->registry->validatePipeline($modules);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('at least one processor', $errors[0]);
    }

    public function test_throws_exception_for_unregistered_module(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->registry->getDefinition('nonexistent');
    }
}
