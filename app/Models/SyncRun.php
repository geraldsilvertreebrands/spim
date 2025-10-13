<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncRun extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function entityType(): BelongsTo
    {
        return $this->belongsTo(EntityType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(SyncResult::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(SyncResult::class)->where('status', 'error');
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(SyncResult::class)->where('status', 'warning');
    }

    /**
     * Check if sync is currently running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if sync completed successfully
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'completed' && $this->failed_items === 0;
    }

    /**
     * Get duration in seconds
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->completed_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }

    /**
     * Get success rate as percentage
     */
    public function getSuccessRateAttribute(): ?float
    {
        if ($this->total_items === 0) {
            return null;
        }

        return round(($this->successful_items / $this->total_items) * 100, 1);
    }

    /**
     * Mark sync as completed
     */
    public function markCompleted(): void
    {
        $this->update([
            'completed_at' => now(),
            'status' => $this->failed_items > 0 ? 'partial' : 'completed',
        ]);
    }

    /**
     * Mark sync as failed
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'completed_at' => now(),
            'status' => 'failed',
            'error_summary' => $error,
        ]);
    }

    /**
     * Cancel a running sync
     */
    public function cancel(): void
    {
        if (!$this->isRunning()) {
            throw new \InvalidArgumentException('Cannot cancel a sync that is not running');
        }

        $this->update([
            'completed_at' => now(),
            'status' => 'cancelled',
            'error_summary' => 'Sync was cancelled by user',
        ]);
    }

    /**
     * Increment item counts
     */
    public function incrementSuccess(): void
    {
        $this->increment('successful_items');
        $this->increment('total_items');
    }

    public function incrementError(): void
    {
        $this->increment('failed_items');
        $this->increment('total_items');
    }

    public function incrementSkipped(): void
    {
        $this->increment('skipped_items');
        $this->increment('total_items');
    }
}

