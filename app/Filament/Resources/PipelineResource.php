<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PipelineResource\Pages;
use App\Models\Pipeline;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;
use BackedEnum;

class PipelineResource extends Resource
{
    protected static ?string $model = Pipeline::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bolt';
    protected static string|UnitEnum|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Pipelines';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('entityType.name')
                    ->label('Entity Type')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('attribute.name')
                    ->label('Attribute')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('last_run_at')
                    ->label('Last Run')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('last_run_status')
                    ->label('Status')
                    ->colors([
                        'success' => 'completed',
                        'danger' => 'failed',
                        'warning' => 'partial',
                        'secondary' => 'running',
                    ]),

                Tables\Columns\TextColumn::make('last_run_processed')
                    ->label('Processed')
                    ->default('—'),

                Tables\Columns\TextColumn::make('last_run_failed')
                    ->label('Failed')
                    ->default('—'),

                Tables\Columns\TextColumn::make('average_confidence')
                    ->label('Avg Confidence')
                    ->getStateUsing(fn ($record) => $record->getAverageConfidence())
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 2) : '—'),

                Tables\Columns\TextColumn::make('evals_count')
                    ->label('Evals')
                    ->counts('evals')
                    ->default('0'),

                Tables\Columns\TextColumn::make('failing_evals_count')
                    ->label('Failing')
                    ->counts('failingEvals')
                    ->default('0')
                    ->color('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('entity_type_id')
                    ->label('Entity Type')
                    ->relationship('entityType', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('run')
                    ->label('Run Now')
                    ->icon('heroicon-o-play')
                    ->action(function ($record) {
                        \App\Jobs\Pipeline\RunPipelineBatch::dispatch(
                            pipeline: $record,
                            triggeredBy: 'manual'
                        );

                        \Filament\Notifications\Notification::make()
                            ->title('Pipeline Queued')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),

                Tables\Actions\Action::make('run_evals')
                    ->label('Run Evals')
                    ->icon('heroicon-o-beaker')
                    ->action(function ($record) {
                        \App\Jobs\Pipeline\RunPipelineEvals::dispatch($record);

                        \Filament\Notifications\Notification::make()
                            ->title('Evals Queued')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPipelines::route('/'),
            'create' => Pages\CreatePipeline::route('/create'),
            'edit' => Pages\EditPipeline::route('/{record}/edit'),
        ];
    }
}

