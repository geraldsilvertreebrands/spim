<?php

namespace App\Models;

use App\Pipelines\PipelineModuleRegistry;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineModule extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'order' => 'integer',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * Get the module instance from the registry
     */
    public function getInstance(): object
    {
        $registry = app(PipelineModuleRegistry::class);
        return $registry->make($this->module_class, $this);
    }

    /**
     * Get the module definition
     */
    public function getDefinition(): object
    {
        $registry = app(PipelineModuleRegistry::class);
        return $registry->getDefinition($this->module_class);
    }

    /**
     * Boot method to bump pipeline version when modules change
     */
    protected static function booted(): void
    {
        static::saved(function (PipelineModule $module) {
            $module->pipeline->bumpVersion();
        });

        static::deleted(function (PipelineModule $module) {
            $module->pipeline->bumpVersion();
        });
    }
}

