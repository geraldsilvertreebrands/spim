<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Services\EavWriter;
use App\Services\EntityFilterBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EntityFilterTest extends TestCase
{
    use RefreshDatabase;

    protected EntityType $entityType;

    protected EavWriter $eavWriter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eavWriter = app(EavWriter::class);

        // Create a test entity type
        $this->entityType = EntityType::create([
            'name' => 'TestProduct',
            'display_name' => 'Test Products',
            'description' => 'Products for testing',
        ]);
    }

    public function test_text_filter_generation(): void
    {
        // Create a text attribute
        $attribute = Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'description',
            'display_name' => 'Description',
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        // Build filters
        $builder = app(EntityFilterBuilder::class);
        $filters = $builder->buildFilters($this->entityType);

        $this->assertCount(1, $filters);
        $this->assertEquals('description', $filters[0]->getName());
    }

    public function test_select_filter_generation(): void
    {
        // Create a select attribute
        $attribute = Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'status',
            'display_name' => 'Status',
            'data_type' => 'select',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
            'allowed_values' => ['active' => 'Active', 'inactive' => 'Inactive'],
        ]);

        // Build filters
        $builder = app(EntityFilterBuilder::class);
        $filters = $builder->buildFilters($this->entityType);

        $this->assertCount(1, $filters);
        $this->assertEquals('status', $filters[0]->getName());
    }

    public function test_integer_filter_generation(): void
    {
        // Create an integer attribute
        $attribute = Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'price',
            'display_name' => 'Price',
            'data_type' => 'integer',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        // Build filters
        $builder = app(EntityFilterBuilder::class);
        $filters = $builder->buildFilters($this->entityType);

        $this->assertCount(1, $filters);
        $this->assertEquals('price', $filters[0]->getName());
    }

    public function test_multiple_filters_generation(): void
    {
        // Create multiple attributes
        Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'name',
            'display_name' => 'Name',
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'status',
            'display_name' => 'Status',
            'data_type' => 'select',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
            'allowed_values' => ['active' => 'Active'],
        ]);

        Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'price',
            'display_name' => 'Price',
            'data_type' => 'integer',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        // Build filters
        $builder = app(EntityFilterBuilder::class);
        $filters = $builder->buildFilters($this->entityType);

        $this->assertCount(3, $filters);
    }

    public function test_json_attributes_are_skipped(): void
    {
        // Create a JSON attribute (should be skipped)
        Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'metadata',
            'display_name' => 'Metadata',
            'data_type' => 'json',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        // Build filters
        $builder = app(EntityFilterBuilder::class);
        $filters = $builder->buildFilters($this->entityType);

        // JSON attributes should not generate filters
        $this->assertCount(0, $filters);
    }

    public function test_text_filter_query_works(): void
    {
        // Create a text attribute
        $attribute = Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'name',
            'display_name' => 'Name',
            'data_type' => 'text',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        // Create test entities
        $entity1 = Entity::create([
            'id' => (string) Str::ulid(),
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-001',
        ]);
        $this->eavWriter->upsertVersioned($entity1->id, $attribute->id, 'Blue Widget', []);

        $entity2 = Entity::create([
            'id' => (string) Str::ulid(),
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-002',
        ]);
        $this->eavWriter->upsertVersioned($entity2->id, $attribute->id, 'Red Widget', []);

        // Test filtering
        $query = Entity::where('entity_type_id', $this->entityType->id);
        $query->whereAttr('name', 'LIKE', '%Blue%');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('TEST-001', $results->first()->entity_id);
    }

    public function test_select_filter_query_works(): void
    {
        // Create a select attribute
        $attribute = Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'status',
            'display_name' => 'Status',
            'data_type' => 'select',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
            'allowed_values' => ['active' => 'Active', 'inactive' => 'Inactive'],
        ]);

        // Create test entities
        $entity1 = Entity::create([
            'id' => (string) Str::ulid(),
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-001',
        ]);
        $this->eavWriter->upsertVersioned($entity1->id, $attribute->id, 'active', []);

        $entity2 = Entity::create([
            'id' => (string) Str::ulid(),
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-002',
        ]);
        $this->eavWriter->upsertVersioned($entity2->id, $attribute->id, 'inactive', []);

        // Test filtering for active status
        $query = Entity::where('entity_type_id', $this->entityType->id);
        $query->whereAttr('status', '=', 'active');

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('TEST-001', $results->first()->entity_id);
    }

    public function test_integer_filter_query_works(): void
    {
        // Create an integer attribute
        $attribute = Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'price',
            'display_name' => 'Price',
            'data_type' => 'integer',
            'editable' => 'yes',
            'is_pipeline' => 'no',
            'is_sync' => 'no',
            'needs_approval' => 'no',
        ]);

        // Create test entities
        $entity1 = Entity::create([
            'id' => (string) Str::ulid(),
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-001',
        ]);
        $this->eavWriter->upsertVersioned($entity1->id, $attribute->id, 100, []);

        $entity2 = Entity::create([
            'id' => (string) Str::ulid(),
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-002',
        ]);
        $this->eavWriter->upsertVersioned($entity2->id, $attribute->id, 200, []);

        // Test filtering for exact price
        $query = Entity::where('entity_type_id', $this->entityType->id);
        $query->whereAttr('price', '=', 100);

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('TEST-001', $results->first()->entity_id);
    }
}
