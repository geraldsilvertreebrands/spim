<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\EntityType;
use Illuminate\Database\Seeder;

class TestBaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed roles and permissions
        $this->call(RoleSeeder::class);

        $productType = EntityType::query()->firstOrCreate(['name' => 'product'], [
            'display_name' => 'Products',
            'description' => 'Product entities',
        ]);

        $categoryType = EntityType::query()->firstOrCreate(['name' => 'category'], [
            'display_name' => 'Categories',
            'description' => 'Category entities',
        ]);

        $attrs = [
            ['name' => 'title', 'data_type' => 'text', 'editable' => 'yes', 'is_pipeline' => 'no', 'is_sync' => 'no', 'needs_approval' => 'no'],
            ['name' => 'brand', 'data_type' => 'text', 'editable' => 'no', 'is_pipeline' => 'no', 'is_sync' => 'from_external', 'needs_approval' => 'no'],
            ['name' => 'weight', 'data_type' => 'integer', 'editable' => 'yes', 'is_pipeline' => 'no', 'is_sync' => 'no', 'needs_approval' => 'no'],
            ['name' => 'tags', 'data_type' => 'multiselect', 'editable' => 'yes', 'is_pipeline' => 'no', 'is_sync' => 'no', 'needs_approval' => 'no', 'allowed_values' => ['NEW' => 'New', 'SALE' => 'Sale']],
        ];

        foreach ($attrs as $a) {
            Attribute::query()->firstOrCreate(
                ['entity_type_id' => $productType->id, 'name' => $a['name']],
                array_merge(
                    $a,
                    [
                        'entity_type_id' => $productType->id,
                    ]
                )
            );
        }
    }
}
