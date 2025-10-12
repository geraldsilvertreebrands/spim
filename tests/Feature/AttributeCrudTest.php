<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\EntityType;
use App\Models\Entity;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AttributeCrudTest extends TestCase
{
    use WithFaker;

    public function test_can_create_entity_type(): void
    {
        $type = EntityType::create([
            'name' => 'test_type',
            'display_name' => 'Test Type',
            'description' => 'Test type',
        ]);

        $this->assertModelExists($type);
    }

    public function test_entity_model_reads_and_writes_versioned_attribute(): void
    {
        $type = EntityType::factory()->create();
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $type->id,
            'name' => 'title',
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        $entity = Entity::factory()->create([
            'entity_type_id' => $type->id,
        ]);

        $entity->{$attribute->name} = 'Test Value';
        $entity->refresh();

        $this->assertEquals('Test Value', $entity->getAttr('title'));
    }
}
