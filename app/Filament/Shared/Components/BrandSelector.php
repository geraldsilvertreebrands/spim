<?php

namespace App\Filament\Shared\Components;

use App\Models\Brand;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Brand selector component for the Supply portal.
 *
 * Restricts brand options to only those the current user has access to.
 * Admins can see all brands, suppliers only see their assigned brands.
 */
class BrandSelector
{
    /**
     * Create a brand selector field.
     */
    public static function make(string $name = 'brand_id'): Select
    {
        return Select::make($name)
            ->label('Brand')
            ->relationship(
                name: 'brand',
                titleAttribute: 'name',
                modifyQueryUsing: fn (Builder $query) => self::scopeToUserBrands($query)
            )
            ->searchable()
            ->preload()
            ->required()
            ->placeholder('Select a brand');
    }

    /**
     * Create a standalone brand selector (not using relationship).
     */
    public static function makeStandalone(string $name = 'brand_id'): Select
    {
        return Select::make($name)
            ->label('Brand')
            ->options(fn () => self::getAccessibleBrandOptions())
            ->searchable()
            ->required()
            ->placeholder('Select a brand');
    }

    /**
     * Scope query to only brands the user can access.
     */
    protected static function scopeToUserBrands(Builder $query): Builder
    {
        $user = Auth::user();

        if (! $user) {
            return $query->whereRaw('1 = 0'); // No results for unauthenticated
        }

        // Admins can see all brands
        if ($user->hasRole('admin')) {
            return $query;
        }

        // Get brand IDs this user can access
        $accessibleBrandIds = $user->accessibleBrandIds();

        return $query->whereIn('id', $accessibleBrandIds);
    }

    /**
     * Get brand options as array for the current user.
     *
     * @return array<int, string>
     */
    protected static function getAccessibleBrandOptions(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        // Admins can see all brands
        if ($user->hasRole('admin')) {
            return Brand::orderBy('name')->pluck('name', 'id')->toArray();
        }

        // Get only brands this user can access
        $accessibleBrandIds = $user->accessibleBrandIds();

        return Brand::whereIn('id', $accessibleBrandIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}
