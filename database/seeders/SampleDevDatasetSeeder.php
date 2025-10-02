<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EntityType;
use App\Models\Attribute;
use App\Models\Entity;

class SampleDevDatasetSeeder extends Seeder
{
    public function run(): void
    {
        $productType = EntityType::query()->firstOrCreate(['name' => 'product'], ['description' => 'Product']);

        $title = Attribute::query()->firstOrCreate([
            'entity_type_id' => $productType->id,
            'name' => 'title',
        ], [
            'data_type' => 'text',
            'attribute_type' => 'versioned',
            'review_required' => 'no',
        ]);

        $brand = Attribute::query()->firstOrCreate([
            'entity_type_id' => $productType->id,
            'name' => 'brand',
        ], [
            'data_type' => 'text',
            'attribute_type' => 'input',
            'review_required' => 'no',
        ]);

        $weight = Attribute::query()->firstOrCreate([
            'entity_type_id' => $productType->id,
            'name' => 'weight',
        ], [
            'data_type' => 'integer',
            'attribute_type' => 'versioned',
            'review_required' => 'no',
        ]);

        // Create a few entities
        for ($i = 1; $i <= 5; $i++) {
            $e = Entity::query()->create([
                'id' => str()->ulid()->toBase32(),
                'entity_type_id' => $productType->id,
                'entity_id' => 'sku'.str_pad((string)$i, 4, '0', STR_PAD_LEFT),
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
