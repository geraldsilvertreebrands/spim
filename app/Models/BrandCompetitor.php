<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandCompetitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'competitor_brand_id',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    /**
     * The brand that has competitors.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * The competitor brand.
     */
    public function competitor(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'competitor_brand_id');
    }

    /**
     * Validate that position is between 1 and 3.
     */
    public static function boot(): void
    {
        parent::boot();

        static::saving(function (BrandCompetitor $competitor) {
            if ($competitor->position < 1 || $competitor->position > 3) {
                throw new \InvalidArgumentException('Position must be between 1 and 3');
            }

            if ($competitor->brand_id === $competitor->competitor_brand_id) {
                throw new \InvalidArgumentException('A brand cannot be its own competitor');
            }
        });
    }
}
