# Test Updates Required for Attribute Refactor

## Overview

The attribute system refactor changes the underlying data model and business logic. The following tests need to be updated to reflect the new attribute configuration system.

## Test Files Requiring Updates

### 1. `tests/Feature/ApprovalWorkflowTest.php`

**Changes Needed:**
- Replace `attribute_type` and `review_required` with new fields
- Update test fixtures to use `editable`, `is_sync`, `needs_approval`
- Test new approval logic:
  - `needs_approval='yes'` → always requires approval
  - `needs_approval='only_low_confidence'` → requires approval when confidence < 0.8
  - `needs_approval='no'` → auto-approves
- Test that `is_sync='no'` also sets `value_live` when approving
- Remove references to `eav_input` table

**Example Updates:**
```php
// OLD:
$attribute = Attribute::create([
    'attribute_type' => 'versioned',
    'review_required' => 'always',
    'is_synced' => false,
]);

// NEW:
$attribute = Attribute::create([
    'editable' => 'yes',
    'is_pipeline' => 'no',
    'is_sync' => 'no',
    'needs_approval' => 'yes',
]);
```

### 2. `tests/Unit/AttributeServiceTest.php`

**Changes Needed:**
- Add tests for new validation rules:
  - ❌ `(editable='yes' OR editable='overridable') + is_sync='from_external'`
  - ❌ `is_pipeline='yes' + editable='yes'`
  - ❌ `needs_approval + is_sync='from_external'`
- Update existing validation tests to use new field names
- Test that `Attribute::validateConfiguration()` throws correct exceptions

**New Test Cases:**
```php
/** @test */
public function editable_attributes_cannot_sync_from_external()
{
    $this->expectException(ValidationException::class);
    
    $attribute = Attribute::factory()->create([
        'editable' => 'yes',
        'is_sync' => 'from_external',
    ]);
    
    $attribute->validateConfiguration();
}

/** @test */
public function pipeline_attributes_cannot_be_directly_editable()
{
    $this->expectException(ValidationException::class);
    
    $attribute = Attribute::factory()->create([
        'editable' => 'yes',
        'is_pipeline' => 'yes',
    ]);
    
    $attribute->validateConfiguration();
}

/** @test */
public function external_synced_attributes_cannot_require_approval()
{
    $this->expectException(ValidationException::class);
    
    $attribute = Attribute::factory()->create([
        'is_sync' => 'from_external',
        'needs_approval' => 'yes',
    ]);
    
    $attribute->validateConfiguration();
}

/** @test */
public function overridable_pipeline_attributes_are_valid()
{
    $attribute = Attribute::factory()->create([
        'editable' => 'overridable',
        'is_pipeline' => 'yes',
    ]);
    
    $this->assertNotNull($attribute);
    // Should not throw
}
```

### 3. `tests/Feature/EntityBrowsingTest.php`

**Changes Needed:**
- Test editable modes:
  - `editable='yes'` → field is enabled
  - `editable='no'` → field is disabled
  - `editable='overridable'` → sets override value
- Test that setting `editable='no'` attributes throws exception
- Update to use `eav_versioned` instead of `eav_input`
- Test value flow:
  - `editable='yes'` + `needs_approval='no'` → sets both current and approved
  - `editable='yes'` + `needs_approval='yes'` → sets only current
  - `editable='overridable'` → sets only override

**Example Test:**
```php
/** @test */
public function setting_overridable_attribute_sets_override_value()
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
    
    // Set initial value
    DB::table('eav_versioned')->insert([
        'entity_id' => $entity->id,
        'attribute_id' => $attribute->id,
        'value_current' => 'original',
        'value_approved' => 'original',
        'value_live' => 'original',
        'value_override' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    // Set override
    $entity->test_attr = 'override';
    
    $record = DB::table('eav_versioned')
        ->where('entity_id', $entity->id)
        ->where('attribute_id', $attribute->id)
        ->first();
    
    $this->assertEquals('original', $record->value_current);
    $this->assertEquals('override', $record->value_override);
}

/** @test */
public function setting_readonly_attribute_throws_exception()
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
```

### 4. `tests/Feature/MagentoJobTest.php`

**Changes Needed:**
- Update to use `is_sync` instead of `attribute_type` and `is_synced`
- Test sync direction logic:
  - `is_sync='from_external'` → updates from Magento
  - `is_sync='to_external'` → sends to Magento
  - `is_sync='no'` → not synced
- Test initial import: ALL synced attributes (both directions) get imported
- Test subsequent sync: Only `from_external` updates from Magento
- Test export: Only `to_external` with `value_approved != value_live` are sent
- Remove references to `eav_input` table

**Example Test:**
```php
/** @test */
public function initial_import_sets_all_synced_attributes()
{
    // Setup attributes with different sync directions
    $fromExternal = Attribute::factory()->create([
        'name' => 'from_attr',
        'is_sync' => 'from_external',
        'editable' => 'no',
    ]);
    
    $toExternal = Attribute::factory()->create([
        'name' => 'to_attr',
        'is_sync' => 'to_external',
        'editable' => 'yes',
    ]);
    
    // Mock Magento response
    Http::fake([
        '*/products' => Http::response([[
            'sku' => 'TEST-SKU',
            'from_attr' => 'from_value',
            'to_attr' => 'to_value',
        ]]),
    ]);
    
    // Run sync
    $this->artisan('sync:magento', ['entityType' => 'product']);
    
    // Both attributes should be imported
    $entity = Entity::where('entity_id', 'TEST-SKU')->first();
    
    $fromRecord = DB::table('eav_versioned')
        ->where('entity_id', $entity->id)
        ->where('attribute_id', $fromExternal->id)
        ->first();
    
    $toRecord = DB::table('eav_versioned')
        ->where('entity_id', $entity->id)
        ->where('attribute_id', $toExternal->id)
        ->first();
    
    // Both should have all three value fields set
    $this->assertEquals('from_value', $fromRecord->value_current);
    $this->assertEquals('from_value', $fromRecord->value_approved);
    $this->assertEquals('from_value', $fromRecord->value_live);
    
    $this->assertEquals('to_value', $toRecord->value_current);
    $this->assertEquals('to_value', $toRecord->value_approved);
    $this->assertEquals('to_value', $toRecord->value_live);
}

/** @test */
public function subsequent_sync_only_updates_from_external()
{
    // Create entity with both types of synced attributes
    $entity = Entity::factory()->create();
    
    $fromExternal = Attribute::factory()->create([
        'name' => 'from_attr',
        'is_sync' => 'from_external',
    ]);
    
    $toExternal = Attribute::factory()->create([
        'name' => 'to_attr',
        'is_sync' => 'to_external',
    ]);
    
    // Set initial values
    DB::table('eav_versioned')->insert([
        [
            'entity_id' => $entity->id,
            'attribute_id' => $fromExternal->id,
            'value_current' => 'old_from',
            'value_approved' => 'old_from',
            'value_live' => 'old_from',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'entity_id' => $entity->id,
            'attribute_id' => $toExternal->id,
            'value_current' => 'old_to',
            'value_approved' => 'old_to',
            'value_live' => 'old_to',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
    
    // Mock Magento response
    Http::fake([
        '*/products/*' => Http::response([
            'sku' => $entity->entity_id,
            'from_attr' => 'new_from',
            'to_attr' => 'new_to',
        ]),
    ]);
    
    // Run sync
    $this->artisan('sync:magento', ['entityType' => 'product']);
    
    $fromRecord = DB::table('eav_versioned')
        ->where('entity_id', $entity->id)
        ->where('attribute_id', $fromExternal->id)
        ->first();
    
    $toRecord = DB::table('eav_versioned')
        ->where('entity_id', $entity->id)
        ->where('attribute_id', $toExternal->id)
        ->first();
    
    // from_external should be updated
    $this->assertEquals('new_from', $fromRecord->value_current);
    
    // to_external should NOT be updated
    $this->assertEquals('old_to', $toRecord->value_current);
}
```

## Database Factory Updates

Update `database/factories/AttributeFactory.php`:

```php
public function definition(): array
{
    return [
        'entity_type_id' => EntityType::factory(),
        'name' => fake()->unique()->word(),
        'data_type' => fake()->randomElement(['text', 'integer', 'select']),
        'editable' => 'yes',
        'is_pipeline' => 'no',
        'is_sync' => 'no',
        'needs_approval' => 'no',
        'allowed_values' => null,
        'linked_entity_type_id' => null,
        'ui_class' => null,
    ];
}

// Add factory states for common configurations
public function readonly(): static
{
    return $this->state(fn (array $attributes) => [
        'editable' => 'no',
    ]);
}

public function fromExternal(): static
{
    return $this->state(fn (array $attributes) => [
        'editable' => 'no',
        'is_sync' => 'from_external',
        'needs_approval' => 'no',
    ]);
}

public function toExternal(): static
{
    return $this->state(fn (array $attributes) => [
        'editable' => 'yes',
        'is_sync' => 'to_external',
        'needs_approval' => 'yes',
    ]);
}

public function overridable(): static
{
    return $this->state(fn (array $attributes) => [
        'editable' => 'overridable',
    ]);
}
```

## Running Updated Tests

After updating tests, run:

```bash
# Run all tests
docker exec spim_app php artisan test

# Run specific test file
docker exec spim_app php artisan test --filter ApprovalWorkflowTest

# Run with coverage
docker exec spim_app php artisan test --coverage
```

## Continuous Integration

Update CI/CD pipelines to:
1. Run migration in test environment
2. Seed test data with new attribute configurations
3. Run full test suite
4. Check for linting errors on modified files



