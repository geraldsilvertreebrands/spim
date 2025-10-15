<?php

namespace App\Pipelines\Contracts;

use App\Pipelines\Data\PipelineContext;
use App\Pipelines\Data\PipelineModuleDefinition;
use App\Pipelines\Data\PipelineResult;
use Filament\Forms\Form;
use Illuminate\Support\Collection;

interface PipelineModuleInterface
{
    /**
     * Get the module definition (metadata)
     */
    public static function definition(): PipelineModuleDefinition;

    /**
     * Configure the Filament form for module settings
     */
    public static function form(Form $form): Form;

    /**
     * Get the list of input attributes this module depends on
     * Used for dependency graph construction
     */
    public static function getInputAttributes(array $settings): Collection;

    /**
     * Validate settings before saving
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateSettings(array $data): array;

    /**
     * Process a single entity through this module
     */
    public function process(PipelineContext $context): PipelineResult;

    /**
     * Process a batch of entities (optional optimization)
     * If not overridden, defaults to calling process() for each item
     *
     * @param array<PipelineContext> $contexts
     * @return array<PipelineResult>
     */
    public function processBatch(array $contexts): array;
}

