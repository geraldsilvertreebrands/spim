<?php

namespace App\Observers;

use App\Jobs\Pipeline\RunPipelineForEntity;
use App\Models\Pipeline;

/**
 * Observer for EAV versioned table changes
 * Triggers pipeline execution when source attributes change
 */
class EntityAttributeObserver
{
    /**
     * Listen for attribute value changes
     */
    public function handleAttributeChange(string $entityId, int $attributeId): void
    {
        // Find pipelines that depend on this attribute
        $pipelines = $this->findDependentPipelines($attributeId);

        foreach ($pipelines as $pipeline) {
            // Queue pipeline execution for this entity
            RunPipelineForEntity::dispatch(
                pipeline: $pipeline,
                entityId: $entityId,
                triggeredBy: 'entity_save',
                triggerReference: $entityId
            );
        }
    }

    /**
     * Find pipelines that have this attribute as an input
     */
    protected function findDependentPipelines(int $attributeId): array
    {
        // Get all pipelines
        $pipelines = Pipeline::with('modules')->get();

        $dependent = [];

        foreach ($pipelines as $pipeline) {
            // Check each module's input attributes
            foreach ($pipeline->modules as $module) {
                $moduleClass = $module->module_class;
                $settings = $module->settings ?? [];

                try {
                    $inputAttributes = $moduleClass::getInputAttributes($settings);

                    if ($inputAttributes->contains($attributeId)) {
                        $dependent[] = $pipeline;
                        break; // Found it, no need to check other modules
                    }
                } catch (\Exception $e) {
                    // Skip modules that can't be queried
                    continue;
                }
            }
        }

        return $dependent;
    }
}
