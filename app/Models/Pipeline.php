<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends Model
{
    use HasFactory, HasUlids;

    protected $guarded = [];

    protected $attributes = [
        'pipeline_version' => 1,
    ];

    protected $casts = [
        'pipeline_version' => 'integer',
        'pipeline_updated_at' => 'datetime',
        'last_run_at' => 'datetime',
        'last_run_duration_ms' => 'integer',
        'last_run_processed' => 'integer',
        'last_run_failed' => 'integer',
        'last_run_tokens_in' => 'integer',
        'last_run_tokens_out' => 'integer',
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function entityType(): BelongsTo
    {
        return $this->belongsTo(EntityType::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(PipelineModule::class)->orderBy('order');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PipelineRun::class)->latest('started_at');
    }

    public function evals(): HasMany
    {
        return $this->hasMany(PipelineEval::class);
    }

    /**
     * Get failing evals (where actual_output != desired_output or either is null)
     */
    public function failingEvals(): HasMany
    {
        return $this->hasMany(PipelineEval::class)
            ->where(function ($query) {
                $query->whereNull('actual_output')
                    ->orWhereRaw('JSON_EXTRACT(actual_output, "$") != JSON_EXTRACT(desired_output, "$")');
            });
    }

    /**
     * Increment pipeline version and update timestamp
     */
    public function bumpVersion(): void
    {
        $this->increment('pipeline_version');
        $this->pipeline_updated_at = now();
        $this->save();
    }

    /**
     * Update cached stats from the most recent run
     */
    public function updateLastRunStats(PipelineRun $run): void
    {
        $this->update([
            'last_run_at' => $run->started_at,
            'last_run_status' => $run->status,
            'last_run_duration_ms' => $run->completed_at
                ? $run->started_at->diffInMilliseconds($run->completed_at)
                : null,
            'last_run_processed' => $run->entities_processed,
            'last_run_failed' => $run->entities_failed,
            'last_run_tokens_in' => $run->tokens_in,
            'last_run_tokens_out' => $run->tokens_out,
        ]);
    }

    /**
     * Calculate average confidence from recent attribute values
     */
    public function getAverageConfidence(): ?float
    {
        $result = \DB::table('eav_versioned')
            ->where('attribute_id', $this->attribute_id)
            ->whereNotNull('confidence')
            ->avg('confidence');

        return $result ? round($result, 4) : null;
    }

    /**
     * Get token usage for the last N days
     */
    public function getTokenUsage(int $days = 30): array
    {
        $result = $this->runs()
            ->where('started_at', '>=', now()->subDays($days))
            ->selectRaw('
                SUM(tokens_in) as total_tokens_in,
                SUM(tokens_out) as total_tokens_out,
                SUM(entities_processed) as total_entities
            ')
            ->first();

        $totalTokens = ($result->total_tokens_in ?? 0) + ($result->total_tokens_out ?? 0);
        $totalEntities = $result->total_entities ?? 0;

        return [
            'total_tokens' => $totalTokens,
            'tokens_in' => $result->total_tokens_in ?? 0,
            'tokens_out' => $result->total_tokens_out ?? 0,
            'avg_tokens_per_entity' => $totalEntities > 0
                ? round($totalTokens / $totalEntities, 2)
                : 0,
        ];
    }

    /**
     * Boot method to auto-update pipeline_updated_at on module changes
     */
    protected static function booted(): void
    {
        static::saving(function (Pipeline $pipeline) {
            if ($pipeline->isDirty() && !$pipeline->isDirty('pipeline_updated_at')) {
                $pipeline->pipeline_updated_at = now();
            }
        });
    }
}

