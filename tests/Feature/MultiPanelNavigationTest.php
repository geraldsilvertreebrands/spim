<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for multi-panel navigation and access control.
 *
 * Verifies that:
 * - Each role can access their designated panel(s)
 * - Unauthorized panel access is blocked with redirect
 * - Admin can switch between all panels
 * - Homepage redirects correctly based on role
 */
class MultiPanelNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

        // Create test brands (needed for Supply and Pricing panels)
        Brand::factory()->create(['name' => 'Test Brand', 'company_id' => 3]);
    }

    // ========================================
    // PIM Panel Access Tests
    // ========================================

    public function test_admin_can_access_pim_panel(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/pim');

        $response->assertStatus(200);
    }

    public function test_pim_editor_can_access_pim_panel(): void
    {
        $editor = User::factory()->create(['is_active' => true]);
        $editor->assignRole('pim-editor');

        $response = $this->actingAs($editor)->get('/pim');

        $response->assertStatus(200);
    }

    public function test_supplier_cannot_access_pim_panel(): void
    {
        $supplier = User::factory()->create(['is_active' => true]);
        $supplier->assignRole('supplier-basic');

        $response = $this->actingAs($supplier)->get('/pim');

        // Should return 403 Forbidden
        $response->assertForbidden();
    }

    public function test_pricing_analyst_cannot_access_pim_panel(): void
    {
        $analyst = User::factory()->create(['is_active' => true]);
        $analyst->assignRole('pricing-analyst');

        $response = $this->actingAs($analyst)->get('/pim');

        // Should return 403 Forbidden
        $response->assertForbidden();
    }

    // ========================================
    // Supply Panel Access Tests
    // ========================================

    public function test_admin_can_access_supply_panel(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->followingRedirects()->get('/supply');

        $response->assertStatus(200);
    }

    public function test_supplier_basic_can_access_supply_panel(): void
    {
        $supplier = User::factory()->create(['is_active' => true]);
        $supplier->assignRole('supplier-basic');

        $response = $this->actingAs($supplier)->followingRedirects()->get('/supply');

        $response->assertStatus(200);
    }

    public function test_supplier_premium_can_access_supply_panel(): void
    {
        $supplier = User::factory()->create(['is_active' => true]);
        $supplier->assignRole('supplier-premium');

        $response = $this->actingAs($supplier)->followingRedirects()->get('/supply');

        $response->assertStatus(200);
    }

    public function test_pim_editor_cannot_access_supply_panel(): void
    {
        $editor = User::factory()->create(['is_active' => true]);
        $editor->assignRole('pim-editor');

        $response = $this->actingAs($editor)->get('/supply');

        // Should return 403 Forbidden
        $response->assertForbidden();
    }

    public function test_pricing_analyst_cannot_access_supply_panel(): void
    {
        $analyst = User::factory()->create(['is_active' => true]);
        $analyst->assignRole('pricing-analyst');

        $response = $this->actingAs($analyst)->get('/supply');

        // Should return 403 Forbidden
        $response->assertForbidden();
    }

    // ========================================
    // Pricing Panel Access Tests
    // ========================================

    public function test_admin_can_access_pricing_panel(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->followingRedirects()->get('/pricing');

        $response->assertStatus(200);
    }

    public function test_pricing_analyst_can_access_pricing_panel(): void
    {
        $analyst = User::factory()->create(['is_active' => true]);
        $analyst->assignRole('pricing-analyst');

        $response = $this->actingAs($analyst)->followingRedirects()->get('/pricing');

        $response->assertStatus(200);
    }

    public function test_pim_editor_cannot_access_pricing_panel(): void
    {
        $editor = User::factory()->create(['is_active' => true]);
        $editor->assignRole('pim-editor');

        $response = $this->actingAs($editor)->get('/pricing');

        // Should return 403 Forbidden
        $response->assertForbidden();
    }

    public function test_supplier_cannot_access_pricing_panel(): void
    {
        $supplier = User::factory()->create(['is_active' => true]);
        $supplier->assignRole('supplier-premium');

        $response = $this->actingAs($supplier)->get('/pricing');

        // Should return 403 Forbidden
        $response->assertForbidden();
    }

    // ========================================
    // Login Page Access Tests
    // ========================================

    public function test_pim_login_page_accessible(): void
    {
        $response = $this->get('/pim/login');

        $response->assertStatus(200);
    }

    public function test_supply_login_page_accessible(): void
    {
        $response = $this->get('/supply/login');

        $response->assertStatus(200);
    }

    public function test_pricing_login_page_accessible(): void
    {
        $response = $this->get('/pricing/login');

        $response->assertStatus(200);
    }

    // ========================================
    // Old /admin URL Redirect Tests
    // ========================================

    public function test_old_admin_url_redirects_to_pim(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/pim');
    }

    public function test_old_admin_subpath_redirects_to_pim_subpath(): void
    {
        $response = $this->get('/admin/products');

        $response->assertRedirect('/pim/products');
    }

    // ========================================
    // Inactive User Tests
    // ========================================

    public function test_inactive_user_cannot_access_pim(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $user->assignRole('admin');

        $response = $this->actingAs($user)->get('/pim');

        // Should redirect to login or show forbidden
        $response->assertRedirectContains('login');
    }

    public function test_inactive_user_cannot_access_supply(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $user->assignRole('supplier-basic');

        $response = $this->actingAs($user)->get('/supply');

        // Should redirect to login
        $response->assertRedirectContains('login');
    }

    // ========================================
    // Admin Panel Switching Tests
    // ========================================

    public function test_admin_can_navigate_from_pim_to_supply(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        // First access PIM
        $this->actingAs($admin)->get('/pim')->assertStatus(200);

        // Then switch to Supply (follows redirect to dashboard)
        $response = $this->actingAs($admin)->followingRedirects()->get('/supply');

        $response->assertStatus(200);
    }

    public function test_admin_can_navigate_from_pim_to_pricing(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        // First access PIM
        $this->actingAs($admin)->get('/pim')->assertStatus(200);

        // Then switch to Pricing (follows redirect to dashboard)
        $response = $this->actingAs($admin)->followingRedirects()->get('/pricing');

        $response->assertStatus(200);
    }

    public function test_admin_can_navigate_between_all_panels(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        // Access all three panels in sequence (follow redirects to respective dashboards)
        $this->actingAs($admin)->get('/pim')->assertStatus(200);
        $this->actingAs($admin)->followingRedirects()->get('/supply')->assertStatus(200);
        $this->actingAs($admin)->followingRedirects()->get('/pricing')->assertStatus(200);
        $this->actingAs($admin)->get('/pim')->assertStatus(200);
    }

    // ========================================
    // Panel Branding Tests
    // ========================================

    public function test_pim_panel_has_correct_branding(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/pim');

        $response->assertSee('Silvertree PIM');
    }

    public function test_supply_panel_has_correct_branding(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->followingRedirects()->get('/supply');

        $response->assertSee('Supplier Portal');
    }

    public function test_pricing_panel_has_correct_branding(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->followingRedirects()->get('/pricing');

        $response->assertSee('Pricing Tool');
    }
}
