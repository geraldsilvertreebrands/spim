<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncResult extends Model
{
    use HasFactory;

    protected $guarded = [];

    public $timestamps = false; // Only has created_at

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * Check if result is an error
     */
    public function isError(): bool
    {
        return $this->status === 'error';
    }

    /**
     * Check if result is a warning
     */
    public function isWarning(): bool
    {
        return $this->status === 'warning';
    }

    /**
     * Check if result is successful
     */
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Scope to errors only
     */
    public function scopeErrors($query)
    {
        return $query->where('status', 'error');
    }

    /**
     * Scope to warnings only
     */
    public function scopeWarnings($query)
    {
        return $query->where('status', 'warning');
    }

    /**
     * Scope to successes only
     */
    public function scopeSuccesses($query)
    {
        return $query->where('status', 'success');
    }
}
