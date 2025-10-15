<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineEval extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected $casts = [
        'desired_output' => 'array',
        'actual_output' => 'array',
        'confidence' => 'decimal:4',
        'last_ran_at' => 'datetime',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * Update actual output from a pipeline run
     */
    public function updateActualOutput(?array $output, ?string $justification, ?float $confidence): void
    {
        $this->update([
            'actual_output' => $output,
            'justification' => $justification,
            'confidence' => $confidence,
            'last_ran_at' => now(),
        ]);
    }

    /**
     * Check if this eval is passing
     */
    public function isPassing(): bool
    {
        if ($this->actual_output === null || $this->desired_output === null) {
            return false;
        }

        return json_encode($this->actual_output) === json_encode($this->desired_output);
    }

    /**
     * Check if input has changed since eval was created
     */
    public function hasInputChanged(string $currentHash): bool
    {
        return $this->input_hash !== null && $this->input_hash !== $currentHash;
    }
}

