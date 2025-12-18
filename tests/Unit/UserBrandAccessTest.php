<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserBrandAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    public function test_user_can_have_brands(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();

        $user->brands()->attach($brand);

        $this->assertCount(1, $user->brands);
        $this->assertEquals($brand->id, $user->brands->first()->id);
    }

    public function test_user_can_have_multiple_brands(): void
    {
        $user = User::factory()->create();
        $brands = Brand::factory()->count(3)->create();

        $user->brands()->attach($brands);

        $this->assertCount(3, $user->brands);
    }

    public function test_can_access_brand_returns_true_for_assigned_brand(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();

        $user->brands()->attach($brand);

        $this->assertTrue($user->canAccessBrand($brand));
    }

    public function test_can_access_brand_returns_false_for_unassigned_brand(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();

        $this->assertFalse($user->canAccessBrand($brand));
    }

    public function test_admin_can_access_any_brand(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $brand = Brand::factory()->create();

        // Admin is not explicitly assigned to brand
        $this->assertTrue($admin->canAccessBrand($brand));
    }

    public function test_accessible_brand_ids_returns_assigned_brands(): void
    {
        $user = User::factory()->create();
        $assignedBrands = Brand::factory()->count(2)->create();
        $unassignedBrand = Brand::factory()->create();

        $user->brands()->attach($assignedBrands);

        $accessibleIds = $user->accessibleBrandIds();

        $this->assertCount(2, $accessibleIds);
        $this->assertContains($assignedBrands[0]->id, $accessibleIds);
        $this->assertContains($assignedBrands[1]->id, $accessibleIds);
        $this->assertNotContains($unassignedBrand->id, $accessibleIds);
    }

    public function test_admin_accessible_brand_ids_returns_all_brands(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $brands = Brand::factory()->count(5)->create();

        $accessibleIds = $admin->accessibleBrandIds();

        $this->assertCount(5, $accessibleIds);
        foreach ($brands as $brand) {
            $this->assertContains($brand->id, $accessibleIds);
        }
    }

    public function test_brand_users_relationship(): void
    {
        $brand = Brand::factory()->create();
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            $user->brands()->attach($brand);
        }

        $this->assertCount(3, $brand->users);
    }

    public function test_pivot_table_has_timestamps(): void
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create();

        $user->brands()->attach($brand);

        $pivot = $user->brands()->first()->pivot;
        $this->assertNotNull($pivot->created_at);
        $this->assertNotNull($pivot->updated_at);
    }
}
