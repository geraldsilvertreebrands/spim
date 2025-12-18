<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\BrandCompetitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandCompetitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_brand_competitor(): void
    {
        $brand = Brand::factory()->create();
        $competitor = Brand::factory()->create();

        $brandCompetitor = BrandCompetitor::create([
            'brand_id' => $brand->id,
            'competitor_brand_id' => $competitor->id,
            'position' => 1,
        ]);

        $this->assertDatabaseHas('brand_competitors', [
            'brand_id' => $brand->id,
            'competitor_brand_id' => $competitor->id,
            'position' => 1,
        ]);
    }

    public function test_brand_relationship_works(): void
    {
        $brand = Brand::factory()->create();
        $competitor = Brand::factory()->create();

        $brandCompetitor = BrandCompetitor::create([
            'brand_id' => $brand->id,
            'competitor_brand_id' => $competitor->id,
            'position' => 1,
        ]);

        $this->assertEquals($brand->id, $brandCompetitor->brand->id);
    }

    public function test_competitor_relationship_works(): void
    {
        $brand = Brand::factory()->create();
        $competitor = Brand::factory()->create();

        $brandCompetitor = BrandCompetitor::create([
            'brand_id' => $brand->id,
            'competitor_brand_id' => $competitor->id,
            'position' => 1,
        ]);

        $this->assertEquals($competitor->id, $brandCompetitor->competitor->id);
    }

    public function test_position_must_be_between_1_and_3(): void
    {
        $brand = Brand::factory()->create();
        $competitor = Brand::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Position must be between 1 and 3');

        BrandCompetitor::create([
            'brand_id' => $brand->id,
            'competitor_brand_id' => $competitor->id,
            'position' => 4,
        ]);
    }

    public function test_position_cannot_be_zero(): void
    {
        $brand = Brand::factory()->create();
        $competitor = Brand::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Position must be between 1 and 3');

        BrandCompetitor::create([
            'brand_id' => $brand->id,
            'competitor_brand_id' => $competitor->id,
            'position' => 0,
        ]);
    }

    public function test_brand_cannot_be_its_own_competitor(): void
    {
        $brand = Brand::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A brand cannot be its own competitor');

        BrandCompetitor::create([
            'brand_id' => $brand->id,
            'competitor_brand_id' => $brand->id,
            'position' => 1,
        ]);
    }

    public function test_unique_constraint_on_brand_and_position(): void
    {
        $brand = Brand::factory()->create();
        $competitor1 = Brand::factory()->create();
        $competitor2 = Brand::factory()->create();

        BrandCompetitor::create([
            'brand_id' => $brand->id,
            'competitor_brand_id' => $competitor1->id,
            'position' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        BrandCompetitor::create([
            'brand_id' => $brand->id,
            'competitor_brand_id' => $competitor2->id,
            'position' => 1, // Same position for same brand
        ]);
    }

    public function test_unique_constraint_on_brand_and_competitor(): void
    {
        $brand = Brand::factory()->create();
        $competitor = Brand::factory()->create();

        BrandCompetitor::create([
            'brand_id' => $brand->id,
            'competitor_brand_id' => $competitor->id,
            'position' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        BrandCompetitor::create([
            'brand_id' => $brand->id,
            'competitor_brand_id' => $competitor->id, // Same competitor
            'position' => 2,
        ]);
    }

    public function test_brand_can_have_multiple_competitors(): void
    {
        $brand = Brand::factory()->create();
        $competitors = Brand::factory()->count(3)->create();

        foreach ($competitors as $index => $competitor) {
            BrandCompetitor::create([
                'brand_id' => $brand->id,
                'competitor_brand_id' => $competitor->id,
                'position' => $index + 1,
            ]);
        }

        $this->assertCount(3, $brand->competitors);
    }
}
