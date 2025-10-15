<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipelineRun extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected $casts = [
        'pipeline_version' => 'integer',
        'batch_size' => 'integer',
        'entities_processed' => 'integer',
        'entities_failed' => 'integer',
        'entities_skipped' => 'integer',
        'tokens_in' => 'integer',
        'tokens_out' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * Mark the run as completed
     */
    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->pipeline->updateLastRunStats($this);
    }

    /**
     * Mark the run as failed
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);

        $this->pipeline->updateLastRunStats($this);
    }

    /**
     * Mark the run as aborted
     */
    public function markAborted(string $reason): void
    {
        $this->update([
            'status' => 'aborted',
            'completed_at' => now(),
            'error_message' => $reason,
        ]);

        $this->pipeline->updateLastRunStats($this);
    }

    /**
     * Increment counters
     */
    public function incrementProcessed(int $count = 1): void
    {
        $this->increment('entities_processed', $count);
    }

    public function incrementFailed(int $count = 1): void
    {
        $this->increment('entities_failed', $count);
    }

    public function incrementSkipped(int $count = 1): void
    {
        $this->increment('entities_skipped', $count);
    }

    /**
     * Add token usage
     */
    public function addTokens(int $tokensIn, int $tokensOut): void
    {
        $this->tokens_in = ($this->tokens_in ?? 0) + $tokensIn;
        $this->tokens_out = ($this->tokens_out ?? 0) + $tokensOut;
        $this->save();
    }

    /**
     * Get duration in milliseconds
     */
    public function getDurationMs(): ?int
    {
        if (!$this->completed_at) {
            return null;
        }

        return $this->started_at->diffInMilliseconds($this->completed_at);
    }

    /**
     * Check if run was successful
     */
    public function wasSuccessful(): bool
    {
        return $this->status === 'completed' && $this->entities_failed === 0;
    }
}

