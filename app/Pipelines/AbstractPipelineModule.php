<?php

namespace App\Pipelines;

use App\Models\PipelineModule;
use App\Pipelines\Contracts\PipelineModuleInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

abstract class AbstractPipelineModule implements PipelineModuleInterface
{
    protected array $settings;

    protected PipelineModule $moduleModel;

    public function __construct(PipelineModule $moduleModel)
    {
        $this->moduleModel = $moduleModel;
        $this->settings = $moduleModel->settings ?? [];
    }

    /**
     * Default implementation: no input attributes
     */
    public static function getInputAttributes(array $settings): Collection
    {
        return collect();
    }

    /**
     * Default validation: no rules
     */
    public function validateSettings(array $data): array
    {
        return $data;
    }

    /**
     * Default batch processing: call process() for each item
     */
    public function processBatch(array $contexts): array
    {
        $results = [];
        foreach ($contexts as $context) {
            $results[] = $this->process($context);
        }

        return $results;
    }

    /**
     * Get a setting value
     */
    protected function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Validate data against rules
     */
    protected function validate(array $data, array $rules, array $messages = []): array
    {
        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return $validator->validated();
    }
}
