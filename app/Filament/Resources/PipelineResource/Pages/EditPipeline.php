<?php

namespace App\Filament\Resources\PipelineResource\Pages;

use App\Filament\Resources\PipelineResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditPipeline extends EditRecord
{
    protected static string $resource = PipelineResource::class;

    public function schema(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\Section::make('Pipeline Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Pipeline Name')
                        ->nullable(),

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
                ]),

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
                ->collapsible(),

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

            Forms\Components\Placeholder::make('modules_notice')
                ->content('Module configuration UI coming soon. Use database or seeder to configure modules for now.'),

            Forms\Components\Placeholder::make('evals_notice')
                ->content('Eval management UI coming soon.'),
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
}

