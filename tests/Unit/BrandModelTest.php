<?php

namespace Tests\Unit;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_brand(): void
    {
        $brand = Brand::factory()->create([
            'name' => 'Test Brand',
            'company_id' => 3,
            'access_level' => 'basic',
        ]);

        $this->assertDatabaseHas('brands', [
            'name' => 'Test Brand',
            'company_id' => 3,
            'access_level' => 'basic',
        ]);
    }

    public function test_is_premium_returns_correct_value(): void
    {
        $basicBrand = Brand::factory()->basic()->create();
        $premiumBrand = Brand::factory()->premium()->create();

        $this->assertFalse($basicBrand->isPremium());
        $this->assertTrue($premiumBrand->isPremium());
    }

    public function test_is_basic_returns_correct_value(): void
    {
        $basicBrand = Brand::factory()->basic()->create();
        $premiumBrand = Brand::factory()->premium()->create();

        $this->assertTrue($basicBrand->isBasic());
        $this->assertFalse($premiumBrand->isBasic());
    }

    public function test_scope_for_company_filters_correctly(): void
    {
        Brand::factory()->forCompany(3)->count(3)->create();
        Brand::factory()->forCompany(5)->count(2)->create();
        Brand::factory()->forCompany(9)->count(1)->create();

        $this->assertCount(3, Brand::forCompany(3)->get());
        $this->assertCount(2, Brand::forCompany(5)->get());
        $this->assertCount(1, Brand::forCompany(9)->get());
    }

    public function test_scope_premium_filters_correctly(): void
    {
        Brand::factory()->basic()->count(3)->create();
        Brand::factory()->premium()->count(2)->create();

        $this->assertCount(2, Brand::premium()->get());
    }

    public function test_scope_basic_filters_correctly(): void
    {
        Brand::factory()->basic()->count(3)->create();
        Brand::factory()->premium()->count(2)->create();

        $this->assertCount(3, Brand::basic()->get());
    }

    public function test_synced_at_is_cast_to_datetime(): void
    {
        $brand = Brand::factory()->synced()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $brand->synced_at);
    }

    public function test_company_id_is_cast_to_integer(): void
    {
        $brand = Brand::factory()->create(['company_id' => '3']);

        $this->assertIsInt($brand->company_id);
    }

    public function test_unique_constraint_on_name_and_company(): void
    {
        Brand::factory()->create([
            'name' => 'Unique Brand',
            'company_id' => 3,
        ]);

        // Same name, different company should work
        $brand2 = Brand::factory()->create([
            'name' => 'Unique Brand',
            'company_id' => 5,
        ]);
        $this->assertDatabaseHas('brands', ['id' => $brand2->id]);

        // Same name, same company should fail
        $this->expectException(\Illuminate\Database\QueryException::class);
        Brand::factory()->create([
            'name' => 'Unique Brand',
            'company_id' => 3,
        ]);
    }

    public function test_default_access_level_is_basic(): void
    {
        $brand = Brand::create([
            'name' => 'New Brand',
            'company_id' => 3,
        ]);

        // Refresh to get the database default value
        $brand->refresh();

        $this->assertEquals('basic', $brand->access_level);
    }
}
