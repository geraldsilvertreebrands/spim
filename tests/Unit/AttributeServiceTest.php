<?php

namespace Tests\Unit;

use App\Models\Attribute;
use App\Services\AttributeService;
use App\Models\EntityType;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttributeServiceTest extends TestCase
{
    protected AttributeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AttributeService::class);
    }

    public function test_validate_allows_select_values(): void
    {
        $type = EntityType::factory()->create();
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $type->id,
            'data_type' => 'select',
            'allowed_values' => ['A' => 'Alpha', 'B' => 'Beta'],
        ]);

        $this->service->validateValue($attribute, 'A');
        $this->assertTrue(true);
    }

    public function test_validate_rejects_invalid_select_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $type = EntityType::factory()->create();
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $type->id,
            'data_type' => 'select',
            'allowed_values' => ['A' => 'Alpha'],
        ]);

        $this->service->validateValue($attribute, 'X');
    }

    public function test_coerce_multiselect_returns_json_string(): void
    {
        $type = EntityType::factory()->create();
        $attribute = Attribute::factory()->create([
            'entity_type_id' => $type->id,
            'data_type' => 'multiselect',
            'allowed_values' => ['A' => 'Alpha', 'B' => 'Beta'],
        ]);

        $encoded = $this->service->coerceIn($attribute, ['A','B']);
        $this->assertJson($encoded);
    }

    public function test_validate_configuration_rules(): void
    {
        // Rule 1: (editable yes/overridable) + is_sync from_external => invalid
        $type = EntityType::factory()->create();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        // This should throw during save due to validation
        Attribute::factory()->create([
            'entity_type_id' => $type->id,
            'editable' => 'yes',
            'is_sync' => 'from_external',
        ]);
    }

    public function test_pipeline_attributes_cannot_be_directly_editable(): void
    {
        $type = EntityType::factory()->create();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        // This should throw during save due to validation
        Attribute::factory()->create([
            'entity_type_id' => $type->id,
            'editable' => 'yes',
            'is_pipeline' => 'yes',
        ]);
    }

    public function test_external_synced_attributes_cannot_require_approval(): void
    {
        $type = EntityType::factory()->create();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        // This should throw during save due to validation
        Attribute::factory()->create([
            'entity_type_id' => $type->id,
            'is_sync' => 'from_external',
            'needs_approval' => 'yes',
        ]);
    }

    public function test_overridable_pipeline_attributes_are_valid(): void
    {
        $type = EntityType::factory()->create();
        $attr = Attribute::factory()->create([
            'entity_type_id' => $type->id,
            'editable' => 'overridable',
            'is_pipeline' => 'yes',
        ]);

        // Should not throw
        $this->assertNotNull($attr);
        $attr->validateConfiguration();
        $this->assertTrue(true);
    }
}
