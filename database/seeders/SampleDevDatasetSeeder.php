<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use Illuminate\Database\Seeder;

class SampleDevDatasetSeeder extends Seeder
{
    public function run(): void
    {
        $productType = EntityType::query()->firstOrCreate(['name' => 'product'], [
            'display_name' => 'Products',
            'description' => 'Product entities',
        ]);

        $categoryType = EntityType::query()->firstOrCreate(['name' => 'category'], [
            'display_name' => 'Categories',
            'description' => 'Category entities',
        ]);

        $title = Attribute::query()->firstOrCreate([
            'entity_type_id' => $productType->id,
            'name' => 'title',
        ], [
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $brand = Attribute::query()->firstOrCreate([
            'entity_type_id' => $productType->id,
            'name' => 'brand',
        ], [
            'data_type' => 'text',
            'editable' => 'no',
            'is_pipeline' => 'no',
            'is_sync' => 'from_external',
            'needs_approval' => 'no',
        ]);

        $weight = Attribute::query()->firstOrCreate([
            'entity_type_id' => $productType->id,
            'name' => 'weight',
        ], [
            'data_type' => 'integer',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        // Create category attributes
        Attribute::query()->firstOrCreate([
            'entity_type_id' => $categoryType->id,
            'name' => 'name',
        ], [
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        Attribute::query()->firstOrCreate([
            'entity_type_id' => $categoryType->id,
            'name' => 'description',
        ], [
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        // Create a few entities
        for ($i = 1; $i <= 5; $i++) {
            $e = Entity::query()->create([
                'id' => str()->ulid()->toBase32(),
                'entity_type_id' => $productType->id,
                'entity_id' => 'sku'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            ]);

            // Seed some values
            \DB::table('eav_versioned')->updateOrInsert([
                'entity_id' => $e->id,
                'attribute_id' => $title->id,
            ], [
                'value_current' => 'Sample Product '.$i,
                'value_approved' => 'Sample Product '.$i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \DB::table('eav_input')->updateOrInsert([
                'entity_id' => $e->id,
                'attribute_id' => $brand->id,
            ], [
                'value' => 'Acme',
                'source' => 'seed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \DB::table('eav_versioned')->updateOrInsert([
                'entity_id' => $e->id,
                'attribute_id' => $weight->id,
            ], [
                'value_current' => (string) (100 + $i),
                'value_approved' => (string) (100 + $i),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
