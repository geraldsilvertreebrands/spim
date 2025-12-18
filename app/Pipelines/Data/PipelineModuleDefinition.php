<?php

namespace App\Pipelines\Data;

class PipelineModuleDefinition
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $description,
        public readonly string $type, // 'source' or 'processor'
    ) {
        if (! in_array($type, ['source', 'processor'])) {
            throw new \InvalidArgumentException("Module type must be 'source' or 'processor'");
        }
    }

    public function isSource(): bool
    {
        return $this->type === 'source';
    }

    public function isProcessor(): bool
    {
        return $this->type === 'processor';
    }
}
