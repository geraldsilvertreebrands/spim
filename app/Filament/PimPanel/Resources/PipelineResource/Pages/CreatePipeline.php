<?php

namespace App\Filament\PimPanel\Resources\PipelineResource\Pages;

use App\Filament\PimPanel\Resources\PipelineResource;
use App\Models\Attribute;
use Filament\Forms;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;

class CreatePipeline extends CreateRecord
{
    protected static string $resource = PipelineResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Select::make('entity_type_id')
                ->label('Entity Type')
                ->relationship('entityType', 'name')
                ->required()
                ->live()
                ->helperText('Select the entity type this pipeline will process.'),

            Forms\Components\Select::make('attribute_id')
                ->label('Target Attribute')
                ->options(function (callable $get) {
                    $entityTypeId = $get('entity_type_id');

                    if (! $entityTypeId) {
                        return [];
                    }

                    return Attribute::where('entity_type_id', $entityTypeId)
                        ->whereDoesntHave('pipeline') // Only show attributes without pipelines
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();
                })
                ->required()
                ->searchable()
                ->helperText('The attribute this pipeline will generate values for. Each attribute can only have one pipeline.'),

            Forms\Components\TextInput::make('name')
                ->label('Pipeline Name (Optional)')
                ->maxLength(255)
                ->helperText('A friendly name for this pipeline. Defaults to the attribute name.'),

            Forms\Components\Placeholder::make('next_steps')
                ->label('Next Steps')
                ->content('After creating the pipeline, you\'ll configure the processing modules that generate the attribute value.'),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set initial version
        $data['pipeline_version'] = 1;
        $data['pipeline_updated_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        // Show success message
        \Filament\Notifications\Notification::make()
            ->title('Pipeline Created')
            ->body('Now configure the processing modules to define how values are generated.')
            ->success()
            ->send();
    }
}
