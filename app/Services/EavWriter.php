<?php

namespace App\Services;

use App\Support\AttributeCaster;
use Illuminate\Support\Facades\DB;

class EavWriter
{
    /**
     * Upsert a VERSIONED attribute.
     * review_required: 'always' | 'low_confidence' | 'no'
     * Options: input_hash, justification, confidence (0..1), meta (array)
     */
    public function upsertVersioned(string $entityId, int $attributeId, ?string $newValue, array $opts = []): void
    {
        $now = now();
        $attr = DB::table('attributes')->find($attributeId);
        if (!$attr) {
            throw new \InvalidArgumentException('Attribute not found: '.$attributeId);
        }

        // Auto-approval decision
        $confidence = array_key_exists('confidence', $opts) ? (float) $opts['confidence'] : null;
        $autoApprove = match ($attr->review_required) {
            'always' => false,
            'low_confidence' => ($confidence !== null && $confidence >= 0.8),
            default => true,
        };

        // Ensure row exists to avoid created_at being overwritten on updates
        $existing = DB::table('eav_versioned')->where([
            'entity_id' => $entityId,
            'attribute_id' => $attributeId,
        ])->first();

        $encoded = AttributeCaster::castIn($attr->data_type ?? null, $newValue);

        if (!$existing) {
            DB::table('eav_versioned')->insert([
                'entity_id'      => $entityId,
                'attribute_id'   => $attributeId,
                'value_current'  => $encoded,
                'value_approved' => $autoApprove ? $encoded : null,
                'value_live'     => null,
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
        if ($existing->value_current === $encoded) {
            return;
        }

        $updates = [
            'value_current' => $encoded,
            'updated_at'    => $now,
            'input_hash'    => $opts['input_hash'] ?? $existing->input_hash,
            'justification' => $opts['justification'] ?? $existing->justification,
            'confidence'    => $confidence ?? $existing->confidence,
            'meta'          => json_encode(array_replace((array) json_decode($existing->meta ?? '[]', true), $opts['meta'] ?? [])),
        ];

        if ($autoApprove) {
            $updates['value_approved'] = $encoded; // keep approved in sync when auto-approved
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

    /** INPUT write */
    public function upsertInput(string $entityId, int $attributeId, ?string $value, ?string $source = null): void
    {
        $attr = DB::table('attributes')->find($attributeId);
        $encoded = AttributeCaster::castIn($attr->data_type ?? null, $value);
        $existing = DB::table('eav_input')->where([
            'entity_id' => $entityId,
            'attribute_id' => $attributeId,
        ])->first();

        if ($existing) {
            DB::table('eav_input')->where('id', $existing->id)->update([
                'value' => $encoded,
                'source' => $source,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('eav_input')->insert([
                'entity_id' => $entityId,
                'attribute_id' => $attributeId,
                'value' => $encoded,
                'source' => $source,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
