<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ReviewQueueService
{
    /**
     * Get all pending approvals grouped by entity
     * Returns array of entities with their pending attributes
     *
     * Logic: Compare the "display value" (override if exists, else current) to approved value.
     * If they differ and review is required, add to queue.
     *
     * Returns full Attribute models so we can use AttributeUi for rendering
     */
    public function getPendingApprovals(): array
    {
        $results = DB::table('eav_versioned as ev')
            ->join('attributes as a', 'ev.attribute_id', '=', 'a.id')
            ->join('entities as e', 'ev.entity_id', '=', 'e.id')
            ->join('entity_types as et', 'e.entity_type_id', '=', 'et.id')
            ->select([
                'ev.entity_id',
                'ev.attribute_id',
                'e.entity_id as entity_natural_id',
                'e.entity_type_id',
                'et.name as entity_type_name',
                'et.id as entity_type_id_for_display',
                'a.name as attribute_name',
                'a.display_name as attribute_display_name',
                'a.data_type',
                'a.review_required',
                'a.ui_class',
                'a.allowed_values',
                'a.linked_entity_type_id',
                'a.attribute_type',
                'ev.value_current',
                'ev.value_override',
                'ev.value_approved',
                'ev.justification',
                'ev.confidence',
                'ev.updated_at',
            ])
            // Compare display value (override if exists, else current) to approved
            ->whereRaw('COALESCE(NULLIF(ev.value_override, ""), ev.value_current) != COALESCE(ev.value_approved, "")')
            ->where(function ($query) {
                $query->where('a.review_required', 'always')
                    ->orWhere(function ($q) {
                        $q->where('a.review_required', 'low_confidence')
                          ->where(function ($q2) {
                              $q2->whereNull('ev.confidence')
                                 ->orWhereRaw('ev.confidence < 0.8');
                          });
                    });
            })
            ->orderBy('e.entity_type_id')
            ->orderBy('ev.entity_id')
            ->orderBy('ev.updated_at', 'desc')
            ->get();

        // Group by entity
        $grouped = [];
        foreach ($results as $row) {
            $entityKey = $row->entity_id;
            if (!isset($grouped[$entityKey])) {
                $grouped[$entityKey] = [
                    'entity_id' => $row->entity_id,
                    'entity_natural_id' => $row->entity_natural_id,
                    'entity_type_name' => $row->entity_type_name,
                    'entity_type_id' => $row->entity_type_id_for_display,
                    'attributes' => [],
                ];
            }
            // Determine display value (override takes precedence)
            $hasOverride = $row->value_override !== null && $row->value_override !== '';
            $displayValue = $hasOverride ? $row->value_override : $row->value_current;

            $grouped[$entityKey]['attributes'][] = [
                'attribute_id' => $row->attribute_id,
                'attribute_name' => $row->attribute_name,
                'attribute_display_name' => $row->attribute_display_name,
                'data_type' => $row->data_type,
                'attribute_type' => $row->attribute_type,
                'ui_class' => $row->ui_class,
                'allowed_values' => $row->allowed_values,
                'linked_entity_type_id' => $row->linked_entity_type_id,
                'review_required' => $row->review_required,
                'value_current' => $row->value_current,
                'value_override' => $row->value_override,
                'value_display' => $displayValue,
                'has_override' => $hasOverride,
                'value_approved' => $row->value_approved,
                'justification' => $row->justification,
                'confidence' => $row->confidence,
                'updated_at' => $row->updated_at,
            ];
        }

        return array_values($grouped);
    }

    /**
     * Count total pending approvals
     * Uses same logic as getPendingApprovals: compare display value to approved
     */
    public function countPendingApprovals(): int
    {
        return DB::table('eav_versioned as ev')
            ->join('attributes as a', 'ev.attribute_id', '=', 'a.id')
            // Compare display value (override if exists, else current) to approved
            ->whereRaw('COALESCE(NULLIF(ev.value_override, ""), ev.value_current) != COALESCE(ev.value_approved, "")')
            ->where(function ($query) {
                $query->where('a.review_required', 'always')
                    ->orWhere(function ($q) {
                        $q->where('a.review_required', 'low_confidence')
                          ->where(function ($q2) {
                              $q2->whereNull('ev.confidence')
                                 ->orWhereRaw('ev.confidence < 0.8');
                          });
                    });
            })
            ->count();
    }

    /**
     * Get a simple diff representation between two values
     */
    public function generateDiff(?string $oldValue, ?string $newValue, string $dataType): string
    {
        $old = $oldValue ?? '';
        $new = $newValue ?? '';

        if ($old === $new) {
            return 'No changes';
        }

        // For JSON, pretty print
        if ($dataType === 'json') {
            $oldFormatted = $this->prettyJson($old);
            $newFormatted = $this->prettyJson($new);
            return $this->generateSimpleTextDiff($oldFormatted, $newFormatted);
        }

        // For HTML and text, simple line-by-line diff
        return $this->generateSimpleTextDiff($old, $new);
    }

    /**
     * Simple text diff showing old -> new
     */
    private function generateSimpleTextDiff(string $old, string $new): string
    {
        // For now, keep it simple - just show old and new
        // In the future, could use a proper diff library
        $oldLines = explode("\n", $old);
        $newLines = explode("\n", $new);

        if (count($oldLines) <= 3 && count($newLines) <= 3) {
            // Short values, just show inline
            return sprintf("Old: %s\nNew: %s", trim($old), trim($new));
        }

        // For longer values, return a more structured representation
        return json_encode([
            'old' => $old,
            'new' => $new,
        ]);
    }

    /**
     * Pretty print JSON
     */
    private function prettyJson(?string $json): string
    {
        if (empty($json)) {
            return '';
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $json;
        }

        return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

