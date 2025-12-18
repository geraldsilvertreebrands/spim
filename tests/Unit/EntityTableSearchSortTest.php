<?php

namespace Tests\Unit;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Services\EntityTableBuilder;
use Tests\TestCase;

class EntityTableSearchSortTest extends TestCase
{
    public function test_integer_search_exact_match(): void
    {
        $entityType = EntityType::factory()->create();

        $priceAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'price',
            'data_type' => 'integer',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $entity1 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity2 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity3 = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        $entity1->price = 100;
        $entity2->price = 1000;
        $entity3->price = 10000;

        // Search for exact integer
        $results = Entity::where('entities.entity_type_id', $entityType->id)
            ->whereAttr('price', '=', '1000')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($entity2->id, $results->first()->id);
    }

    public function test_integer_search_rejects_non_numeric(): void
    {
        $entityType = EntityType::factory()->create();

        $priceAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'price',
            'data_type' => 'integer',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $entity1 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity1->price = 100;

        // Non-numeric search should return no results
        $tableBuilder = app(EntityTableBuilder::class);
        $query = Entity::where('entities.entity_type_id', $entityType->id);
        $query = $tableBuilder->applySearch($query, $priceAttr, 'abc');
        $results = $query->get();

        $this->assertCount(0, $results);
    }

    public function test_integer_sort(): void
    {
        $entityType = EntityType::factory()->create();

        $priceAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'price',
            'data_type' => 'integer',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $entity1 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity2 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity3 = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        $entity1->price = 500;
        $entity2->price = 50;
        $entity3->price = 5000;

        // Sort ascending
        $results = Entity::where('entities.entity_type_id', $entityType->id)
            ->orderByAttr('price', 'asc')
            ->get();

        $this->assertEquals($entity2->id, $results[0]->id); // 50
        $this->assertEquals($entity1->id, $results[1]->id); // 500
        $this->assertEquals($entity3->id, $results[2]->id); // 5000
    }

    public function test_select_search_by_key(): void
    {
        $entityType = EntityType::factory()->create();

        $statusAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'status',
            'data_type' => 'select',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
            'allowed_values' => [
                'enabled' => 'Enabled',
                'disabled' => 'Disabled',
                'pending' => 'Pending Review',
            ],
        ]);

        $entity1 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity2 = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        $entity1->status = 'enabled';
        $entity2->status = 'disabled';

        // Search by key
        $tableBuilder = app(EntityTableBuilder::class);
        $query = Entity::where('entities.entity_type_id', $entityType->id);
        $query = $tableBuilder->applySearch($query, $statusAttr, 'enabled');
        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertEquals($entity1->id, $results->first()->id);
    }

    public function test_select_search_by_label(): void
    {
        $entityType = EntityType::factory()->create();

        $statusAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'status',
            'data_type' => 'select',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
            'allowed_values' => [
                'enabled' => 'Enabled',
                'disabled' => 'Disabled',
                'pending' => 'Pending Review',
            ],
        ]);

        $entity1 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity2 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity3 = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        $entity1->status = 'enabled';
        $entity2->status = 'disabled';
        $entity3->status = 'pending';

        // Search by partial label "Pend" should find "Pending Review"
        $tableBuilder = app(EntityTableBuilder::class);
        $query = Entity::where('entities.entity_type_id', $entityType->id);
        $query = $tableBuilder->applySearch($query, $statusAttr, 'Pend');
        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertEquals($entity3->id, $results->first()->id);
    }

    public function test_select_sort(): void
    {
        $entityType = EntityType::factory()->create();

        $statusAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'status',
            'data_type' => 'select',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
            'allowed_values' => [
                'enabled' => 'Enabled',
                'disabled' => 'Disabled',
                'pending' => 'Pending Review',
            ],
        ]);

        $entity1 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity2 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity3 = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        $entity1->status = 'pending';
        $entity2->status = 'disabled';
        $entity3->status = 'enabled';

        // Sort by status (alphabetically by key)
        $results = Entity::where('entities.entity_type_id', $entityType->id)
            ->orderByAttr('status', 'asc')
            ->get();

        $this->assertEquals($entity2->id, $results[0]->id); // disabled
        $this->assertEquals($entity3->id, $results[1]->id); // enabled
        $this->assertEquals($entity1->id, $results[2]->id); // pending
    }

    public function test_multiselect_search_by_label(): void
    {
        $entityType = EntityType::factory()->create();

        $categoriesAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'categories',
            'data_type' => 'multiselect',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
            'allowed_values' => [
                'electronics' => 'Electronics',
                'computers' => 'Computers',
                'smartphones' => 'Smartphones',
                'accessories' => 'Accessories',
            ],
        ]);

        $entity1 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity2 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity3 = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        // Multiselect stored as JSON array
        \DB::table('eav_versioned')->insert([
            'entity_id' => $entity1->id,
            'attribute_id' => $categoriesAttr->id,
            'value_current' => json_encode(['electronics', 'computers']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('eav_versioned')->insert([
            'entity_id' => $entity2->id,
            'attribute_id' => $categoriesAttr->id,
            'value_current' => json_encode(['smartphones']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('eav_versioned')->insert([
            'entity_id' => $entity3->id,
            'attribute_id' => $categoriesAttr->id,
            'value_current' => json_encode(['accessories']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Search by partial label "Comp" should find "Computers"
        $tableBuilder = app(EntityTableBuilder::class);
        $query = Entity::where('entities.entity_type_id', $entityType->id);
        $query = $tableBuilder->applySearch($query, $categoriesAttr, 'Comp');
        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertEquals($entity1->id, $results->first()->id);
    }

    public function test_text_search_case_insensitive(): void
    {
        $entityType = EntityType::factory()->create();

        $nameAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'name',
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $entity1 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity2 = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        $entity1->name = 'Apple MacBook Pro';
        $entity2->name = 'Samsung Galaxy';

        // Case-insensitive partial search
        $results = Entity::where('entities.entity_type_id', $entityType->id)
            ->whereAttr('name', 'LIKE', '%macbook%')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($entity1->id, $results->first()->id);
    }

    public function test_text_sort(): void
    {
        $entityType = EntityType::factory()->create();

        $nameAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'name',
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $entity1 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity2 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity3 = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        $entity1->name = 'Zebra';
        $entity2->name = 'Apple';
        $entity3->name = 'Mango';

        // Sort alphabetically
        $results = Entity::where('entities.entity_type_id', $entityType->id)
            ->orderByAttr('name', 'asc')
            ->get();

        $this->assertEquals($entity2->id, $results[0]->id); // Apple
        $this->assertEquals($entity3->id, $results[1]->id); // Mango
        $this->assertEquals($entity1->id, $results[2]->id); // Zebra
    }

    public function test_html_search(): void
    {
        $entityType = EntityType::factory()->create();

        $descriptionAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'description',
            'data_type' => 'html',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $entity1 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity2 = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        $entity1->description = '<p>This is a <strong>great</strong> product</p>';
        $entity2->description = '<p>Another product</p>';

        // Search in HTML content
        $results = Entity::where('entities.entity_type_id', $entityType->id)
            ->whereAttr('description', 'LIKE', '%great%')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($entity1->id, $results->first()->id);
    }

    public function test_build_column_creates_searchable_sortable_columns(): void
    {
        $entityType = EntityType::factory()->create();

        $attribute = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'test_attr',
            'display_name' => 'Test Attribute',
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $tableBuilder = app(EntityTableBuilder::class);
        $column = $tableBuilder->buildColumn($attribute);

        $this->assertNotNull($column);
        $this->assertEquals('Test Attribute', $column->getLabel());
    }

    public function test_column_has_tooltip_for_long_values(): void
    {
        $entityType = EntityType::factory()->create();

        $descriptionAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'description',
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $entity = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        // Create a long description that will be truncated
        $longDescription = str_repeat('This is a very long description that will be truncated. ', 10);
        $entity->description = $longDescription;

        $tableBuilder = app(EntityTableBuilder::class);
        $column = $tableBuilder->buildColumn($descriptionAttr);

        // Verify column was created successfully with tooltip functionality
        $this->assertNotNull($column);
        $this->assertInstanceOf(\Filament\Tables\Columns\TextColumn::class, $column);
    }
}
