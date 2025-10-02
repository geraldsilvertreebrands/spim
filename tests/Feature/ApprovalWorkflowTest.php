<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\User;
use App\Services\EavWriter;
use App\Services\ReviewQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected EntityType $entityType;
    protected Entity $entity;
    protected Attribute $alwaysReviewAttr;
    protected Attribute $lowConfidenceAttr;
    protected Attribute $noReviewAttr;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create entity type
        $this->entityType = EntityType::create([
            'name' => 'Test Product',
            'description' => 'Test products',
        ]);

        // Create test attributes with different review requirements
        $this->alwaysReviewAttr = Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'always_review_field',
            'display_name' => 'Always Review Field',
            'data_type' => 'text',
            'attribute_type' => 'versioned',
            'review_required' => 'always',
        ]);

        $this->lowConfidenceAttr = Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'low_confidence_field',
            'display_name' => 'Low Confidence Field',
            'data_type' => 'text',
            'attribute_type' => 'versioned',
            'review_required' => 'low_confidence',
        ]);

        $this->noReviewAttr = Attribute::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'no_review_field',
            'display_name' => 'No Review Field',
            'data_type' => 'text',
            'attribute_type' => 'versioned',
            'review_required' => 'no',
        ]);

        // Create test entity
        $this->entity = Entity::create([
            'id' => (string) Str::ulid(),
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-001',
        ]);

        // Create test user
        $this->user = User::factory()->create();
    }

    public function test_always_review_attribute_requires_approval(): void
    {
        $writer = app(EavWriter::class);

        // Write value to attribute that always requires review
        $writer->upsertVersioned($this->entity->id, $this->alwaysReviewAttr->id, 'Test Value', [
            'confidence' => 0.95,
        ]);

        // Check that value_current is set but value_approved is null
        $row = DB::table('eav_versioned')
            ->where('entity_id', $this->entity->id)
            ->where('attribute_id', $this->alwaysReviewAttr->id)
            ->first();

        $this->assertEquals('Test Value', $row->value_current);
        $this->assertNull($row->value_approved);
    }

    public function test_low_confidence_attribute_auto_approves_when_confidence_high(): void
    {
        $writer = app(EavWriter::class);

        // Write value with high confidence
        $writer->upsertVersioned($this->entity->id, $this->lowConfidenceAttr->id, 'High Confidence Value', [
            'confidence' => 0.85,
        ]);

        $row = DB::table('eav_versioned')
            ->where('entity_id', $this->entity->id)
            ->where('attribute_id', $this->lowConfidenceAttr->id)
            ->first();

        // Should be auto-approved
        $this->assertEquals('High Confidence Value', $row->value_current);
        $this->assertEquals('High Confidence Value', $row->value_approved);
    }

    public function test_low_confidence_attribute_requires_approval_when_confidence_low(): void
    {
        $writer = app(EavWriter::class);

        // Write value with low confidence
        $writer->upsertVersioned($this->entity->id, $this->lowConfidenceAttr->id, 'Low Confidence Value', [
            'confidence' => 0.65,
        ]);

        $row = DB::table('eav_versioned')
            ->where('entity_id', $this->entity->id)
            ->where('attribute_id', $this->lowConfidenceAttr->id)
            ->first();

        // Should NOT be auto-approved
        $this->assertEquals('Low Confidence Value', $row->value_current);
        $this->assertNull($row->value_approved);
    }

    public function test_no_review_attribute_auto_approves(): void
    {
        $writer = app(EavWriter::class);

        // Write value to no-review attribute
        $writer->upsertVersioned($this->entity->id, $this->noReviewAttr->id, 'Auto Approved Value', [
            'confidence' => 0.5,
        ]);

        $row = DB::table('eav_versioned')
            ->where('entity_id', $this->entity->id)
            ->where('attribute_id', $this->noReviewAttr->id)
            ->first();

        // Should be auto-approved regardless of confidence
        $this->assertEquals('Auto Approved Value', $row->value_current);
        $this->assertEquals('Auto Approved Value', $row->value_approved);
    }

    public function test_approve_versioned_updates_approved_value(): void
    {
        $writer = app(EavWriter::class);

        // Create a pending approval
        $writer->upsertVersioned($this->entity->id, $this->alwaysReviewAttr->id, 'Pending Value', [
            'confidence' => 0.9,
        ]);

        // Approve it
        $writer->approveVersioned($this->entity->id, $this->alwaysReviewAttr->id);

        $row = DB::table('eav_versioned')
            ->where('entity_id', $this->entity->id)
            ->where('attribute_id', $this->alwaysReviewAttr->id)
            ->first();

        $this->assertEquals('Pending Value', $row->value_current);
        $this->assertEquals('Pending Value', $row->value_approved);
    }

    public function test_bulk_approve_approves_multiple_attributes(): void
    {
        $writer = app(EavWriter::class);

        // Create entity and multiple pending approvals
        $entity2 = Entity::create([
            'id' => (string) Str::ulid(),
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-002',
        ]);

        $writer->upsertVersioned($this->entity->id, $this->alwaysReviewAttr->id, 'Value 1', [
            'confidence' => 0.9,
        ]);

        $writer->upsertVersioned($entity2->id, $this->alwaysReviewAttr->id, 'Value 2', [
            'confidence' => 0.9,
        ]);

        // Bulk approve
        $writer->bulkApprove([
            ['entity_id' => $this->entity->id, 'attribute_id' => $this->alwaysReviewAttr->id],
            ['entity_id' => $entity2->id, 'attribute_id' => $this->alwaysReviewAttr->id],
        ]);

        $row1 = DB::table('eav_versioned')
            ->where('entity_id', $this->entity->id)
            ->where('attribute_id', $this->alwaysReviewAttr->id)
            ->first();

        $row2 = DB::table('eav_versioned')
            ->where('entity_id', $entity2->id)
            ->where('attribute_id', $this->alwaysReviewAttr->id)
            ->first();

        $this->assertEquals('Value 1', $row1->value_approved);
        $this->assertEquals('Value 2', $row2->value_approved);
    }

    public function test_review_queue_service_finds_pending_approvals(): void
    {
        $writer = app(EavWriter::class);
        $service = app(ReviewQueueService::class);

        // Create pending approvals
        $writer->upsertVersioned($this->entity->id, $this->alwaysReviewAttr->id, 'Pending 1', [
            'confidence' => 0.9,
            'justification' => 'Test justification',
        ]);

        $writer->upsertVersioned($this->entity->id, $this->lowConfidenceAttr->id, 'Pending 2', [
            'confidence' => 0.7,
        ]);

        // Get pending approvals
        $pending = $service->getPendingApprovals();

        $this->assertCount(1, $pending);
        $this->assertEquals($this->entity->id, $pending[0]['entity_id']);
        $this->assertCount(2, $pending[0]['attributes']);
    }

    public function test_review_queue_excludes_auto_approved_attributes(): void
    {
        $writer = app(EavWriter::class);
        $service = app(ReviewQueueService::class);

        // Create auto-approved attribute
        $writer->upsertVersioned($this->entity->id, $this->noReviewAttr->id, 'Auto Approved', [
            'confidence' => 0.5,
        ]);

        // Get pending approvals
        $pending = $service->getPendingApprovals();

        // Should be empty since auto-approved attributes don't need review
        $this->assertCount(0, $pending);
    }

    public function test_review_queue_excludes_high_confidence_low_confidence_attributes(): void
    {
        $writer = app(EavWriter::class);
        $service = app(ReviewQueueService::class);

        // Create high-confidence low_confidence attribute (auto-approved)
        $writer->upsertVersioned($this->entity->id, $this->lowConfidenceAttr->id, 'High Confidence', [
            'confidence' => 0.9,
        ]);

        // Get pending approvals
        $pending = $service->getPendingApprovals();

        // Should be empty since it was auto-approved
        $this->assertCount(0, $pending);
    }

    public function test_review_queue_count_returns_correct_number(): void
    {
        $writer = app(EavWriter::class);
        $service = app(ReviewQueueService::class);

        // Create multiple pending approvals
        $writer->upsertVersioned($this->entity->id, $this->alwaysReviewAttr->id, 'Pending 1', []);
        $writer->upsertVersioned($this->entity->id, $this->lowConfidenceAttr->id, 'Pending 2', [
            'confidence' => 0.6,
        ]);

        $count = $service->countPendingApprovals();

        $this->assertEquals(2, $count);
    }

    public function test_approved_value_stays_when_current_changes(): void
    {
        $writer = app(EavWriter::class);

        // Initial value - auto approved
        $writer->upsertVersioned($this->entity->id, $this->noReviewAttr->id, 'First Value', []);

        $row = DB::table('eav_versioned')
            ->where('entity_id', $this->entity->id)
            ->where('attribute_id', $this->noReviewAttr->id)
            ->first();

        $this->assertEquals('First Value', $row->value_approved);

        // Update the attribute with always-review setting
        DB::table('attributes')->where('id', $this->noReviewAttr->id)->update([
            'review_required' => 'always',
        ]);

        // Update value again
        $writer->upsertVersioned($this->entity->id, $this->noReviewAttr->id, 'Second Value', []);

        $row = DB::table('eav_versioned')
            ->where('entity_id', $this->entity->id)
            ->where('attribute_id', $this->noReviewAttr->id)
            ->first();

        // Current changed, but approved stayed the same
        $this->assertEquals('Second Value', $row->value_current);
        $this->assertEquals('First Value', $row->value_approved);
    }

    public function test_review_queue_includes_override_changes(): void
    {
        $writer = app(EavWriter::class);
        $service = app(ReviewQueueService::class);

        // Create and approve initial value
        $writer->upsertVersioned($this->entity->id, $this->noReviewAttr->id, 'Initial Value', []);

        $row = DB::table('eav_versioned')
            ->where('entity_id', $this->entity->id)
            ->where('attribute_id', $this->noReviewAttr->id)
            ->first();

        $this->assertEquals('Initial Value', $row->value_approved);

        // Set an override (different from approved)
        $writer->setOverride($this->entity->id, $this->noReviewAttr->id, 'Override Value');

        // Change to always review
        DB::table('attributes')->where('id', $this->noReviewAttr->id)->update([
            'review_required' => 'always',
        ]);

        // Should appear in review queue because override != approved
        $pending = $service->getPendingApprovals();

        $this->assertCount(1, $pending);
        $this->assertEquals($this->entity->id, $pending[0]['entity_id']);
        $this->assertCount(1, $pending[0]['attributes']);
        $this->assertEquals('Override Value', $pending[0]['attributes'][0]['value_display']);
        $this->assertTrue($pending[0]['attributes'][0]['has_override']);
    }

    public function test_approve_applies_override_value(): void
    {
        $writer = app(EavWriter::class);

        // Create initial value
        $writer->upsertVersioned($this->entity->id, $this->alwaysReviewAttr->id, 'Current Value', []);

        // Set an override
        $writer->setOverride($this->entity->id, $this->alwaysReviewAttr->id, 'Override Value');

        // Approve should use the override
        $writer->approveVersioned($this->entity->id, $this->alwaysReviewAttr->id);

        $row = DB::table('eav_versioned')
            ->where('entity_id', $this->entity->id)
            ->where('attribute_id', $this->alwaysReviewAttr->id)
            ->first();

        $this->assertEquals('Override Value', $row->value_approved);
        $this->assertEquals('Current Value', $row->value_current);
        $this->assertEquals('Override Value', $row->value_override);
    }

    public function test_review_queue_excludes_when_override_matches_approved(): void
    {
        $writer = app(EavWriter::class);
        $service = app(ReviewQueueService::class);

        // Create value and approve it
        $writer->upsertVersioned($this->entity->id, $this->noReviewAttr->id, 'Current Value', []);
        $writer->approveVersioned($this->entity->id, $this->noReviewAttr->id);

        // Set override to match approved
        $writer->setOverride($this->entity->id, $this->noReviewAttr->id, 'Current Value');

        // Change to always review
        DB::table('attributes')->where('id', $this->noReviewAttr->id)->update([
            'review_required' => 'always',
        ]);

        // Should NOT appear in queue (override == approved)
        $pending = $service->getPendingApprovals();
        $this->assertCount(0, $pending);
    }

    public function test_review_queue_display_value_logic(): void
    {
        $writer = app(EavWriter::class);
        $service = app(ReviewQueueService::class);

        // Set up multiple attributes with different scenarios
        $writer->upsertVersioned($this->entity->id, $this->alwaysReviewAttr->id, 'Current A', []);

        $entity2 = Entity::create([
            'id' => (string) Str::ulid(),
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-003',
        ]);

        // Entity 2: has override
        $writer->upsertVersioned($entity2->id, $this->alwaysReviewAttr->id, 'Current B', []);
        $writer->setOverride($entity2->id, $this->alwaysReviewAttr->id, 'Override B');

        $pending = $service->getPendingApprovals();

        // Should have 2 entities
        $this->assertCount(2, $pending);

        // Find entity1 and entity2 in results
        $entity1Data = collect($pending)->firstWhere('entity_id', $this->entity->id);
        $entity2Data = collect($pending)->firstWhere('entity_id', $entity2->id);

        // Entity 1: display value should be current (no override)
        $this->assertEquals('Current A', $entity1Data['attributes'][0]['value_display']);
        $this->assertFalse($entity1Data['attributes'][0]['has_override']);

        // Entity 2: display value should be override
        $this->assertEquals('Override B', $entity2Data['attributes'][0]['value_display']);
        $this->assertTrue($entity2Data['attributes'][0]['has_override']);
    }

    public function test_bulk_approve_with_mixed_override_and_current(): void
    {
        $writer = app(EavWriter::class);

        // Entity 1: no override
        $writer->upsertVersioned($this->entity->id, $this->alwaysReviewAttr->id, 'Current 1', []);

        // Entity 2: has override
        $entity2 = Entity::create([
            'id' => (string) Str::ulid(),
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-004',
        ]);
        $writer->upsertVersioned($entity2->id, $this->alwaysReviewAttr->id, 'Current 2', []);
        $writer->setOverride($entity2->id, $this->alwaysReviewAttr->id, 'Override 2');

        // Bulk approve both
        $writer->bulkApprove([
            ['entity_id' => $this->entity->id, 'attribute_id' => $this->alwaysReviewAttr->id],
            ['entity_id' => $entity2->id, 'attribute_id' => $this->alwaysReviewAttr->id],
        ]);

        $row1 = DB::table('eav_versioned')
            ->where('entity_id', $this->entity->id)
            ->where('attribute_id', $this->alwaysReviewAttr->id)
            ->first();

        $row2 = DB::table('eav_versioned')
            ->where('entity_id', $entity2->id)
            ->where('attribute_id', $this->alwaysReviewAttr->id)
            ->first();

        // Entity 1: should approve current
        $this->assertEquals('Current 1', $row1->value_approved);

        // Entity 2: should approve override
        $this->assertEquals('Override 2', $row2->value_approved);
    }

    public function test_empty_string_vs_null_override_handling(): void
    {
        $writer = app(EavWriter::class);
        $service = app(ReviewQueueService::class);

        // Create value
        $writer->upsertVersioned($this->entity->id, $this->alwaysReviewAttr->id, 'Current Value', []);

        // Set override to empty string (should be treated as no override)
        DB::table('eav_versioned')
            ->where('entity_id', $this->entity->id)
            ->where('attribute_id', $this->alwaysReviewAttr->id)
            ->update(['value_override' => '']);

        $pending = $service->getPendingApprovals();

        // Should find it and display current (not empty override)
        $this->assertCount(1, $pending);
        $this->assertEquals('Current Value', $pending[0]['attributes'][0]['value_display']);
        $this->assertFalse($pending[0]['attributes'][0]['has_override']);
    }

    public function test_changing_current_with_existing_override_keeps_override(): void
    {
        $writer = app(EavWriter::class);

        // Create initial value
        $writer->upsertVersioned($this->entity->id, $this->alwaysReviewAttr->id, 'Current 1', []);

        // Set override
        $writer->setOverride($this->entity->id, $this->alwaysReviewAttr->id, 'My Override');

        // Update current value
        $writer->upsertVersioned($this->entity->id, $this->alwaysReviewAttr->id, 'Current 2', []);

        $row = DB::table('eav_versioned')
            ->where('entity_id', $this->entity->id)
            ->where('attribute_id', $this->alwaysReviewAttr->id)
            ->first();

        // Override should remain
        $this->assertEquals('My Override', $row->value_override);
        $this->assertEquals('Current 2', $row->value_current);
    }

    public function test_review_queue_respects_confidence_threshold(): void
    {
        $writer = app(EavWriter::class);
        $service = app(ReviewQueueService::class);

        $entity2 = Entity::create([
            'id' => (string) Str::ulid(),
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-005',
        ]);

        // Low confidence (0.79) - should need review
        $writer->upsertVersioned($this->entity->id, $this->lowConfidenceAttr->id, 'Value 1', [
            'confidence' => 0.79,
        ]);

        // Exactly 0.8 - should auto-approve (not in queue)
        $writer->upsertVersioned($entity2->id, $this->lowConfidenceAttr->id, 'Value 2', [
            'confidence' => 0.8,
        ]);

        $pending = $service->getPendingApprovals();

        // Should only have entity 1 (0.79 < 0.8)
        $this->assertCount(1, $pending);
        $this->assertEquals($this->entity->id, $pending[0]['entity_id']);
    }

    public function test_count_matches_actual_pending_items(): void
    {
        $writer = app(EavWriter::class);
        $service = app(ReviewQueueService::class);

        // Create 3 pending items
        $writer->upsertVersioned($this->entity->id, $this->alwaysReviewAttr->id, 'Value 1', []);

        $entity2 = Entity::create([
            'id' => (string) Str::ulid(),
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-006',
        ]);
        $writer->upsertVersioned($entity2->id, $this->alwaysReviewAttr->id, 'Value 2', []);

        // Add one with override
        $entity3 = Entity::create([
            'id' => (string) Str::ulid(),
            'entity_type_id' => $this->entityType->id,
            'entity_id' => 'TEST-007',
        ]);
        $writer->upsertVersioned($entity3->id, $this->alwaysReviewAttr->id, 'Current 3', []);
        $writer->setOverride($entity3->id, $this->alwaysReviewAttr->id, 'Override 3');

        $pending = $service->getPendingApprovals();
        $count = $service->countPendingApprovals();

        // Count should match actual items
        $totalAttributes = array_sum(array_map(fn($e) => count($e['attributes']), $pending));
        $this->assertEquals($totalAttributes, $count);
        $this->assertEquals(3, $count);
    }
}

