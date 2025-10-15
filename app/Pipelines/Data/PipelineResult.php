<?php

namespace App\Pipelines\Data;

class PipelineResult
{
    public function __construct(
        public readonly mixed $value,
        public readonly ?float $confidence = null,
        public readonly ?string $justification = null,
        public readonly array $meta = [],
        public readonly string $status = 'ok', // 'ok', 'skipped', 'error'
        public readonly array $errors = [],
    ) {
        if (!in_array($status, ['ok', 'skipped', 'error'])) {
            throw new \InvalidArgumentException("Status must be 'ok', 'skipped', or 'error'");
        }
    }

    /**
     * Create a successful result
     */
    public static function ok(
        mixed $value,
        ?float $confidence = null,
        ?string $justification = null,
        array $meta = []
    ): self {
        return new self(
            value: $value,
            confidence: $confidence,
            justification: $justification,
            meta: $meta,
            status: 'ok',
        );
    }

    /**
     * Create an error result
     */
    public static function error(string $message, array $errors = []): self
    {
        return new self(
            value: null,
            status: 'error',
            errors: array_merge([$message], $errors),
        );
    }

    /**
     * Create a skipped result
     */
    public static function skipped(string $reason): self
    {
        return new self(
            value: null,
            status: 'skipped',
            errors: [$reason],
        );
    }

    /**
     * Check if result is successful
     */
    public function isOk(): bool
    {
        return $this->status === 'ok';
    }

    /**
     * Check if result is an error
     */
    public function isError(): bool
    {
        return $this->status === 'error';
    }

    /**
     * Check if result was skipped
     */
    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }

    /**
     * Get the first error message
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Get all error messages as a string
     */
    public function getErrorMessages(): string
    {
        return implode('; ', $this->errors);
    }
}

