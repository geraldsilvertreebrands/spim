<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\AttributeUiRegistry;
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
            $registry = new AttributeUiRegistry();
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
    }

    public function boot(): void
    {
        // Register policies
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
