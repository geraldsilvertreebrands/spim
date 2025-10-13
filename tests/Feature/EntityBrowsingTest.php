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
}
