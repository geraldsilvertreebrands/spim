<?php

namespace Tests\Unit;

use App\Pipelines\Data\PipelineContext;
use App\Pipelines\Data\PipelineModuleDefinition;
use App\Pipelines\Data\PipelineResult;
use PHPUnit\Framework\TestCase;

class PipelineDataClassesTest extends TestCase
{
    public function test_pipeline_context_stores_data(): void
    {
        $context = new PipelineContext(
            entityId: 'test-entity-id',
            attributeId: 1,
            inputs: ['name' => 'Test', 'price' => '10.00'],
            pipelineVersion: 1,
            settings: ['key' => 'value'],
        );

        $this->assertEquals('test-entity-id', $context->entityId);
        $this->assertEquals(1, $context->attributeId);
        $this->assertEquals('Test', $context->input('name'));
        $this->assertEquals('default', $context->input('missing', 'default'));
        $this->assertEquals('value', $context->setting('key'));
    }

    public function test_pipeline_context_can_merge_inputs(): void
    {
        $context = new PipelineContext(
            entityId: 'test-id',
            attributeId: 1,
            inputs: ['a' => '1'],
            pipelineVersion: 1,
            settings: [],
        );

        $newContext = $context->mergeInputs(['b' => '2']);

        $this->assertEquals('1', $newContext->input('a'));
        $this->assertEquals('2', $newContext->input('b'));
    }

    public function test_pipeline_result_ok(): void
    {
        $result = PipelineResult::ok(
            value: 'test value',
            confidence: 0.95,
            justification: 'because reasons'
        );

        $this->assertTrue($result->isOk());
        $this->assertFalse($result->isError());
        $this->assertEquals('test value', $result->value);
        $this->assertEquals(0.95, $result->confidence);
        $this->assertEquals('because reasons', $result->justification);
    }

    public function test_pipeline_result_error(): void
    {
        $result = PipelineResult::error('Something went wrong', ['detail 1', 'detail 2']);

        $this->assertTrue($result->isError());
        $this->assertFalse($result->isOk());
        $this->assertEquals('Something went wrong', $result->getFirstError());
        $this->assertStringContainsString('Something went wrong', $result->getErrorMessages());
    }

    public function test_pipeline_result_skipped(): void
    {
        $result = PipelineResult::skipped('Not applicable');

        $this->assertTrue($result->isSkipped());
        $this->assertFalse($result->isOk());
        $this->assertEquals('Not applicable', $result->getFirstError());
    }

    public function test_pipeline_module_definition_validates_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PipelineModuleDefinition(
            id: 'test',
            label: 'Test',
            description: 'Test module',
            type: 'invalid' // Should be 'source' or 'processor'
        );
    }

    public function test_pipeline_module_definition_type_checks(): void
    {
        $source = new PipelineModuleDefinition(
            id: 'test',
            label: 'Test',
            description: 'Test',
            type: 'source'
        );

        $processor = new PipelineModuleDefinition(
            id: 'test2',
            label: 'Test2',
            description: 'Test2',
            type: 'processor'
        );

        $this->assertTrue($source->isSource());
        $this->assertFalse($source->isProcessor());
        $this->assertTrue($processor->isProcessor());
        $this->assertFalse($processor->isSource());
    }
}

