<?php

namespace Tests\Feature;

use App\Models\EntityType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationLoadsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);

        // Create admin user
        $this->admin = User::factory()->create([
            'email' => 'admin@test.com',
            'is_active' => true,
        ]);
        $this->admin->assignRole('admin');

        // Create entity types needed for routes
        // Note: Entity type names must match what the Resources return in getEntityTypeName()
        EntityType::firstOrCreate(
            ['name' => 'product'],
            ['display_name' => 'Product']
        );
        EntityType::firstOrCreate(
            ['name' => 'Categories'],
            ['display_name' => 'Categories']
        );
    }

    public function test_login_page_renders(): void
    {
        $response = $this->get('/pim/login');

        $response->assertStatus(200);
        $response->assertSee('Sign in');
    }

    public function test_dashboard_loads_for_authenticated_user(): void
    {
        $response = $this->actingAs($this->admin)->get('/pim');

        $response->assertStatus(200);
    }

    public function test_products_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get('/pim/products');

        $response->assertStatus(200);
    }

    public function test_categories_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get('/pim/categories');

        $response->assertStatus(200);
    }

    public function test_attributes_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get('/pim/attributes');

        $response->assertStatus(200);
    }

    public function test_attribute_sections_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get('/pim/attribute-sections');

        $response->assertStatus(200);
    }

    public function test_pipelines_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get('/pim/pipelines');

        $response->assertStatus(200);
    }

    public function test_magento_sync_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get('/pim/magento-sync');

        $response->assertStatus(200);
    }

    public function test_users_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get('/pim/users');

        $response->assertStatus(200);
    }

    public function test_entity_types_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get('/pim/entity-types');

        $response->assertStatus(200);
    }

    public function test_review_queue_page_loads(): void
    {
        $response = $this->actingAs($this->admin)->get('/pim/review-queue');

        $response->assertStatus(200);
    }

    public function test_no_php_errors_on_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get('/pim');

        $response->assertStatus(200);
        // Check for PHP error indicators (not UI text like "Error handling")
        $response->assertDontSee('PHP Error');
        $response->assertDontSee('Whoops!');
        $response->assertDontSee('Stack trace:');
        $response->assertDontSee('Fatal error');
    }

    public function test_homepage_redirects_unauthenticated_to_pim_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/pim/login');
    }

    public function test_homepage_redirects_admin_to_pim(): void
    {
        $response = $this->actingAs($this->admin)->get('/');

        $response->assertRedirect('/pim');
    }

    public function test_homepage_redirects_pim_editor_to_pim(): void
    {
        $pimEditor = User::factory()->create(['is_active' => true]);
        $pimEditor->assignRole('pim-editor');

        $response = $this->actingAs($pimEditor)->get('/');

        $response->assertRedirect('/pim');
    }

    public function test_homepage_redirects_supplier_to_supply(): void
    {
        $supplier = User::factory()->create(['is_active' => true]);
        $supplier->assignRole('supplier-basic');

        $response = $this->actingAs($supplier)->get('/');

        $response->assertRedirect('/supply');
    }

    public function test_homepage_redirects_pricing_analyst_to_pricing(): void
    {
        $pricingAnalyst = User::factory()->create(['is_active' => true]);
        $pricingAnalyst->assignRole('pricing-analyst');

        $response = $this->actingAs($pricingAnalyst)->get('/');

        $response->assertRedirect('/pricing');
    }
}
