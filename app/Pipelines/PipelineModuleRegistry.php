<?php

namespace App\Pipelines;

use App\Models\PipelineModule;
use App\Pipelines\Contracts\PipelineModuleInterface;
use App\Pipelines\Data\PipelineModuleDefinition;
use Illuminate\Support\Collection;

class PipelineModuleRegistry
{
    /**
     * @var array<string, string> Module class map (id => FQCN)
     */
    protected array $modules = [];

    /**
     * @var array<string, PipelineModuleDefinition> Cached definitions
     */
    protected array $definitions = [];

    /**
     * Register a module class
     */
    public function register(string $moduleClass): void
    {
        if (!class_exists($moduleClass)) {
            throw new \InvalidArgumentException("Module class {$moduleClass} does not exist");
        }

        if (!in_array(PipelineModuleInterface::class, class_implements($moduleClass))) {
            throw new \InvalidArgumentException(
                "Module class {$moduleClass} must implement " . PipelineModuleInterface::class
            );
        }

        $definition = $moduleClass::definition();
        $this->modules[$definition->id] = $moduleClass;
        $this->definitions[$definition->id] = $definition;
    }

    /**
     * Get all registered modules
     */
    public function all(): Collection
    {
        return collect($this->definitions);
    }

    /**
     * Get source modules only
     */
    public function sources(): Collection
    {
        return collect($this->definitions)->filter(fn($def) => $def->isSource());
    }

    /**
     * Get processor modules only
     */
    public function processors(): Collection
    {
        return collect($this->definitions)->filter(fn($def) => $def->isProcessor());
    }

    /**
     * Get module definition by class name
     */
    public function getDefinition(string $moduleClass): PipelineModuleDefinition
    {
        // If passed the full class name, find its ID first
        if (str_contains($moduleClass, '\\')) {
            $id = array_search($moduleClass, $this->modules);
            if ($id === false) {
                throw new \InvalidArgumentException("Module class {$moduleClass} is not registered");
            }
            return $this->definitions[$id];
        }

        // Otherwise treat as ID
        if (!isset($this->definitions[$moduleClass])) {
            throw new \InvalidArgumentException("Module {$moduleClass} is not registered");
        }

        return $this->definitions[$moduleClass];
    }

    /**
     * Get module class name by ID
     */
    public function getClass(string $id): string
    {
        if (!isset($this->modules[$id])) {
            throw new \InvalidArgumentException("Module {$id} is not registered");
        }

        return $this->modules[$id];
    }

    /**
     * Check if module is registered
     */
    public function has(string $id): bool
    {
        return isset($this->modules[$id]);
    }

    /**
     * Make a module instance from a PipelineModule model
     */
    public function make(string $moduleClass, PipelineModule $moduleModel): PipelineModuleInterface
    {
        if (!class_exists($moduleClass)) {
            throw new \InvalidArgumentException("Module class {$moduleClass} does not exist");
        }

        return new $moduleClass($moduleModel);
    }

    /**
     * Validate a pipeline's module configuration
     * Returns array of validation errors (empty if valid)
     */
    public function validatePipeline(Collection $modules): array
    {
        $errors = [];

        if ($modules->isEmpty()) {
            $errors[] = 'Pipeline must have at least one module';
            return $errors;
        }

        // First module must be a source
        $firstModule = $modules->first();
        $firstDef = $this->getDefinition($firstModule->module_class);
        if (!$firstDef->isSource()) {
            $errors[] = 'First module must be a source module';
        }

        // All subsequent modules must be processors
        foreach ($modules->skip(1) as $index => $module) {
            $def = $this->getDefinition($module->module_class);
            if (!$def->isProcessor()) {
                $errors[] = "Module at position " . ($index + 2) . " must be a processor";
            }
        }

        // Must have at least one processor
        if ($modules->count() < 2) {
            $errors[] = 'Pipeline must have at least one processor module';
        }

        return $errors;
    }

    /**
     * Get options for a select field (for Filament forms)
     */
    public function getOptions(bool $sourcesOnly = false, bool $processorsOnly = false): array
    {
        $defs = $this->all();

        if ($sourcesOnly) {
            $defs = $defs->filter(fn($def) => $def->isSource());
        } elseif ($processorsOnly) {
            $defs = $defs->filter(fn($def) => $def->isProcessor());
        }

        return $defs->mapWithKeys(function ($def) {
            return [$this->modules[$def->id] => $def->label];
        })->toArray();
    }
}

