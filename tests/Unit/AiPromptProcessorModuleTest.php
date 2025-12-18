<?php

namespace Tests\Unit;

use App\Models\Attribute;
use App\Models\EntityType;
use App\Models\Pipeline;
use App\Models\PipelineModule;
use App\Pipelines\Modules\AiPromptProcessorModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPromptProcessorModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_templates_have_additional_properties_false(): void
    {
        // Use reflection to access the protected constant
        $reflection = new \ReflectionClass(AiPromptProcessorModule::class);
        $schemas = $reflection->getConstant('SCHEMA_TEMPLATES');

        $this->assertIsArray($schemas);
        $this->assertNotEmpty($schemas);

        foreach ($schemas as $templateName => $schema) {
            $this->assertArrayHasKey('additionalProperties', $schema,
                "Schema template '{$templateName}' is missing 'additionalProperties' key");

            $this->assertFalse($schema['additionalProperties'],
                "Schema template '{$templateName}' must have 'additionalProperties' set to false for OpenAI compatibility");
        }
    }

    public function test_schema_templates_have_required_structure(): void
    {
        $reflection = new \ReflectionClass(AiPromptProcessorModule::class);
        $schemas = $reflection->getConstant('SCHEMA_TEMPLATES');

        foreach ($schemas as $templateName => $schema) {
            // Check basic structure
            $this->assertArrayHasKey('type', $schema, "Schema '{$templateName}' missing 'type'");
            $this->assertEquals('object', $schema['type'], "Schema '{$templateName}' must be of type 'object'");

            $this->assertArrayHasKey('properties', $schema, "Schema '{$templateName}' missing 'properties'");
            $this->assertArrayHasKey('required', $schema, "Schema '{$templateName}' missing 'required'");

            // Check required fields
            $properties = $schema['properties'];
            $this->assertArrayHasKey('value', $properties, "Schema '{$templateName}' missing 'value' property");
            $this->assertArrayHasKey('justification', $properties, "Schema '{$templateName}' missing 'justification' property");
            $this->assertArrayHasKey('confidence', $properties, "Schema '{$templateName}' missing 'confidence' property");

            // Check required array
            $this->assertContains('value', $schema['required'], "Schema '{$templateName}' should require 'value'");
            $this->assertContains('justification', $schema['required'], "Schema '{$templateName}' should require 'justification'");
            $this->assertContains('confidence', $schema['required'], "Schema '{$templateName}' should require 'confidence'");
        }
    }

    public function test_module_validates_schema_is_valid_json(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create(['entity_type_id' => $entityType->id]);
        $pipeline = Pipeline::create([
            'attribute_id' => $attribute->id,
            'entity_type_id' => $entityType->id,
        ]);

        $pipelineModule = PipelineModule::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'module_class' => AiPromptProcessorModule::class,
            'settings' => [
                'prompt' => 'Test prompt',
                'output_schema' => 'invalid json',
                'schema_template' => 'text',
                'model' => 'gpt-4o-mini',
            ],
        ]);

        $module = new AiPromptProcessorModule($pipelineModule);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $module->validateSettings($pipelineModule->settings);
    }

    public function test_module_validates_schema_is_object_type(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create(['entity_type_id' => $entityType->id]);
        $pipeline = Pipeline::create([
            'attribute_id' => $attribute->id,
            'entity_type_id' => $entityType->id,
        ]);

        $pipelineModule = PipelineModule::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'module_class' => AiPromptProcessorModule::class,
            'settings' => [
                'prompt' => 'Test prompt',
                'output_schema' => '{"type":"string"}',
                'schema_template' => 'text',
                'model' => 'gpt-4o-mini',
            ],
        ]);

        $module = new AiPromptProcessorModule($pipelineModule);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $module->validateSettings($pipelineModule->settings);
    }

    public function test_module_accepts_valid_schema_with_additional_properties(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create(['entity_type_id' => $entityType->id]);
        $pipeline = Pipeline::create([
            'attribute_id' => $attribute->id,
            'entity_type_id' => $entityType->id,
        ]);

        $validSchema = json_encode([
            'type' => 'object',
            'properties' => [
                'value' => ['type' => 'string'],
                'justification' => ['type' => 'string'],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
            ],
            'required' => ['value', 'justification', 'confidence'],
            'additionalProperties' => false,
        ]);

        $pipelineModule = PipelineModule::create([
            'pipeline_id' => $pipeline->id,
            'order' => 1,
            'module_class' => AiPromptProcessorModule::class,
            'settings' => [
                'prompt' => 'Test prompt',
                'output_schema' => $validSchema,
                'schema_template' => 'text',
                'model' => 'gpt-4o-mini',
            ],
        ]);

        $module = new AiPromptProcessorModule($pipelineModule);

        // Should not throw exception
        $validated = $module->validateSettings($pipelineModule->settings);

        $this->assertArrayHasKey('output_schema', $validated);
        $this->assertEquals($validSchema, $validated['output_schema']);
    }

    public function test_all_template_schemas_are_openai_compatible(): void
    {
        $reflection = new \ReflectionClass(AiPromptProcessorModule::class);
        $schemas = $reflection->getConstant('SCHEMA_TEMPLATES');

        foreach ($schemas as $templateName => $schema) {
            // Verify it can be encoded to JSON
            $jsonSchema = json_encode($schema);
            $this->assertNotFalse($jsonSchema, "Schema '{$templateName}' cannot be JSON encoded");

            // Verify required OpenAI fields
            $this->assertArrayHasKey('type', $schema);
            $this->assertArrayHasKey('properties', $schema);
            $this->assertArrayHasKey('additionalProperties', $schema);
            $this->assertFalse($schema['additionalProperties'],
                "OpenAI requires additionalProperties to be false in '{$templateName}' schema");

            // Verify it's a properly structured object schema
            $decoded = json_decode($jsonSchema, true);
            $this->assertEquals($schema, $decoded, "Schema '{$templateName}' doesn't round-trip through JSON");
        }
    }
}
