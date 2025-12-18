<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company_id',
        'access_level',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'company_id' => 'integer',
    ];

    /**
     * Users who have access to this brand (suppliers).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'supplier_brand_access')
            ->withTimestamps();
    }

    /**
     * Competitor relationships for this brand.
     */
    public function competitors(): HasMany
    {
        return $this->hasMany(BrandCompetitor::class);
    }

    /**
     * Scope to filter brands by company.
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter premium brands.
     */
    public function scopePremium($query)
    {
        return $query->where('access_level', 'premium');
    }

    /**
     * Scope to filter basic brands.
     */
    public function scopeBasic($query)
    {
        return $query->where('access_level', 'basic');
    }

    /**
     * Check if this brand has premium access level.
     */
    public function isPremium(): bool
    {
        return $this->access_level === 'premium';
    }

    /**
     * Check if this brand has basic access level.
     */
    public function isBasic(): bool
    {
        return $this->access_level === 'basic';
    }

    /**
     * Check if this brand belongs to Pet Heaven (company_id = 5).
     */
    public function isPetHeaven(): bool
    {
        return $this->company_id === 5;
    }

    /**
     * Check if this brand belongs to Faithful to Nature (company_id = 3).
     */
    public function isFaithfulToNature(): bool
    {
        return $this->company_id === 3;
    }

    /**
     * Check if this brand belongs to UCOOK (company_id = 9).
     */
    public function isUcook(): bool
    {
        return $this->company_id === 9;
    }
}
