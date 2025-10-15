<?php

namespace App\Filament\Resources\PipelineResource\Pages;

use App\Filament\Resources\PipelineResource;
use App\Pipelines\PipelineModuleRegistry;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditPipeline extends EditRecord
{
    protected static string $resource = PipelineResource::class;

    public function schema(Schema $schema): Schema
    {
        $registry = app(PipelineModuleRegistry::class);

        return $schema->components([
            Forms\Components\Tabs::make('Pipeline')
                ->tabs([
                    Forms\Components\Tabs\Tab::make('Configuration')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            Forms\Components\Section::make('Pipeline Information')
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label('Pipeline Name')
                                        ->maxLength(255)
                                        ->nullable()
                                        ->helperText('Optional friendly name for this pipeline.'),

                                    Forms\Components\Placeholder::make('entity_type')
                                        ->label('Entity Type')
                                        ->content(fn ($record) => $record->entityType->name ?? '—'),

                                    Forms\Components\Placeholder::make('attribute')
                                        ->label('Target Attribute')
                                        ->content(fn ($record) => $record->attribute->name ?? '—'),

                                    Forms\Components\Placeholder::make('version')
                                        ->label('Pipeline Version')
                                        ->content(fn ($record) => $record->pipeline_version),

                                    Forms\Components\Placeholder::make('updated')
                                        ->label('Last Updated')
                                        ->content(fn ($record) => $record->pipeline_updated_at?->diffForHumans()),
                                ])
                                ->columns(2),

                            Forms\Components\Section::make('Processing Modules')
                                ->description('Define the sequence of operations that generate the attribute value. First module must load source data, subsequent modules transform it.')
                                ->schema([
                                    Forms\Components\Builder::make('modules_config')
                                        ->label('')
                                        ->blocks($this->getModuleBlocks($registry))
                                        ->collapsible()
                                        ->blockNumbers(false)
                                        ->addActionLabel('Add Module')
                                        ->reorderable()
                                        ->live()
                                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                                            // This would trigger validation, but we'll handle it on save
                                        })
                                        ->default([])
                                        ->columnSpanFull(),
                                ])
                                ->collapsible(),
                        ]),

                    Forms\Components\Tabs\Tab::make('Statistics')
                        ->icon('heroicon-o-chart-bar')
                        ->schema([
                            Forms\Components\Section::make('Last Run Stats')
                                ->schema([
                                    Forms\Components\Placeholder::make('last_run')
                                        ->label('Last Run')
                                        ->content(fn ($record) => $record->last_run_at?->diffForHumans() ?? 'Never'),

                                    Forms\Components\Placeholder::make('status')
                                        ->label('Status')
                                        ->content(fn ($record) => $record->last_run_status ?? '—'),

                                    Forms\Components\Placeholder::make('processed')
                                        ->label('Entities Processed')
                                        ->content(fn ($record) => $record->last_run_processed ?? '—'),

                                    Forms\Components\Placeholder::make('failed')
                                        ->label('Failed')
                                        ->content(fn ($record) => $record->last_run_failed ?? '—'),

                                    Forms\Components\Placeholder::make('tokens')
                                        ->label('Tokens (In/Out)')
                                        ->content(function ($record) {
                                            if (!$record->last_run_tokens_in && !$record->last_run_tokens_out) {
                                                return '—';
                                            }
                                            return number_format($record->last_run_tokens_in ?? 0) . ' / ' . number_format($record->last_run_tokens_out ?? 0);
                                        }),
                                ])
                                ->columns(2),

                            Forms\Components\Section::make('Token Usage (Last 30 Days)')
                                ->schema([
                                    Forms\Components\Placeholder::make('token_stats')
                                        ->label('')
                                        ->content(function ($record) {
                                            $stats = $record->getTokenUsage(30);
                                            return implode(' | ', [
                                                'Total: ' . number_format($stats['total_tokens']),
                                                'Avg per entity: ' . $stats['avg_tokens_per_entity'],
                                            ]);
                                        }),
                                ])
                                ->collapsible(),
                        ]),

                    Forms\Components\Tabs\Tab::make('Evaluations')
                        ->icon('heroicon-o-beaker')
                        ->badge(fn ($record) => $record->failingEvals()->count() > 0 ? $record->failingEvals()->count() : null)
                        ->badgeColor('danger')
                        ->schema([
                            Forms\Components\Section::make('Evaluation Test Cases')
                                ->description('Test cases to verify pipeline output quality. Evaluations are re-run after each pipeline execution.')
                                ->schema([
                                    Forms\Components\Repeater::make('evals_config')
                                        ->label('Evaluations')
                                        ->schema([
                                            Forms\Components\TextInput::make('entity_id')
                                                ->label('Entity ID')
                                                ->required()
                                                ->helperText('The ULID of the entity to test against.'),

                                            Forms\Components\Textarea::make('desired_output')
                                                ->label('Desired Output (JSON)')
                                                ->required()
                                                ->rows(4)
                                                ->helperText('The expected output in JSON format. Example: {"value": "Test Product", "justification": "...", "confidence": 0.95}')
                                                ->placeholder('{"value": "Expected Value", "justification": "Why this value", "confidence": 0.95}'),

                                            Forms\Components\Textarea::make('notes')
                                                ->label('Notes')
                                                ->rows(2)
                                                ->helperText('Optional notes about this test case.'),

                                            Forms\Components\Grid::make(3)
                                                ->schema([
                                                    Forms\Components\Placeholder::make('input_hash_display')
                                                        ->label('Input Hash')
                                                        ->content(fn ($state, $get) => $state['input_hash'] ?? 'Will be calculated on first run'),

                                                    Forms\Components\Placeholder::make('actual_output_display')
                                                        ->label('Last Actual Output')
                                                        ->content(function ($state, $get) {
                                                            $actual = $state['actual_output'] ?? null;
                                                            if (!$actual) {
                                                                return 'Not yet run';
                                                            }
                                                            return json_encode($actual, JSON_PRETTY_PRINT);
                                                        }),

                                                    Forms\Components\Placeholder::make('status_display')
                                                        ->label('Status')
                                                        ->content(function ($state, $get) {
                                                            $isPassing = $state['is_passing'] ?? null;
                                                            if ($isPassing === null) {
                                                                return '—';
                                                            }
                                                            return $isPassing ? '✅ Passing' : '❌ Failing';
                                                        }),
                                                ]),

                                            Forms\Components\Hidden::make('input_hash'),
                                            Forms\Components\Hidden::make('actual_output'),
                                            Forms\Components\Hidden::make('justification'),
                                            Forms\Components\Hidden::make('confidence'),
                                            Forms\Components\Hidden::make('last_ran_at'),
                                            Forms\Components\Hidden::make('is_passing'),
                                        ])
                                        ->columns(1)
                                        ->reorderable(false)
                                        ->collapsible()
                                        ->itemLabel(fn (array $state): ?string =>
                                            'Entity: ' . ($state['entity_id'] ?? 'New') .
                                            ($state['is_passing'] === false ? ' ❌' : ($state['is_passing'] === true ? ' ✅' : ''))
                                        )
                                        ->addActionLabel('Add Evaluation')
                                        ->defaultItems(0)
                                        ->columnSpanFull(),
                                ])
                                ->collapsible(),
                        ]),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('run_pipeline')
                ->label('Run Pipeline')
                ->icon('heroicon-o-play')
                ->action(function () {
                    \App\Jobs\Pipeline\RunPipelineBatch::dispatch(
                        pipeline: $this->record,
                        triggeredBy: 'manual'
                    );

                    \Filament\Notifications\Notification::make()
                        ->title('Pipeline Queued')
                        ->body('The pipeline has been queued for execution.')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),

            Actions\Action::make('run_evals')
                ->label('Run Evals')
                ->icon('heroicon-o-beaker')
                ->action(function () {
                    \App\Jobs\Pipeline\RunPipelineEvals::dispatch($this->record);

                    \Filament\Notifications\Notification::make()
                        ->title('Evals Queued')
                        ->body('Evaluation tests have been queued.')
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Build module blocks for the Builder component
     */
    protected function getModuleBlocks(PipelineModuleRegistry $registry): array
    {
        $blocks = [];

        // Get all registered modules
        foreach ($registry->all() as $definition) {
            $moduleClass = $registry->getClass($definition->id);

            // Create a temporary form to get the schema
            $form = new \Filament\Forms\Form(new class {});
            $moduleForm = $moduleClass::form($form);

            $blocks[] = Forms\Components\Builder\Block::make($definition->id)
                ->label($definition->label)
                ->icon($definition->isSource() ? 'heroicon-o-folder-open' : 'heroicon-o-cpu-chip')
                ->schema([
                    Forms\Components\Hidden::make('module_class')
                        ->default($moduleClass),

                    Forms\Components\Placeholder::make('description')
                        ->label('Description')
                        ->content($definition->description),

                    ...$moduleForm->getComponents(),
                ]);
        }

        return $blocks;
    }

    /**
     * Fill form with existing module and eval data
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $registry = app(PipelineModuleRegistry::class);

        // Load existing modules into the builder format
        $modules = $this->record->modules()->orderBy('order')->get();

        $modulesConfig = [];
        foreach ($modules as $module) {
            $definition = $registry->getDefinition($module->module_class);

            $modulesConfig[] = [
                'type' => $definition->id,
                'data' => array_merge(
                    ['module_class' => $module->module_class],
                    $module->settings ?? []
                ),
            ];
        }

        $data['modules_config'] = $modulesConfig;

        // Load existing evals
        $evals = $this->record->evals()->get();

        $evalsConfig = [];
        foreach ($evals as $eval) {
            $evalsConfig[] = [
                'id' => $eval->id,
                'entity_id' => $eval->entity_id,
                'desired_output' => json_encode($eval->desired_output, JSON_PRETTY_PRINT),
                'notes' => $eval->notes,
                'input_hash' => $eval->input_hash,
                'actual_output' => $eval->actual_output,
                'justification' => $eval->justification,
                'confidence' => $eval->confidence,
                'last_ran_at' => $eval->last_ran_at?->toDateTimeString(),
                'is_passing' => $eval->isPassing(),
            ];
        }

        $data['evals_config'] = $evalsConfig;

        return $data;
    }

    /**
     * Save modules and evals from form to database
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $modulesConfig = $data['modules_config'] ?? [];
        $evalsConfig = $data['evals_config'] ?? [];

        // Don't save these to pipeline table
        unset($data['modules_config']);
        unset($data['evals_config']);

        return $data;
    }

    /**
     * After save, sync modules and evals
     */
    protected function afterSave(): void
    {
        $modulesConfig = $this->data['modules_config'] ?? [];
        $evalsConfig = $this->data['evals_config'] ?? [];
        $registry = app(PipelineModuleRegistry::class);

        // Validate module configuration
        if (!empty($modulesConfig)) {
            $errors = $this->validateModuleConfiguration($modulesConfig, $registry);

            if (!empty($errors)) {
                \Filament\Notifications\Notification::make()
                    ->title('Module Configuration Error')
                    ->body(implode(' ', $errors))
                    ->danger()
                    ->send();
                return;
            }
        }

        // Delete existing modules
        $this->record->modules()->delete();

        // Create new modules
        foreach ($modulesConfig as $index => $moduleBlock) {
            $moduleClass = $moduleBlock['data']['module_class'] ?? null;

            if (!$moduleClass) {
                continue;
            }

            // Extract settings (remove our internal fields)
            $settings = $moduleBlock['data'];
            unset($settings['module_class']);

            $this->record->modules()->create([
                'order' => $index + 1,
                'module_class' => $moduleClass,
                'settings' => $settings,
            ]);
        }

        // Bump pipeline version if modules changed
        if (!empty($modulesConfig)) {
            $this->record->increment('pipeline_version');
            $this->record->update(['pipeline_updated_at' => now()]);
        }

        // Sync evaluations
        $this->syncEvaluations($evalsConfig);
    }

    /**
     * Sync evaluations with database
     */
    protected function syncEvaluations(array $evalsConfig): void
    {
        $existingIds = [];

        foreach ($evalsConfig as $evalData) {
            // Parse desired output JSON
            $desiredOutput = null;
            try {
                $desiredOutput = json_decode($evalData['desired_output'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON');
                }
            } catch (\Exception $e) {
                \Filament\Notifications\Notification::make()
                    ->title('Invalid Eval JSON')
                    ->body('Desired output for entity ' . ($evalData['entity_id'] ?? 'unknown') . ' is not valid JSON.')
                    ->danger()
                    ->send();
                continue;
            }

            // Update or create eval
            $evalId = $evalData['id'] ?? null;

            if ($evalId) {
                // Update existing
                $eval = $this->record->evals()->find($evalId);
                if ($eval) {
                    $eval->update([
                        'entity_id' => $evalData['entity_id'],
                        'desired_output' => $desiredOutput,
                        'notes' => $evalData['notes'] ?? null,
                    ]);
                    $existingIds[] = $evalId;
                }
            } else {
                // Create new
                $eval = $this->record->evals()->create([
                    'entity_id' => $evalData['entity_id'],
                    'desired_output' => $desiredOutput,
                    'notes' => $evalData['notes'] ?? null,
                    'input_hash' => '', // Will be calculated on first run
                ]);
                $existingIds[] = $eval->id;
            }
        }

        // Delete evals that were removed from the form
        $this->record->evals()
            ->whereNotIn('id', $existingIds)
            ->delete();
    }

    /**
     * Validate module configuration follows rules
     */
    protected function validateModuleConfiguration(array $modulesConfig, PipelineModuleRegistry $registry): array
    {
        $errors = [];

        if (empty($modulesConfig)) {
            return $errors;
        }

        // First module must be a source
        $firstModule = $modulesConfig[0]['data']['module_class'] ?? null;
        if ($firstModule) {
            $firstDef = $registry->getDefinition($firstModule);
            if (!$firstDef->isSource()) {
                $errors[] = 'First module must be a source module (loads input data).';
            }
        }

        // Subsequent modules must be processors
        foreach (array_slice($modulesConfig, 1) as $index => $moduleBlock) {
            $moduleClass = $moduleBlock['data']['module_class'] ?? null;
            if ($moduleClass) {
                $def = $registry->getDefinition($moduleClass);
                if (!$def->isProcessor()) {
                    $errors[] = "Module at position " . ($index + 2) . " must be a processor module.";
                }
            }
        }

        // Must have at least one processor
        if (count($modulesConfig) < 2) {
            $errors[] = 'Pipeline must have at least one processor module (in addition to the source).';
        }

        return $errors;
    }
}

