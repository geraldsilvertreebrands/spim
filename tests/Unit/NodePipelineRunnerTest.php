<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class NodePipelineRunnerTest extends TestCase
{
    protected string $helperPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->helperPath = __DIR__ . '/../../resources/node/pipeline-runner.cjs';

        if (!file_exists($this->helperPath)) {
            $this->markTestSkipped('Node helper script not found');
        }

        // Check if node is available
        $process = new Process(['which', 'node']);
        $process->run();
        if (!$process->isSuccessful()) {
            $this->markTestSkipped('Node.js not installed');
        }
    }

    public function test_node_helper_executes_simple_calculation(): void
    {
        $payload = json_encode([
            'code' => 'return { value: 42, confidence: 1.0 };',
            'items' => [
                [
                    'index' => 0,
                    'entityId' => 'test-entity',
                    'inputs' => [],
                ],
            ],
        ]);

        $process = new Process(['node', $this->helperPath]);
        $process->setInput($payload);
        $process->setTimeout(5);
        $process->run();

        $this->assertTrue($process->isSuccessful(), 'Process failed: ' . $process->getErrorOutput());

        $result = json_decode($process->getOutput(), true);
        $this->assertArrayHasKey('results', $result);
        $this->assertCount(1, $result['results']);
        $this->assertEquals(42, $result['results'][0]['value']);
        $this->assertEquals(1.0, $result['results'][0]['confidence']);
    }

    public function test_node_helper_can_access_inputs(): void
    {
        $payload = json_encode([
            'code' => 'return { value: $json.price * 2, confidence: 1.0 };',
            'items' => [
                [
                    'index' => 0,
                    'entityId' => 'test-entity',
                    'inputs' => ['price' => 50],
                ],
            ],
        ]);

        $process = new Process(['node', $this->helperPath]);
        $process->setInput($payload);
        $process->setTimeout(5);
        $process->run();

        $this->assertTrue($process->isSuccessful());

        $result = json_decode($process->getOutput(), true);
        $this->assertEquals(100, $result['results'][0]['value']);
    }

    public function test_node_helper_processes_multiple_items(): void
    {
        $payload = json_encode([
            'code' => 'return { value: $json.number * 10, confidence: 1.0 };',
            'items' => [
                ['index' => 0, 'entityId' => 'entity-1', 'inputs' => ['number' => 1]],
                ['index' => 1, 'entityId' => 'entity-2', 'inputs' => ['number' => 2]],
                ['index' => 2, 'entityId' => 'entity-3', 'inputs' => ['number' => 3]],
            ],
        ]);

        $process = new Process(['node', $this->helperPath]);
        $process->setInput($payload);
        $process->setTimeout(5);
        $process->run();

        $this->assertTrue($process->isSuccessful());

        $result = json_decode($process->getOutput(), true);
        $this->assertCount(3, $result['results']);
        $this->assertEquals(10, $result['results'][0]['value']);
        $this->assertEquals(20, $result['results'][1]['value']);
        $this->assertEquals(30, $result['results'][2]['value']);
    }

    public function test_node_helper_handles_string_concatenation(): void
    {
        $payload = json_encode([
            'code' => 'return { value: $json.first + " " + $json.last, justification: "Combined names", confidence: 1.0 };',
            'items' => [
                [
                    'index' => 0,
                    'entityId' => 'test-entity',
                    'inputs' => ['first' => 'John', 'last' => 'Doe'],
                ],
            ],
        ]);

        $process = new Process(['node', $this->helperPath]);
        $process->setInput($payload);
        $process->setTimeout(5);
        $process->run();

        $this->assertTrue($process->isSuccessful());

        $result = json_decode($process->getOutput(), true);
        $this->assertEquals('John Doe', $result['results'][0]['value']);
        $this->assertEquals('Combined names', $result['results'][0]['justification']);
    }

    public function test_node_helper_handles_errors_gracefully(): void
    {
        $payload = json_encode([
            'code' => 'throw new Error("Test error");',
            'items' => [
                [
                    'index' => 0,
                    'entityId' => 'test-entity',
                    'inputs' => [],
                ],
            ],
        ]);

        $process = new Process(['node', $this->helperPath]);
        $process->setInput($payload);
        $process->setTimeout(5);
        $process->run();

        $this->assertTrue($process->isSuccessful());

        $result = json_decode($process->getOutput(), true);
        $this->assertArrayHasKey('error', $result['results'][0]);
        $this->assertStringContainsString('Test error', $result['results'][0]['error']);
    }

    public function test_node_helper_can_use_math_functions(): void
    {
        $payload = json_encode([
            'code' => 'return { value: Math.round($json.price * 1.1), confidence: 0.95 };',
            'items' => [
                [
                    'index' => 0,
                    'entityId' => 'test-entity',
                    'inputs' => ['price' => 99],
                ],
            ],
        ]);

        $process = new Process(['node', $this->helperPath]);
        $process->setInput($payload);
        $process->setTimeout(5);
        $process->run();

        $this->assertTrue($process->isSuccessful());

        $result = json_decode($process->getOutput(), true);
        $this->assertEquals(109, $result['results'][0]['value']);
        $this->assertEquals(0.95, $result['results'][0]['confidence']);
    }

    public function test_node_helper_auto_wraps_simple_values(): void
    {
        $payload = json_encode([
            'code' => 'return "simple string";',
            'items' => [
                [
                    'index' => 0,
                    'entityId' => 'test-entity',
                    'inputs' => [],
                ],
            ],
        ]);

        $process = new Process(['node', $this->helperPath]);
        $process->setInput($payload);
        $process->setTimeout(5);
        $process->run();

        $this->assertTrue($process->isSuccessful());

        $result = json_decode($process->getOutput(), true);
        $this->assertEquals('simple string', $result['results'][0]['value']);
        $this->assertEquals(1.0, $result['results'][0]['confidence']);
    }
}

