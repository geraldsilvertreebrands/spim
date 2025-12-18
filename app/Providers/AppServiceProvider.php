<?php

namespace App\Providers;

use App\Listeners\AuthActivityLogger;
use App\Models\User;
use App\Pipelines\Modules\AiPromptProcessorModule;
use App\Pipelines\Modules\AttributesSourceModule;
use App\Pipelines\Modules\CalculationProcessorModule;
use App\Pipelines\PipelineModuleRegistry;
use App\Policies\UserPolicy;
use App\Support\AttributeUiRegistry;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
    ];

    public function register(): void
    {
        $this->app->singleton(AttributeUiRegistry::class, function () {
            $registry = new AttributeUiRegistry;
            $registry->register('text', \App\Ui\Attributes\TextAttributeUi::class);
            $registry->register('html', \App\Ui\Attributes\HtmlAttributeUi::class);
            $registry->register('integer', \App\Ui\Attributes\IntegerAttributeUi::class);
            $registry->register('json', \App\Ui\Attributes\JsonAttributeUi::class);
            $registry->register('select', \App\Ui\Attributes\SelectAttributeUi::class);
            $registry->register('multiselect', \App\Ui\Attributes\MultiselectAttributeUi::class);
            $registry->register('belongs_to', \App\Ui\Attributes\BelongsToAttributeUi::class);
            $registry->register('belongs_to_multi', \App\Ui\Attributes\BelongsToMultiAttributeUi::class);

            return $registry;
        });

        $this->app->singleton(PipelineModuleRegistry::class, function () {
            $registry = new PipelineModuleRegistry;
            $registry->register(AttributesSourceModule::class);
            $registry->register(AiPromptProcessorModule::class);
            $registry->register(CalculationProcessorModule::class);

            return $registry;
        });
    }

    public function boot(): void
    {
        // Register policies
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Register shared Blade components
        Blade::component('premium-locked-placeholder', \App\Filament\Shared\Components\PremiumLockedPlaceholder::class);
        Blade::component('premium-gate', \App\Filament\Shared\Components\PremiumGate::class);
        Blade::component('kpi-tile', \App\Filament\Shared\Components\KpiTile::class);
        Blade::component('bigquery-error', \App\Filament\Shared\Components\BigQueryError::class);
        Blade::component('loading-skeleton', \App\Filament\Shared\Components\LoadingSkeleton::class);

        // Register auth activity logger for audit trail
        Event::subscribe(AuthActivityLogger::class);
    }
}
