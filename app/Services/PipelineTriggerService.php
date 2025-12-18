<?php

namespace App\Services;

use App\Jobs\Pipeline\RunPipelineForEntity;
use App\Models\Entity;
use App\Models\Pipeline;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PipelineTriggerService
{
    /**
     * Trigger pipelines for an entity after save
     * This should be called from Filament forms or wherever entities are edited
     */
    public function triggerPipelinesForEntity(Entity|string $entity, ?Collection $changedAttributeIds = null): void
    {
        $entityId = $entity instanceof Entity ? $entity->id : $entity;
        $entityTypeId = $entity instanceof Entity ? $entity->entity_type_id : null;

        if (! $entityTypeId) {
            // Fetch entity type
            $entityModel = Entity::find($entityId);
            if (! $entityModel) {
                Log::warning('Attempted to trigger pipelines for non-existent entity', ['entity_id' => $entityId]);

                return;
            }
            $entityTypeId = $entityModel->entity_type_id;
        }

        // Find pipelines for this entity type
        $pipelines = Pipeline::where('entity_type_id', $entityTypeId)
            ->with('modules')
            ->get();

        if ($pipelines->isEmpty()) {
            return;
        }

        // Filter to only pipelines that depend on changed attributes (if specified)
        if ($changedAttributeIds !== null && $changedAttributeIds->isNotEmpty()) {
            $pipelines = $pipelines->filter(function ($pipeline) use ($changedAttributeIds) {
                return $this->pipelineDependsOnAttributes($pipeline, $changedAttributeIds);
            });
        }

        // Queue execution for each pipeline
        foreach ($pipelines as $pipeline) {
            RunPipelineForEntity::dispatch(
                pipeline: $pipeline,
                entityId: $entityId,
                triggeredBy: 'entity_save',
                triggerReference: $entityId
            );
        }

        if ($pipelines->isNotEmpty()) {
            Log::info('Queued pipelines for entity', [
                'entity_id' => $entityId,
                'pipeline_count' => $pipelines->count(),
            ]);
        }
    }

    /**
     * Check if a pipeline depends on any of the given attributes
     */
    protected function pipelineDependsOnAttributes(Pipeline $pipeline, Collection $attributeIds): bool
    {
        foreach ($pipeline->modules as $module) {
            $moduleClass = $module->module_class;
            $settings = $module->settings ?? [];

            try {
                $inputAttributes = $moduleClass::getInputAttributes($settings);

                // Check if any input attribute matches our changed attributes
                if ($inputAttributes->intersect($attributeIds)->isNotEmpty()) {
                    return true;
                }
            } catch (\Exception $e) {
                // Skip modules that can't be queried
                continue;
            }
        }

        return false;
    }

    /**
     * Trigger all pipelines for all entities of a type
     * Useful for manual "reprocess all" actions
     */
    public function triggerAllPipelinesForEntityType(int $entityTypeId): int
    {
        $pipelines = Pipeline::where('entity_type_id', $entityTypeId)->get();

        foreach ($pipelines as $pipeline) {
            \App\Jobs\Pipeline\RunPipelineBatch::dispatch(
                pipeline: $pipeline,
                triggeredBy: 'manual'
            );
        }

        return $pipelines->count();
    }
}
