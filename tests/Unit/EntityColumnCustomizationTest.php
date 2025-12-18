<?php

namespace Tests\Unit;

use App\Models\Attribute;
use App\Models\EntityType;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\EntityTableBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class EntityColumnCustomizationTest extends TestCase
{
    use RefreshDatabase;

    protected EntityType $entityType;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Get the existing Product entity type from seeder
        $this->entityType = EntityType::where('name', 'Product')->firstOrFail();

        // Create a user
        $this->user = User::factory()->create();
    }

    public function test_user_preference_set_creates_new_preference(): void
    {
        $key = 'test_key';
        $value = ['test', 'value'];

        UserPreference::set($this->user->id, $key, $value);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $this->user->id,
            'key' => $key,
        ]);

        $retrieved = UserPreference::get($this->user->id, $key);
        $this->assertEquals($value, $retrieved);
    }

    public function test_user_preference_set_updates_existing_preference(): void
    {
        $key = 'test_key';
        $initialValue = ['initial'];
        $updatedValue = ['updated'];

        // Create initial preference
        UserPreference::set($this->user->id, $key, $initialValue);
        $firstId = UserPreference::where('user_id', $this->user->id)
            ->where('key', $key)
            ->first()->id;

        // Update preference
        UserPreference::set($this->user->id, $key, $updatedValue);

        // Should still have only one record
        $count = UserPreference::where('user_id', $this->user->id)
            ->where('key', $key)
            ->count();
        $this->assertEquals(1, $count);

        // Should have same ID (updated, not created new)
        $secondId = UserPreference::where('user_id', $this->user->id)
            ->where('key', $key)
            ->first()->id;
        $this->assertEquals($firstId, $secondId);

        // Value should be updated
        $retrieved = UserPreference::get($this->user->id, $key);
        $this->assertEquals($updatedValue, $retrieved);
    }

    public function test_user_preference_get_returns_default_when_not_found(): void
    {
        $default = ['default', 'value'];
        $retrieved = UserPreference::get($this->user->id, 'non_existent_key', $default);

        $this->assertEquals($default, $retrieved);
    }

    public function test_user_preference_get_returns_null_when_not_found_and_no_default(): void
    {
        $retrieved = UserPreference::get($this->user->id, 'non_existent_key');

        $this->assertNull($retrieved);
    }

    public function test_entity_table_builder_uses_default_columns_when_no_preference(): void
    {
        Auth::login($this->user);

        $builder = app(EntityTableBuilder::class);
        $columns = $builder->buildColumns($this->entityType);

        // Should have at least entity_id column
        $this->assertGreaterThanOrEqual(1, count($columns));

        // First column should be entity_id
        $columnNames = array_map(fn ($col) => $col->getName(), $columns);
        $this->assertEquals('entity_id', $columnNames[0]);
    }

    public function test_entity_table_builder_respects_user_preferences(): void
    {
        // Get some actual attributes from the seeded data
        $attributes = Attribute::where('entity_type_id', $this->entityType->id)
            ->limit(3)
            ->pluck('name')
            ->toArray();

        $this->assertNotEmpty($attributes, 'No attributes found for testing');

        // Set user preference
        $preferenceKey = "entity_type_{$this->entityType->id}_columns";
        UserPreference::set($this->user->id, $preferenceKey, $attributes);

        Auth::login($this->user);

        $builder = app(EntityTableBuilder::class);
        $columns = $builder->buildColumns($this->entityType);

        // Should have entity_id + selected attributes
        $this->assertCount(count($attributes) + 1, $columns);

        $columnNames = array_map(fn ($col) => $col->getName(), $columns);

        // First should be entity_id
        $this->assertEquals('entity_id', $columnNames[0]);

        // Rest should match selected attributes
        $actualAttributeColumns = array_slice($columnNames, 1);
        $this->assertEquals($attributes, $actualAttributeColumns);
    }

    public function test_entity_table_builder_maintains_column_order(): void
    {
        // Get attributes and set a specific order
        $allAttributes = Attribute::where('entity_type_id', $this->entityType->id)
            ->limit(5)
            ->pluck('name')
            ->toArray();

        if (count($allAttributes) < 3) {
            $this->markTestSkipped('Not enough attributes for this test');
        }

        // Reverse the order to test ordering is respected
        $selectedAttributes = array_reverse(array_slice($allAttributes, 0, 3));

        $preferenceKey = "entity_type_{$this->entityType->id}_columns";
        UserPreference::set($this->user->id, $preferenceKey, $selectedAttributes);

        Auth::login($this->user);

        $builder = app(EntityTableBuilder::class);
        $columns = $builder->buildColumns($this->entityType);

        $columnNames = array_map(fn ($col) => $col->getName(), $columns);

        // Skip entity_id and check the rest
        $actualAttributeColumns = array_slice($columnNames, 1);

        // Order should match our reversed selection
        $this->assertEquals($selectedAttributes, $actualAttributeColumns);
    }

    public function test_entity_table_builder_uses_display_name_for_labels(): void
    {
        // Create an attribute specifically for this test with a display_name
        $attribute = Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'test_custom_label',
            'display_name' => 'Custom Display Label',
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
            'sort_order' => 999,
        ]);

        $preferenceKey = "entity_type_{$this->entityType->id}_columns";
        UserPreference::set($this->user->id, $preferenceKey, [$attribute->name]);

        Auth::login($this->user);

        $builder = app(EntityTableBuilder::class);
        $columns = $builder->buildColumns($this->entityType);

        // Find the attribute column (skip entity_id)
        $attributeColumn = $columns[1] ?? null;

        $this->assertNotNull($attributeColumn);
        $this->assertEquals($attribute->name, $attributeColumn->getName());
        $this->assertEquals('Custom Display Label', $attributeColumn->getLabel());
    }

    public function test_entity_table_builder_uses_fallback_label_when_display_name_is_null(): void
    {
        // Create an attribute without a display_name
        $attribute = Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'test_snake_case_name',
            'display_name' => null,
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
            'sort_order' => 999,
        ]);

        $preferenceKey = "entity_type_{$this->entityType->id}_columns";
        UserPreference::set($this->user->id, $preferenceKey, [$attribute->name]);

        Auth::login($this->user);

        $builder = app(EntityTableBuilder::class);
        $columns = $builder->buildColumns($this->entityType);

        // Find the attribute column (skip entity_id)
        $attributeColumn = $columns[1] ?? null;

        $this->assertNotNull($attributeColumn);
        $this->assertEquals($attribute->name, $attributeColumn->getName());
        // Should use the formatted name as fallback
        $this->assertEquals('Test snake case name', $attributeColumn->getLabel());
    }

    public function test_different_users_have_independent_preferences(): void
    {
        $user2 = User::factory()->create();

        $allAttributes = Attribute::where('entity_type_id', $this->entityType->id)
            ->limit(4)
            ->pluck('name')
            ->toArray();

        if (count($allAttributes) < 2) {
            $this->markTestSkipped('Not enough attributes for this test');
        }

        $preferenceKey = "entity_type_{$this->entityType->id}_columns";

        // Set different preferences for each user
        $user1Columns = [$allAttributes[0]];
        $user2Columns = array_slice($allAttributes, 1, 2);

        UserPreference::set($this->user->id, $preferenceKey, $user1Columns);
        UserPreference::set($user2->id, $preferenceKey, $user2Columns);

        // Test user 1
        Auth::login($this->user);
        $builder = app(EntityTableBuilder::class);
        $columns1 = $builder->buildColumns($this->entityType);
        $columnNames1 = array_map(fn ($col) => $col->getName(), $columns1);

        // Test user 2
        Auth::logout();
        Auth::login($user2);
        $columns2 = $builder->buildColumns($this->entityType);
        $columnNames2 = array_map(fn ($col) => $col->getName(), $columns2);

        // Columns should be different
        $this->assertNotEquals($columnNames1, $columnNames2);

        // User 1 should have entity_id + 1 column
        $this->assertCount(2, $columns1);
        $this->assertEquals($user1Columns[0], $columnNames1[1]);

        // User 2 should have entity_id + 2 columns
        $this->assertCount(3, $columns2);
        $this->assertEquals($user2Columns, array_slice($columnNames2, 1));
    }

    public function test_entity_table_builder_handles_empty_preference_array(): void
    {
        $preferenceKey = "entity_type_{$this->entityType->id}_columns";
        UserPreference::set($this->user->id, $preferenceKey, []);

        Auth::login($this->user);

        $builder = app(EntityTableBuilder::class);
        $columns = $builder->buildColumns($this->entityType);

        // Should only return entity_id column
        $this->assertCount(1, $columns);
        $this->assertEquals('entity_id', $columns[0]->getName());
    }

    public function test_entity_table_builder_handles_invalid_attribute_names_gracefully(): void
    {
        $validAttribute = Attribute::where('entity_type_id', $this->entityType->id)
            ->first();

        if (! $validAttribute) {
            $this->markTestSkipped('No attributes found for testing');
        }

        // Mix valid and invalid attribute names
        $preferenceKey = "entity_type_{$this->entityType->id}_columns";
        UserPreference::set($this->user->id, $preferenceKey, [
            'non_existent_attribute',
            $validAttribute->name,
            'another_invalid_attribute',
        ]);

        Auth::login($this->user);

        $builder = app(EntityTableBuilder::class);
        $columns = $builder->buildColumns($this->entityType);

        // Should only include entity_id and the valid attribute
        $this->assertCount(2, $columns);
        $columnNames = array_map(fn ($col) => $col->getName(), $columns);
        $this->assertEquals('entity_id', $columnNames[0]);
        $this->assertEquals($validAttribute->name, $columnNames[1]);
    }

    public function test_entity_table_builder_works_with_explicitly_provided_attributes(): void
    {
        $attributes = Attribute::where('entity_type_id', $this->entityType->id)
            ->limit(2)
            ->pluck('name')
            ->toArray();

        if (count($attributes) < 2) {
            $this->markTestSkipped('Not enough attributes for this test');
        }

        // Don't set user preference, but provide attributes directly
        $builder = app(EntityTableBuilder::class);
        $columns = $builder->buildColumns($this->entityType, $attributes);

        // Should respect the provided attributes, not user preference
        $this->assertCount(count($attributes) + 1, $columns);

        $columnNames = array_map(fn ($col) => $col->getName(), $columns);
        $this->assertEquals('entity_id', $columnNames[0]);
        $this->assertEquals($attributes, array_slice($columnNames, 1));
    }
}
