<?php

namespace App\Filament\PimPanel\Resources\PipelineResource\RelationManagers;

use App\Models\Entity;
use App\Models\PipelineEval;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PipelineEvalsRelationManager extends RelationManager
{
    protected static string $relationship = 'evals';

    protected static ?string $title = 'Evaluations';

    protected static string|BackedEnum|null $icon = 'heroicon-o-beaker';

    protected static ?string $recordTitleAttribute = 'entity_id';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('entity_external_id')
                    ->label('Entity ID (SKU)')
                    ->required()
                    ->helperText('The external identifier (e.g., SKU for products) of the entity to test against.')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Forms\Set $set, ?Model $record) {
                        // Only lookup if creating new or entity changed
                        if (! $state) {
                            return;
                        }

                        $pipeline = $this->getOwnerRecord();
                        $entity = Entity::where('entity_type_id', $pipeline->entity_type_id)
                            ->where('entity_id', $state)
                            ->first();

                        if ($entity) {
                            $set('entity_id', $entity->id);
                        } else {
                            $set('entity_id', null);
                        }
                    }),

                Forms\Components\Hidden::make('entity_id'),

                Forms\Components\Textarea::make('desired_output')
                    ->label('Desired Output')
                    ->required()
                    ->rows(4)
                    ->helperText('The expected output value only. Can be text, number, boolean, or JSON. Do not include justification or confidence.')
                    ->placeholder('Expected Value'),

                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2)
                    ->helperText('Optional notes about this test case.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('entity_id')
            ->columns([
                Tables\Columns\TextColumn::make('entity.entity_id')
                    ->label('Entity ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('desired_output')
                    ->label('Desired Value')
                    ->formatStateUsing(function ($state) {
                        // Extract value if it's an array with 'value' key
                        if (is_array($state) && isset($state['value'])) {
                            $state = $state['value'];
                        }

                        $display = is_array($state) || is_object($state)
                            ? json_encode($state)
                            : (string) $state;

                        return Str::limit($display, 50);
                    })
                    ->tooltip(function ($state) {
                        // Extract value if it's an array with 'value' key
                        if (is_array($state) && isset($state['value'])) {
                            $state = $state['value'];
                        }

                        return is_array($state) || is_object($state)
                            ? json_encode($state, JSON_PRETTY_PRINT)
                            : (string) $state;
                    }),

                Tables\Columns\TextColumn::make('actual_output')
                    ->label('Actual Value')
                    ->formatStateUsing(function ($state) {
                        if ($state === null) {
                            return '—';
                        }

                        // Extract value if it's an array with 'value' key
                        if (is_array($state) && isset($state['value'])) {
                            $state = $state['value'];
                        }

                        $display = is_array($state) || is_object($state)
                            ? json_encode($state)
                            : (string) $state;

                        return Str::limit($display, 50);
                    })
                    ->tooltip(function ($state) {
                        if ($state === null) {
                            return 'Not yet run';
                        }

                        // Extract value if it's an array with 'value' key
                        if (is_array($state) && isset($state['value'])) {
                            $state = $state['value'];
                        }

                        return is_array($state) || is_object($state)
                            ? json_encode($state, JSON_PRETTY_PRINT)
                            : (string) $state;
                    }),

                Tables\Columns\TextColumn::make('justification')
                    ->label('Justification')
                    ->formatStateUsing(fn ($state) => $state ? Str::limit($state, 40) : '—')
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('confidence')
                    ->label('Confidence')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state * 100, 1).'%' : '—')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(function (PipelineEval $record): string {
                        if ($record->actual_output === null) {
                            return 'not_run';
                        }

                        return $record->isPassing() ? 'passing' : 'failing';
                    })
                    ->colors([
                        'success' => 'passing',
                        'danger' => 'failing',
                        'secondary' => 'not_run',
                    ])
                    ->icons([
                        'heroicon-o-check-circle' => 'passing',
                        'heroicon-o-x-circle' => 'failing',
                        'heroicon-o-minus-circle' => 'not_run',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_ran_at')
                    ->label('Last Run')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->placeholder('Never'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'passing' => 'Passing',
                        'failing' => 'Failing',
                        'not_run' => 'Not Run',
                    ])
                    ->query(function ($query, array $data) {
                        if (! isset($data['value'])) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'passing' => $query->whereNotNull('actual_output')
                                ->whereRaw('JSON_EXTRACT(actual_output, "$.value") = JSON_EXTRACT(desired_output, "$.value")'),
                            'failing' => $query->whereNotNull('actual_output')
                                ->whereRaw('JSON_EXTRACT(actual_output, "$.value") != JSON_EXTRACT(desired_output, "$.value")'),
                            'not_run' => $query->whereNull('actual_output'),
                            default => $query,
                        };
                    }),
            ])
            ->headerActions([
                Actions\Action::make('create')
                    ->label('Add Evaluation')
                    ->icon('heroicon-o-plus')
                    ->slideOver()
                    ->form([
                        Forms\Components\TextInput::make('entity_external_id')
                            ->label('Entity ID (SKU)')
                            ->required()
                            ->helperText('The external identifier (e.g., SKU for products) of the entity to test against.'),

                        Forms\Components\Textarea::make('desired_output')
                            ->label('Desired Output')
                            ->required()
                            ->rows(4)
                            ->helperText('The expected output value only. Can be text, number, boolean, or JSON. Do not include justification or confidence.')
                            ->placeholder('Expected Value'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->helperText('Optional notes about this test case.'),
                    ])
                    ->action(function (array $data): void {
                        // Validate entity exists
                        $pipeline = $this->getOwnerRecord();
                        $entity = Entity::where('entity_type_id', $pipeline->entity_type_id)
                            ->where('entity_id', $data['entity_external_id'])
                            ->first();

                        if (! $entity) {
                            \Filament\Notifications\Notification::make()
                                ->title('Entity Not Found')
                                ->body("Entity with ID '{$data['entity_external_id']}' not found.")
                                ->danger()
                                ->send();

                            return;
                        }

                        // Parse desired output - can be JSON or plain value
                        $desiredOutputRaw = $data['desired_output'] ?? '';
                        $desiredValue = json_decode($desiredOutputRaw, true);

                        // If JSON decode failed or resulted in null/empty, use raw value
                        if (json_last_error() !== JSON_ERROR_NONE || $desiredValue === null) {
                            $desiredValue = $desiredOutputRaw;
                        }

                        $pipeline->evals()->create([
                            'entity_id' => $entity->id,
                            'desired_output' => ['value' => $desiredValue],
                            'notes' => $data['notes'] ?? null,
                            'input_hash' => '', // Will be calculated on first run
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Evaluation Created')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->slideOver()
                    ->fillForm(function (PipelineEval $record): array {
                        // Load entity external ID for editing
                        $entity = $record->entity;

                        // Extract just the value from desired_output
                        $desiredValue = $record->desired_output;
                        if (is_array($desiredValue) && isset($desiredValue['value'])) {
                            $desiredValue = $desiredValue['value'];
                        }

                        // Convert to string for display
                        $desiredOutputStr = is_array($desiredValue) || is_object($desiredValue)
                            ? json_encode($desiredValue, JSON_PRETTY_PRINT)
                            : (string) $desiredValue;

                        // Extract actual value for display
                        $actualValue = $record->actual_output;
                        if (is_array($actualValue) && isset($actualValue['value'])) {
                            $actualValue = $actualValue['value'];
                        }
                        $actualValueStr = $actualValue !== null
                            ? (is_array($actualValue) || is_object($actualValue)
                                ? json_encode($actualValue, JSON_PRETTY_PRINT)
                                : (string) $actualValue)
                            : null;

                        return [
                            'entity_external_id' => $entity->entity_id ?? '',
                            'desired_output' => $desiredOutputStr,
                            'notes' => $record->notes,
                            'actual_value_display' => $actualValueStr,
                            'justification_display' => $record->justification,
                            'confidence_display' => $record->confidence !== null
                                ? number_format($record->confidence * 100, 1).'%'
                                : null,
                        ];
                    })
                    ->form([
                        Forms\Components\TextInput::make('entity_external_id')
                            ->label('Entity ID (SKU)')
                            ->required()
                            ->helperText('The external identifier (e.g., SKU for products) of the entity to test against.'),

                        Forms\Components\Textarea::make('desired_output')
                            ->label('Desired Output')
                            ->required()
                            ->rows(4)
                            ->helperText('The expected output value only. Can be text, number, boolean, or JSON. Do not include justification or confidence.')
                            ->placeholder('Expected Value'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->helperText('Optional notes about this test case.'),

                        Section::make('AI Output')
                            ->description('Results from the last pipeline run')
                            ->schema([
                                Forms\Components\Textarea::make('actual_value_display')
                                    ->label('Actual Value')
                                    ->rows(3)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('Not yet run'),

                                Forms\Components\Textarea::make('justification_display')
                                    ->label('Justification')
                                    ->rows(3)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('Not yet run'),

                                Forms\Components\TextInput::make('confidence_display')
                                    ->label('Confidence')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('Not yet run'),
                            ])
                            ->collapsed(),
                    ])
                    ->action(function (PipelineEval $record, array $data): void {
                        // Validate entity exists
                        $pipeline = $this->getOwnerRecord();
                        $entity = Entity::where('entity_type_id', $pipeline->entity_type_id)
                            ->where('entity_id', $data['entity_external_id'])
                            ->first();

                        if (! $entity) {
                            \Filament\Notifications\Notification::make()
                                ->title('Entity Not Found')
                                ->body("Entity with ID '{$data['entity_external_id']}' not found.")
                                ->danger()
                                ->send();

                            return;
                        }

                        // Parse desired output - can be JSON or plain value
                        $desiredOutputRaw = $data['desired_output'] ?? '';
                        $desiredValue = json_decode($desiredOutputRaw, true);

                        // If JSON decode failed or resulted in null/empty, use raw value
                        if (json_last_error() !== JSON_ERROR_NONE || $desiredValue === null) {
                            $desiredValue = $desiredOutputRaw;
                        }

                        $record->update([
                            'entity_id' => $entity->id,
                            'desired_output' => ['value' => $desiredValue],
                            'notes' => $data['notes'] ?? null,
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Evaluation Updated')
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('run')
                    ->label('Run')
                    ->icon('heroicon-o-play')
                    ->action(function (PipelineEval $record) {
                        $executionService = app(\App\Services\PipelineExecutionService::class);
                        $result = $executionService->executeSingleEval($record);

                        if ($result['success']) {
                            $status = $result['passing'] ? 'Passing ✓' : 'Failing ✗';
                            \Filament\Notifications\Notification::make()
                                ->title('Evaluation Completed')
                                ->body("Status: {$status}")
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Evaluation Failed')
                                ->body($result['error'] ?? 'Unknown error')
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation(false),

                Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (PipelineEval $record) {
                        $record->delete();

                        \Filament\Notifications\Notification::make()
                            ->title('Evaluation Deleted')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Actions\BulkAction::make('delete')
                    ->label('Delete Selected')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $records->each->delete();

                        \Filament\Notifications\Notification::make()
                            ->title('Evaluations Deleted')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('last_ran_at', 'desc')
            ->emptyStateHeading('No evaluation test cases')
            ->emptyStateDescription('Add test cases to verify pipeline output quality.')
            ->emptyStateIcon('heroicon-o-beaker');
    }
}
