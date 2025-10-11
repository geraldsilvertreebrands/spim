<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class EavWriter
{
    /**
     * Upsert a VERSIONED attribute.
     *
     * Logic based on attribute configuration:
     * - Always sets value_current
     * - If needs_approval='no', also sets value_approved
     * - If needs_approval='only_low_confidence' AND confidence>=0.8, also sets value_approved
     * - If is_sync='no' AND value_approved was set, also sets value_live
     *
     * Options: input_hash, justification, confidence (0..1), meta (array)
     */
    public function upsertVersioned(string $entityId, int $attributeId, ?string $newValue, array $opts = []): void
    {
        $now = now();
        $attr = DB::table('attributes')->find($attributeId);
        if (!$attr) {
            throw new \InvalidArgumentException('Attribute not found: '.$attributeId);
        }

        // Auto-approval decision based on needs_approval setting
        $confidence = array_key_exists('confidence', $opts) ? (float) $opts['confidence'] : null;
        $autoApprove = match ($attr->needs_approval) {
            'yes' => false,
            'only_low_confidence' => ($confidence !== null && $confidence >= 0.8),
            default => true, // 'no' means always auto-approve
        };

        // Also set value_live if auto-approved AND is_sync='no'
        $autoSetLive = $autoApprove && $attr->is_sync === 'no';

        // Ensure row exists to avoid created_at being overwritten on updates
        $existing = DB::table('eav_versioned')->where([
            'entity_id' => $entityId,
            'attribute_id' => $attributeId,
        ])->first();

        if (!$existing) {
            DB::table('eav_versioned')->insert([
                'entity_id'      => $entityId,
                'attribute_id'   => $attributeId,
                'value_current'  => $newValue,
                'value_approved' => $autoApprove ? $newValue : null,
                'value_live'     => $autoSetLive ? $newValue : null,
                'value_override' => null,
                'input_hash'     => $opts['input_hash'] ?? null,
                'justification'  => $opts['justification'] ?? null,
                'confidence'     => $confidence,
                'meta'           => json_encode($opts['meta'] ?? []),
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            return;
        }

        // Short circuit if no change
        if ($existing->value_current === $newValue) {
            return;
        }

        $updates = [
            'value_current' => $newValue,
            'updated_at'    => $now,
            'input_hash'    => $opts['input_hash'] ?? $existing->input_hash,
            'justification' => $opts['justification'] ?? $existing->justification,
            'confidence'    => $confidence ?? $existing->confidence,
            'meta'          => json_encode(array_replace((array) json_decode($existing->meta ?? '[]', true), $opts['meta'] ?? [])),
        ];

        if ($autoApprove) {
            $updates['value_approved'] = $newValue;
        }

        if ($autoSetLive) {
            $updates['value_live'] = $newValue;
        }

        DB::table('eav_versioned')->where('id', $existing->id)->update($updates);
    }

    /**
     * Human override (null to clear). Does not touch created_at.
     */
    public function setOverride(string $entityId, int $attributeId, ?string $value): void
    {
        $existing = DB::table('eav_versioned')->where([
            'entity_id' => $entityId,
            'attribute_id' => $attributeId,
        ])->first();

        if (!$existing) {
            DB::table('eav_versioned')->insert([
                'entity_id' => $entityId,
                'attribute_id' => $attributeId,
                'value_override' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return;
        }

        DB::table('eav_versioned')->where('id', $existing->id)->update([
            'value_override' => $value,
            'updated_at' => now(),
        ]);
    }


    /**
     * Approve a versioned attribute value (set value_approved = override or current)
     * If an override exists, approve the override value. Otherwise approve the current value.
     * Also sets value_live if is_sync='no'
     */
    public function approveVersioned(string $entityId, int $attributeId): void
    {
        $existing = DB::table('eav_versioned')->where([
            'entity_id' => $entityId,
            'attribute_id' => $attributeId,
        ])->first();

        if (!$existing) {
            throw new \InvalidArgumentException("Versioned attribute not found for entity {$entityId} and attribute {$attributeId}");
        }

        // Get attribute configuration
        $attr = DB::table('attributes')->find($attributeId);
        if (!$attr) {
            throw new \InvalidArgumentException('Attribute not found: '.$attributeId);
        }

        // Use override if it exists, otherwise use current
        $valueToApprove = $existing->value_override ?? $existing->value_current;

        $updates = [
            'value_approved' => $valueToApprove,
            'updated_at' => now(),
        ];

        // Also set value_live if is_sync='no' (not synced to external systems)
        if ($attr->is_sync === 'no') {
            $updates['value_live'] = $valueToApprove;
        }

        DB::table('eav_versioned')->where('id', $existing->id)->update($updates);
    }

    /**
     * Bulk approve versioned attributes
     * @param array $items Array of ['entity_id' => string, 'attribute_id' => int]
     */
    public function bulkApprove(array $items): void
    {
        foreach ($items as $item) {
            $this->approveVersioned($item['entity_id'], $item['attribute_id']);
        }
    }
}
