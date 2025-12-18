<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for premium feature gating functionality.
 *
 * Verifies that:
 * - Basic users cannot access premium features
 * - Premium users can access premium features
 * - Admins can access all features
 * - Brand-level premium access works correctly
 */
class PremiumGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_has_premium_access(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertTrue($admin->hasPremiumAccess());
    }

    public function test_premium_supplier_has_premium_access(): void
    {
        $supplier = User::factory()->create();
        $supplier->assignRole('supplier-premium');

        $this->assertTrue($supplier->hasPremiumAccess());
    }

    public function test_basic_supplier_does_not_have_premium_access(): void
    {
        $supplier = User::factory()->create();
        $supplier->assignRole('supplier-basic');

        $this->assertFalse($supplier->hasPremiumAccess());
    }

    public function test_pim_editor_does_not_have_premium_access(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole('pim-editor');

        $this->assertFalse($editor->hasPremiumAccess());
    }

    public function test_admin_has_premium_access_for_any_brand(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $brand = Brand::factory()->create([
            'access_level' => 'basic',
        ]);

        $this->assertTrue($admin->hasPremiumAccessForBrand($brand));
    }

    public function test_premium_supplier_has_access_to_premium_brand(): void
    {
        $supplier = User::factory()->create();
        $supplier->assignRole('supplier-premium');

        $brand = Brand::factory()->create([
            'access_level' => 'premium',
        ]);

        // Assign brand to supplier
        $supplier->brands()->attach($brand->id);

        $this->assertTrue($supplier->hasPremiumAccessForBrand($brand));
    }

    public function test_premium_supplier_does_not_have_access_to_basic_brand(): void
    {
        $supplier = User::factory()->create();
        $supplier->assignRole('supplier-premium');

        $brand = Brand::factory()->create([
            'access_level' => 'basic',
        ]);

        // Assign brand to supplier
        $supplier->brands()->attach($brand->id);

        $this->assertFalse($supplier->hasPremiumAccessForBrand($brand));
    }

    public function test_basic_supplier_does_not_have_access_to_premium_brand(): void
    {
        $supplier = User::factory()->create();
        $supplier->assignRole('supplier-basic');

        $brand = Brand::factory()->create([
            'access_level' => 'premium',
        ]);

        // Assign brand to supplier
        $supplier->brands()->attach($brand->id);

        $this->assertFalse($supplier->hasPremiumAccessForBrand($brand));
    }

    public function test_premium_supplier_without_brand_access_cannot_access_premium_brand(): void
    {
        $supplier = User::factory()->create();
        $supplier->assignRole('supplier-premium');

        $brand = Brand::factory()->create([
            'access_level' => 'premium',
        ]);

        // Don't assign brand to supplier

        $this->assertFalse($supplier->hasPremiumAccessForBrand($brand));
    }

    public function test_premium_access_without_brand_checks_user_permission_only(): void
    {
        $premiumSupplier = User::factory()->create();
        $premiumSupplier->assignRole('supplier-premium');

        $basicSupplier = User::factory()->create();
        $basicSupplier->assignRole('supplier-basic');

        $this->assertTrue($premiumSupplier->hasPremiumAccessForBrand(null));
        $this->assertFalse($basicSupplier->hasPremiumAccessForBrand(null));
    }

    public function test_unauthenticated_user_does_not_have_premium_access(): void
    {
        $brand = Brand::factory()->create([
            'access_level' => 'premium',
        ]);

        // Create component without authentication
        $component = new \App\Filament\Shared\Components\PremiumGate('Test Feature', $brand);

        $this->assertFalse($component->hasPremiumAccess);
    }

    public function test_premium_gate_component_grants_access_to_premium_user(): void
    {
        $supplier = User::factory()->create();
        $supplier->assignRole('supplier-premium');

        $brand = Brand::factory()->create([
            'access_level' => 'premium',
        ]);
        $supplier->brands()->attach($brand->id);

        $this->actingAs($supplier);

        $component = new \App\Filament\Shared\Components\PremiumGate('Test Feature', $brand);

        $this->assertTrue($component->hasPremiumAccess);
        $this->assertEquals('Test Feature', $component->feature);
        $this->assertEquals($brand->id, $component->brand->id);
    }

    public function test_premium_gate_component_denies_access_to_basic_user(): void
    {
        $supplier = User::factory()->create();
        $supplier->assignRole('supplier-basic');

        $brand = Brand::factory()->create([
            'access_level' => 'basic',
        ]);
        $supplier->brands()->attach($brand->id);

        $this->actingAs($supplier);

        $component = new \App\Filament\Shared\Components\PremiumGate('Test Feature', $brand);

        $this->assertFalse($component->hasPremiumAccess);
    }

    public function test_premium_locked_placeholder_has_correct_properties(): void
    {
        $component = new \App\Filament\Shared\Components\PremiumLockedPlaceholder(
            feature: 'Advanced Analytics',
            contactEmail: 'test@example.com',
            title: 'Premium Only',
            description: 'Contact us for'
        );

        $this->assertEquals('Advanced Analytics', $component->feature);
        $this->assertEquals('test@example.com', $component->contactEmail);
        $this->assertEquals('Premium Only', $component->title);
        $this->assertEquals('Contact us for', $component->description);
    }

    public function test_premium_locked_placeholder_has_default_values(): void
    {
        $component = new \App\Filament\Shared\Components\PremiumLockedPlaceholder;

        $this->assertEquals('this feature', $component->feature);
        $this->assertEquals('sales@silvertreebrands.com', $component->contactEmail);
        $this->assertEquals('Premium Feature', $component->title);
        $this->assertEquals('Upgrade to access', $component->description);
    }

    public function test_view_premium_features_permission_exists(): void
    {
        $this->assertDatabaseHas('permissions', [
            'name' => 'view-premium-features',
            'guard_name' => 'web',
        ]);
    }

    public function test_supplier_basic_role_does_not_have_premium_permission(): void
    {
        $role = \Spatie\Permission\Models\Role::findByName('supplier-basic');

        $this->assertFalse($role->hasPermissionTo('view-premium-features'));
    }

    public function test_supplier_premium_role_has_premium_permission(): void
    {
        $role = \Spatie\Permission\Models\Role::findByName('supplier-premium');

        $this->assertTrue($role->hasPermissionTo('view-premium-features'));
    }

    public function test_admin_role_has_premium_permission(): void
    {
        $role = \Spatie\Permission\Models\Role::findByName('admin');

        $this->assertTrue($role->hasPermissionTo('view-premium-features'));
    }
}
