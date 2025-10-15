<?php

namespace App\Pipelines\Modules;

use App\Models\Attribute;
use App\Pipelines\AbstractPipelineModule;
use App\Pipelines\Data\PipelineContext;
use App\Pipelines\Data\PipelineModuleDefinition;
use App\Pipelines\Data\PipelineResult;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttributesSourceModule extends AbstractPipelineModule
{
    public static function definition(): PipelineModuleDefinition
    {
        return new PipelineModuleDefinition(
            id: 'attributes_source',
            label: 'Attributes',
            description: 'Load attribute values from the entity',
            type: 'source',
        );
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('attribute_ids')
                ->label('Attributes')
                ->multiple()
                ->required()
                ->searchable()
                ->options(function () {
                    return Attribute::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->helperText('Select the attributes to use as inputs for this pipeline'),
        ]);
    }

    public static function getInputAttributes(array $settings): Collection
    {
        $attributeIds = $settings['attribute_ids'] ?? [];
        return collect($attributeIds)->map(fn($id) => (int) $id);
    }

    public function validateSettings(array $data): array
    {
        return $this->validate($data, [
            'attribute_ids' => 'required|array|min:1',
            'attribute_ids.*' => 'exists:attributes,id',
        ]);
    }

    public function process(PipelineContext $context): PipelineResult
    {
        // Source modules should not be called with process() in normal flow
        // They are handled specially by the execution service
        return PipelineResult::error('Source modules should not be processed directly');
    }

    /**
     * Batch load attributes for multiple entities
     * This is the main entry point for this source module
     */
    public function loadInputsForEntities(array $entityIds): array
    {
        $attributeIds = $this->setting('attribute_ids', []);

        if (empty($attributeIds)) {
            return [];
        }

        // Get attribute names for mapping
        $attributes = Attribute::whereIn('id', $attributeIds)
            ->get()
            ->keyBy('id');

        // Batch load all attribute values
        $values = DB::table('eav_versioned')
            ->whereIn('entity_id', $entityIds)
            ->whereIn('attribute_id', $attributeIds)
            ->get();

        // Group by entity
        $grouped = $values->groupBy('entity_id');

        // Build inputs array for each entity
        $result = [];
        foreach ($entityIds as $entityId) {
            $entityValues = $grouped->get($entityId, collect());
            $inputs = [];

            foreach ($entityValues as $value) {
                $attribute = $attributes->get($value->attribute_id);
                if ($attribute) {
                    $inputs[$attribute->name] = $value->value_current;
                }
            }

            $result[$entityId] = $inputs;
        }

        return $result;
    }

    /**
     * Batch processing for this source module
     * Returns array of inputs (not PipelineResult objects)
     */
    public function processBatch(array $contexts): array
    {
        $entityIds = array_map(fn($ctx) => $ctx->entityId, $contexts);
        return $this->loadInputsForEntities($entityIds);
    }
}

