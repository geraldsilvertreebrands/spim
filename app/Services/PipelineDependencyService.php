<?php

namespace App\Services;

use App\Models\EntityType;
use App\Models\Pipeline;
use App\Pipelines\PipelineModuleRegistry;
use Illuminate\Support\Collection;

class PipelineDependencyService
{
    public function __construct(
        protected PipelineModuleRegistry $registry,
    ) {
    }

    /**
     * Get execution order for all pipelines in an entity type using Kahn's algorithm
     *
     * @param EntityType $entityType
     * @return Collection<Pipeline> Pipelines in execution order
     * @throws \RuntimeException if a cycle is detected
     */
    public function getExecutionOrder(EntityType $entityType): Collection
    {
        $pipelines = Pipeline::where('entity_type_id', $entityType->id)
            ->with('modules', 'attribute')
            ->get();

        if ($pipelines->isEmpty()) {
            return collect();
        }

        // Build dependency graph
        $graph = $this->buildDependencyGraph($pipelines);

        // Run topological sort
        return $this->topologicalSort($graph, $pipelines);
    }

    /**
     * Validate a single pipeline for dependency issues
     *
     * @param Pipeline $pipeline
     * @return array Array of error messages (empty if valid)
     */
    public function validatePipeline(Pipeline $pipeline): array
    {
        $errors = [];

        try {
            // Get all pipelines in same entity type
            $allPipelines = Pipeline::where('entity_type_id', $pipeline->entity_type_id)
                ->where('id', '!=', $pipeline->id)
                ->with('modules', 'attribute')
                ->get();

            // Add current pipeline
            $allPipelines->push($pipeline);

            // Build graph
            $graph = $this->buildDependencyGraph($allPipelines);

            // Try to sort - will throw if cycle detected
            $this->topologicalSort($graph, $allPipelines);
        } catch (\RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Get dependencies for a pipeline
     *
     * @param Pipeline $pipeline
     * @return Collection<int> Attribute IDs this pipeline depends on
     */
    public function getDependencies(Pipeline $pipeline): Collection
    {
        $dependencies = collect();

        // Load modules
        $modules = $pipeline->modules()->orderBy('order')->get();

        foreach ($modules as $module) {
            $moduleClass = $module->module_class;
            $settings = $module->settings ?? [];

            // Get input attributes from module
            $inputAttributes = $moduleClass::getInputAttributes($settings);
            $dependencies = $dependencies->merge($inputAttributes);
        }

        return $dependencies->unique();
    }

    /**
     * Build dependency graph from pipelines
     * Returns adjacency list: attribute_id => [dependent_attribute_ids]
     */
    protected function buildDependencyGraph(Collection $pipelines): array
    {
        $graph = [];

        foreach ($pipelines as $pipeline) {
            $targetAttributeId = $pipeline->attribute_id;
            $dependencies = $this->getDependencies($pipeline);

            // Initialize node if not exists
            if (!isset($graph[$targetAttributeId])) {
                $graph[$targetAttributeId] = [
                    'pipeline' => $pipeline,
                    'dependencies' => [],
                    'dependents' => [],
                ];
            }

            // Add dependencies
            foreach ($dependencies as $depAttributeId) {
                if (!in_array($depAttributeId, $graph[$targetAttributeId]['dependencies'])) {
                    $graph[$targetAttributeId]['dependencies'][] = $depAttributeId;
                }

                // Initialize dependency node if not exists
                if (!isset($graph[$depAttributeId])) {
                    $graph[$depAttributeId] = [
                        'pipeline' => null,
                        'dependencies' => [],
                        'dependents' => [],
                    ];
                }

                // Track reverse edges
                if (!in_array($targetAttributeId, $graph[$depAttributeId]['dependents'])) {
                    $graph[$depAttributeId]['dependents'][] = $targetAttributeId;
                }
            }
        }

        return $graph;
    }

    /**
     * Perform topological sort using Kahn's algorithm
     *
     * @throws \RuntimeException if cycle detected
     */
    protected function topologicalSort(array $graph, Collection $pipelines): Collection
    {
        // Count in-degrees (number of dependencies)
        $inDegree = [];
        foreach ($graph as $attrId => $node) {
            $inDegree[$attrId] = count($node['dependencies']);
        }

        // Find all nodes with no dependencies
        $queue = [];
        foreach ($inDegree as $attrId => $degree) {
            if ($degree === 0) {
                $queue[] = $attrId;
            }
        }

        $sorted = [];
        $visited = 0;

        while (!empty($queue)) {
            $current = array_shift($queue);
            $visited++;

            // Add pipeline to sorted list if it exists for this attribute
            if ($graph[$current]['pipeline']) {
                $sorted[] = $graph[$current]['pipeline'];
            }

            // Reduce in-degree for dependents
            foreach ($graph[$current]['dependents'] as $dependent) {
                $inDegree[$dependent]--;

                if ($inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }

        // If we didn't visit all nodes, there's a cycle
        if ($visited < count($graph)) {
            throw new \RuntimeException($this->describeCycle($graph, $inDegree, $pipelines));
        }

        return collect($sorted);
    }

    /**
     * Describe the cycle for better error messages
     */
    protected function describeCycle(array $graph, array $inDegree, Collection $pipelines): string
    {
        // Find nodes still in cycle
        $cycleNodes = [];
        foreach ($inDegree as $attrId => $degree) {
            if ($degree > 0 && $graph[$attrId]['pipeline']) {
                $cycleNodes[] = $graph[$attrId]['pipeline']->attribute->name ?? "Attribute #{$attrId}";
            }
        }

        if (empty($cycleNodes)) {
            return 'Circular dependency detected in pipeline dependencies';
        }

        return 'Circular dependency detected involving: ' . implode(', ', $cycleNodes);
    }

    /**
     * Check if adding a dependency would create a cycle
     *
     * @param Pipeline $pipeline Pipeline that would depend on attribute
     * @param int $dependencyAttributeId Attribute ID to depend on
     * @return bool True if would create cycle
     */
    public function wouldCreateCycle(Pipeline $pipeline, int $dependencyAttributeId): bool
    {
        // If dependency attribute is the same as target, that's a direct cycle
        if ($pipeline->attribute_id === $dependencyAttributeId) {
            return true;
        }

        // Build graph with this new dependency
        $pipelines = Pipeline::where('entity_type_id', $pipeline->entity_type_id)
            ->with('modules', 'attribute')
            ->get();

        // Create temporary pipeline with the new dependency
        $tempPipeline = clone $pipeline;

        try {
            $graph = $this->buildDependencyGraph($pipelines);

            // Manually add the new dependency
            if (!isset($graph[$pipeline->attribute_id])) {
                $graph[$pipeline->attribute_id] = [
                    'pipeline' => $tempPipeline,
                    'dependencies' => [],
                    'dependents' => [],
                ];
            }

            if (!in_array($dependencyAttributeId, $graph[$pipeline->attribute_id]['dependencies'])) {
                $graph[$pipeline->attribute_id]['dependencies'][] = $dependencyAttributeId;
            }

            // Try to sort
            $this->topologicalSort($graph, $pipelines);

            return false;
        } catch (\RuntimeException $e) {
            return true;
        }
    }
}

