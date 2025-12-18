<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function preferences()
    {
        return $this->hasMany(UserPreference::class);
    }

    /**
     * Price alerts created by this user.
     */
    public function priceAlerts()
    {
        return $this->hasMany(PriceAlert::class);
    }

    /**
     * Brands this user has access to (for suppliers).
     */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'supplier_brand_access')
            ->withTimestamps();
    }

    /**
     * Check if user can access a specific brand.
     */
    public function canAccessBrand(Brand $brand): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        return $this->brands()->where('brands.id', $brand->id)->exists();
    }

    /**
     * Get array of brand IDs this user can access.
     *
     * @return array<int>
     */
    public function accessibleBrandIds(): array
    {
        if ($this->hasRole('admin')) {
            return Brand::pluck('id')->toArray();
        }

        return $this->brands()->pluck('brands.id')->toArray();
    }

    /**
     * Check if user has access to premium features.
     *
     * Admins and users with 'view-premium-features' permission have access.
     */
    public function hasPremiumAccess(): bool
    {
        return $this->hasRole('admin') || $this->can('view-premium-features');
    }

    /**
     * Check if user has premium access for a specific brand.
     *
     * Checks both user permissions and brand access level.
     */
    public function hasPremiumAccessForBrand(?Brand $brand = null): bool
    {
        // Admin always has premium access
        if ($this->hasRole('admin')) {
            return true;
        }

        // User must have premium permission
        if (! $this->can('view-premium-features')) {
            return false;
        }

        // If no brand specified, just check user permission
        if ($brand === null) {
            return true;
        }

        // Check if brand is premium tier and user has access to it
        return $brand->isPremium() && $this->canAccessBrand($brand);
    }

    /**
     * Determine if the user can access a Filament panel.
     *
     * This method is called by Filament before the panel's middleware.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Admins can access all panels
        if ($this->hasRole('admin')) {
            return true;
        }

        // Check panel-specific access
        return match ($panel->getId()) {
            'pim' => $this->hasRole('pim-editor'),
            'supply' => $this->hasAnyRole(['supplier-basic', 'supplier-premium']),
            'pricing' => $this->hasRole('pricing-analyst'),
            default => false,
        };
    }

    /**
     * Configure activity logging for User model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => "User {$eventName}");
    }
}
