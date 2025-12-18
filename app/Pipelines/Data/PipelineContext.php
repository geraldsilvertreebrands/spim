<?php

namespace App\Pipelines\Data;

class PipelineContext
{
    public function __construct(
        public readonly string $entityId,
        public readonly int $attributeId,
        public readonly array $inputs,
        public readonly int $pipelineVersion,
        public readonly array $settings,
        public readonly ?int $batchIndex = null,
        public array $meta = [],
    ) {}

    /**
     * Get a specific input attribute value
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->inputs[$key] ?? $default;
    }

    /**
     * Get all inputs
     */
    public function allInputs(): array
    {
        return $this->inputs;
    }

    /**
     * Get a setting value
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Add metadata
     */
    public function addMeta(string $key, mixed $value): void
    {
        $this->meta[$key] = $value;
    }

    /**
     * Get metadata
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    /**
     * Create a new context with modified inputs (for passing between modules)
     */
    public function withInputs(array $inputs): self
    {
        return new self(
            entityId: $this->entityId,
            attributeId: $this->attributeId,
            inputs: $inputs,
            pipelineVersion: $this->pipelineVersion,
            settings: $this->settings,
            batchIndex: $this->batchIndex,
            meta: $this->meta,
        );
    }

    /**
     * Merge inputs with existing ones
     */
    public function mergeInputs(array $newInputs): self
    {
        return $this->withInputs(array_merge($this->inputs, $newInputs));
    }
}
