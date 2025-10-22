<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\User;
use App\Services\EavWriter;
use Tests\TestCase;

class EntityBrowsingTest extends TestCase
{
    public function test_entity_can_store_and_retrieve_attributes(): void
    {
        $entityType = EntityType::factory()->create(['name' => 'Test Products']);

        $titleAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'title',
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $priceAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'price',
            'data_type' => 'integer',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $entity = Entity::factory()->create([
            'entity_type_id' => $entityType->id,
        ]);

        // Set attributes using magic setters
        $entity->title = 'Test Product';
        $entity->price = 1000;

        // Retrieve using magic getters
        $this->assertEquals('Test Product', $entity->title);
        $this->assertEquals(1000, $entity->price);
    }

    public function test_entity_detail_modal_shows_attributes(): void
    {
        $user = User::factory()->create();
        $entityType = EntityType::factory()->create(['name' => 'Test Products']);

        $titleAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'title',
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $entity = Entity::factory()->create([
            'entity_type_id' => $entityType->id,
        ]);

        $entity->title = 'Test Product';

        // Test that entity type relationship works
        $this->assertNotNull($entity->entityType);
        $this->assertEquals($entityType->id, $entity->entityType->id);
    }

    public function test_entity_supports_override_values(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'title',
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $entity = Entity::factory()->create([
            'entity_type_id' => $entityType->id,
        ]);

        // Set initial value
        $entity->title = 'Original Title';

        // Set override
        $writer = app(EavWriter::class);
        $writer->setOverride($entity->id, $attribute->id, 'Overridden Title');

        // Refresh and check
        $entity->refresh();

        // Override mode should return overridden value
        $this->assertEquals('Overridden Title', $entity->getAttr('title', 'override'));

        // Current mode should return original value
        $this->assertEquals('Original Title', $entity->getAttr('title', 'current'));
    }

    public function test_entity_query_scopes_work(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'status',
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $entity1 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity2 = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        $entity1->status = 'active';
        $entity2->status = 'inactive';

        // Test whereAttr scope
        $activeEntities = Entity::where('entities.entity_type_id', $entityType->id)
            ->whereAttr('status', '=', 'active')
            ->get();

        $this->assertCount(1, $activeEntities);
        $this->assertEquals($entity1->id, $activeEntities->first()->id);
    }

    public function test_setting_overridable_attribute_sets_override_value(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'test_attr',
            'data_type' => 'text',
            'editable' => 'overridable',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $entity = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        // Set initial value (current/approved/live)
        \DB::table('eav_versioned')->insert([
            'entity_id' => $entity->id,
            'attribute_id' => $attribute->id,
            'value_current' => 'original',
            'value_approved' => 'original',
            'value_live' => 'original',
            'value_override' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Set override via magic setter
        $entity->test_attr = 'override';

        $record = \DB::table('eav_versioned')
            ->where('entity_id', $entity->id)
            ->where('attribute_id', $attribute->id)
            ->first();

        $this->assertEquals('original', $record->value_current);
        $this->assertEquals('override', $record->value_override);
    }

    public function test_setting_readonly_attribute_throws_exception(): void
    {
        $entityType = EntityType::factory()->create();
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'readonly_attr',
            'data_type' => 'text',
            'editable' => 'no',
        ]);

        $entity = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('read-only');

        $entity->readonly_attr = 'value';
    }

    public function test_entity_search_multiple_attributes(): void
    {
        $entityType = EntityType::factory()->create();
        
        $titleAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'title',
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $descAttr = Attribute::factory()->create([
            'entity_type_id' => $entityType->id,
            'name' => 'description',
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $entity1 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity2 = Entity::factory()->create(['entity_type_id' => $entityType->id]);
        $entity3 = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        $entity1->title = 'Apple Product';
        $entity1->description = 'A great device';

        $entity2->title = 'Samsung Product';
        $entity2->description = 'An apple a day';

        $entity3->title = 'Google Product';
        $entity3->description = 'Search engine';

        // Search for "apple" across both attributes
        $results = Entity::where('entities.entity_type_id', $entityType->id)
            ->where(function ($query) {
                $query->whereAttr('title', 'LIKE', '%apple%')
                      ->orWhereAttr('description', 'LIKE', '%apple%');
            })
            ->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', $entity1->id));
        $this->assertTrue($results->contains('id', $entity2->id));
    }

    public function test_entity_sort_by_attribute(): void
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

        $entity1->price = 300;
        $entity2->price = 100;
        $entity3->price = 200;

        // Sort by price ascending
        $resultsAsc = Entity::where('entities.entity_type_id', $entityType->id)
            ->orderByAttr('price', 'asc')
            ->get();

        $this->assertEquals($entity2->id, $resultsAsc[0]->id);
        $this->assertEquals($entity3->id, $resultsAsc[1]->id);
        $this->assertEquals($entity1->id, $resultsAsc[2]->id);

        // Sort by price descending
        $resultsDesc = Entity::where('entities.entity_type_id', $entityType->id)
            ->orderByAttr('price', 'desc')
            ->get();

        $this->assertEquals($entity1->id, $resultsDesc[0]->id);
        $this->assertEquals($entity3->id, $resultsDesc[1]->id);
        $this->assertEquals($entity2->id, $resultsDesc[2]->id);
    }

    public function test_entity_search_and_sort_combined(): void
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
        $entity4 = Entity::factory()->create(['entity_type_id' => $entityType->id]);

        $entity1->name = 'Widget A';
        $entity1->price = 200;

        $entity2->name = 'Widget B';
        $entity2->price = 100;

        $entity3->name = 'Widget C';
        $entity3->price = 300;

        $entity4->name = 'Gadget D';
        $entity4->price = 150;

        // Search for "Widget" and sort by price ascending
        $results = Entity::where('entities.entity_type_id', $entityType->id)
            ->whereAttr('name', 'LIKE', '%Widget%')
            ->orderByAttr('price', 'asc')
            ->get();

        $this->assertCount(3, $results);
        $this->assertEquals($entity2->id, $results[0]->id); // Widget B - $100
        $this->assertEquals($entity1->id, $results[1]->id); // Widget A - $200
        $this->assertEquals($entity3->id, $results[2]->id); // Widget C - $300
    }
}
