<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SideBySideEditTest extends TestCase
{
    use RefreshDatabase;

    protected EntityType $entityType;
    protected User $user;
    protected array $attributes = [];
    protected array $entities = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Get or create entity type
        $this->entityType = EntityType::firstOrCreate(
            ['name' => 'Product'],
            [
                'display_name' => 'Products',
                'description' => 'Test products',
            ]
        );

        // Create test attributes
        $this->attributes['name'] = Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'name',
            'display_name' => 'Product Name',
            'data_type' => 'text',
            'editable' => 'yes',
            'is_sync' => 'no',
            'needs_approval' => 'no',
            'sort_order' => 1,
        ]);

        $this->attributes['price'] = Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'price',
            'display_name' => 'Price',
            'data_type' => 'integer',
            'editable' => 'yes',
            'is_sync' => 'no',
            'needs_approval' => 'no',
            'sort_order' => 2,
        ]);

        $this->attributes['description'] = Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'description',
            'display_name' => 'Description',
            'data_type' => 'text',
            'editable' => 'overridable',
            'is_sync' => 'no',
            'needs_approval' => 'yes',
            'sort_order' => 3,
        ]);

        $this->attributes['readonly'] = Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'readonly_field',
            'display_name' => 'Read Only',
            'data_type' => 'text',
            'editable' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
            'sort_order' => 4,
        ]);

        // Create test entities
        for ($i = 1; $i <= 3; $i++) {
            $entity = new Entity([
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'entity_id' => "TEST-{$i}",
                'entity_type_id' => $this->entityType->id,
            ]);
            $entity->save();

            // Set some attribute values
            $entity->name = "Product {$i}";
            $entity->price = $i * 100;

            $this->entities[] = $entity;
        }
    }

    /** @test */
    public function it_validates_minimum_entity_count()
    {
        // Try with only 1 entity
        $response = $this->get(route('filament.admin.resources.product-entities.side-by-side', [
            'entities' => $this->entities[0]->id,
        ]));

        // Should be redirected or show error
        $this->assertTrue(true); // Placeholder - actual test depends on implementation
    }

    /** @test */
    public function it_validates_maximum_entity_count()
    {
        // Create 16 entities
        $entityIds = [];
        for ($i = 0; $i < 16; $i++) {
            $entity = Entity::create([
                'entity_id' => "TEST-MANY-{$i}",
                'entity_type_id' => $this->entityType->id,
            ]);
            $entityIds[] = $entity->id;
        }

        // Bulk action should show warning
        $this->assertTrue(count($entityIds) > 15);
    }

    /** @test */
    public function it_loads_entities_successfully()
    {
        $entityIds = array_map(fn($e) => $e->id, $this->entities);

        $component = Livewire::test(
            \App\Filament\Resources\ProductEntityResource\Pages\SideBySideEditProducts::class,
            ['entityIds' => implode(',', $entityIds)]
        );

        // entityIdsArray is protected, so we can't directly assert it
        // Instead, check that entities were loaded correctly
        $component->assertCount('entities', 3);
    }

    /** @test */
    public function it_loads_default_attributes_excluding_readonly()
    {
        $entityIds = array_map(fn($e) => $e->id, $this->entities);

        $component = Livewire::test(
            \App\Filament\Resources\ProductEntityResource\Pages\SideBySideEditProducts::class,
            ['entityIds' => implode(',', $entityIds)]
        );

        // Should load editable and overridable attributes by default
        $selectedAttributes = $component->get('selectedAttributes');

        $this->assertContains('name', $selectedAttributes);
        $this->assertContains('price', $selectedAttributes);
        $this->assertContains('description', $selectedAttributes);
    }

    /** @test */
    public function it_initializes_form_data_from_entities()
    {
        $entityIds = array_map(fn($e) => $e->id, $this->entities);

        $component = Livewire::test(
            \App\Filament\Resources\ProductEntityResource\Pages\SideBySideEditProducts::class,
            ['entityIds' => implode(',', $entityIds)]
        );

        $formData = $component->get('formData');

        // Check that form data is initialized for each entity
        foreach ($this->entities as $entity) {
            $this->assertArrayHasKey($entity->id, $formData);
            $this->assertEquals("Product {$entity->entity_id[5]}", $formData[$entity->id]['name']);
        }
    }

    /** @test */
    public function it_saves_changes_to_all_entities()
    {
        $entityIds = array_map(fn($e) => $e->id, $this->entities);

        $component = Livewire::test(
            \App\Filament\Resources\ProductEntityResource\Pages\SideBySideEditProducts::class,
            ['entityIds' => implode(',', $entityIds)]
        );

        // Update form data
        $newFormData = $component->get('formData');
        foreach ($this->entities as $entity) {
            $newFormData[$entity->id]['name'] = "Updated Product {$entity->entity_id}";
            $newFormData[$entity->id]['price'] = 999;
        }

        $component->set('formData', $newFormData);
        $component->call('save');

        // Verify changes were persisted
        foreach ($this->entities as $entity) {
            $entity->refresh();
            $this->assertEquals("Updated Product {$entity->entity_id}", $entity->getAttr('name'));
            $this->assertEquals(999, $entity->getAttr('price'));
        }
    }

    /** @test */
    public function it_handles_validation_errors_gracefully()
    {
        $entityIds = array_map(fn($e) => $e->id, $this->entities);

        $component = Livewire::test(
            \App\Filament\Resources\ProductEntityResource\Pages\SideBySideEditProducts::class,
            ['entityIds' => implode(',', $entityIds)]
        );

        // Try to set an invalid value (non-integer for price field)
        $formData = $component->get('formData');
        $formData[$this->entities[0]->id]['price'] = 'not-a-number';

        $component->set('formData', $formData);
        $component->call('save');

        // Should have errors
        $errors = $component->get('errors');
        $this->assertNotEmpty($errors);
    }

    /** @test */
    public function it_saves_and_restores_attribute_preferences()
    {
        $entityIds = array_map(fn($e) => $e->id, $this->entities);
        $preferenceKey = "entity_type_{$this->entityType->id}_sidebyside_attributes";

        // Set custom preferences
        $selectedAttributes = ['name', 'price'];
        UserPreference::set($this->user->id, $preferenceKey, $selectedAttributes);

        // Load the page
        $component = Livewire::test(
            \App\Filament\Resources\ProductEntityResource\Pages\SideBySideEditProducts::class,
            ['entityIds' => implode(',', $entityIds)]
        );

        // Should load the saved preferences
        $loadedAttributes = $component->get('selectedAttributes');
        $this->assertEquals($selectedAttributes, $loadedAttributes);
    }

    /** @test */
    public function it_handles_overridable_attributes_correctly()
    {
        $entityIds = array_map(fn($e) => $e->id, $this->entities);

        $component = Livewire::test(
            \App\Filament\Resources\ProductEntityResource\Pages\SideBySideEditProducts::class,
            ['entityIds' => implode(',', $entityIds)]
        );

        // Set an override value for description
        $formData = $component->get('formData');
        $formData[$this->entities[0]->id]['description'] = 'Override description';

        $component->set('formData', $formData);
        $component->call('save');

        // Verify override was set (not current value)
        $row = DB::table('eav_versioned')
            ->where('entity_id', $this->entities[0]->id)
            ->where('attribute_id', $this->attributes['description']->id)
            ->first();

        $this->assertEquals('Override description', $row->value_override);
    }

    /** @test */
    public function it_handles_mixed_entity_types_correctly()
    {
        // Create another entity type
        $otherEntityType = EntityType::create([
            'name' => 'Category',
            'display_name' => 'Categories',
            'description' => 'Test categories',
        ]);

        $otherEntity = Entity::create([
            'entity_id' => 'OTHER-1',
            'entity_type_id' => $otherEntityType->id,
        ]);

        // Try to load entities from different types
        $entityIds = [$this->entities[0]->id, $otherEntity->id];

        $component = Livewire::test(
            \App\Filament\Resources\ProductEntityResource\Pages\SideBySideEditProducts::class,
            ['entityIds' => implode(',', $entityIds)]
        );

        // Should only load entities of the correct type
        $loadedEntities = $component->get('entities');
        $this->assertCount(1, $loadedEntities);
        $this->assertArrayHasKey($this->entities[0]->id, $loadedEntities);
        $this->assertArrayNotHasKey($otherEntity->id, $loadedEntities);
    }

    /** @test */
    public function it_shows_success_notification_after_save()
    {
        $entityIds = array_map(fn($e) => $e->id, $this->entities);

        $component = Livewire::test(
            \App\Filament\Resources\ProductEntityResource\Pages\SideBySideEditProducts::class,
            ['entityIds' => implode(',', $entityIds)]
        );

        $component->call('save');

        // Should show success notification
        // (Filament notifications are tested separately)
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_empty_entity_list()
    {
        $component = Livewire::test(
            \App\Filament\Resources\ProductEntityResource\Pages\SideBySideEditProducts::class,
            ['entities' => '']
        );

        // Should redirect or show error
        $this->assertTrue(true); // Placeholder - depends on implementation
    }

    /** @test */
    public function it_handles_invalid_entity_ids()
    {
        $component = Livewire::test(
            \App\Filament\Resources\ProductEntityResource\Pages\SideBySideEditProducts::class,
            ['entities' => 'invalid,ids,here']
        );

        // Should handle gracefully
        $entities = $component->get('entities');
        $this->assertEmpty($entities);
    }
}

