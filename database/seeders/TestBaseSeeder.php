<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EntityType;
use App\Models\Attribute;

class TestBaseSeeder extends Seeder
{
    public function run(): void
    {
        $productType = EntityType::query()->firstOrCreate(['name' => 'product'], ['description' => 'Product']);

        $attrs = [
            ['name' => 'title', 'data_type' => 'text', 'attribute_type' => 'versioned'],
            ['name' => 'brand', 'data_type' => 'text', 'attribute_type' => 'input'],
            ['name' => 'weight', 'data_type' => 'integer', 'attribute_type' => 'versioned'],
            ['name' => 'tags', 'data_type' => 'multiselect', 'attribute_type' => 'versioned', 'allowed_values' => ['NEW' => 'New','SALE' => 'Sale']],
        ];

        foreach ($attrs as $a) {
            Attribute::query()->firstOrCreate(
                ['entity_type_id' => $productType->id, 'name' => $a['name']],
                array_merge(
                    $a,
                    [
                        'entity_type_id' => $productType->id,
                        'review_required' => 'no',
                        'is_synced' => false,
                    ]
                )
            );
        }
    }
}
