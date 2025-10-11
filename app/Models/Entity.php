<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use App\Support\AttributeCaster;
use App\Services\AttributeService;

class Entity extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected array $attrCache = [];

    protected function loadAttrBags(): array
    {
        $id = $this->getKey();
        if (!$id) {
            return ['override' => [], 'current' => []];
        }
        $row = DB::table('entity_attr_json')->where('entity_id', $id)->first();
        return [
            'override' => $row?->attrs_with_override ? json_decode($row->attrs_with_override, true) : [],
            'current'  => $row?->attrs_current        ? json_decode($row->attrs_current, true) : [],
        ];
    }

    /**
     * Resolve attributes for this entity.
     * $mode = 'override' | 'current'
     */
    public function attrs(string $mode = 'override'): object
    {
        if (!isset($this->attrCache[$mode])) {
            $bags = $this->loadAttrBags();
            $this->attrCache[$mode] = (object) ($bags[$mode] ?? []);
        }
        return $this->attrCache[$mode];
    }

    public function getAttr(string $name, string $mode = 'override', $default = null)
    {
        $bag = (array) $this->attrs($mode);
        $raw = Arr::get($bag, $name, $default);
        if ($raw === $default) return $raw;
        $attr = DB::table('attributes')
            ->where('entity_type_id', $this->entity_type_id)
            ->where('name', $name)
            ->first();
        return AttributeCaster::castOut($attr->data_type ?? null, $raw);
    }

    /** Laravel-like fallback getter: $entity->name */
    public function getAttribute($key)
    {
        // Don't intercept relationship keys or known columns
        $knownKeys = ['id', 'entity_id', 'entity_type_id', 'created_at', 'updated_at'];
        $relationshipMethods = ['entityType', 'attributes'];

        if (in_array($key, $knownKeys) || in_array($key, $relationshipMethods)) {
            return parent::getAttribute($key);
        }

        // Try parent first
        $value = parent::getAttribute($key);
        if ($value !== null) {
            return $value;
        }

        // Check if relationship is loaded
        if ($this->relationLoaded($key)) {
            return parent::getAttribute($key);
        }

        // Only fall back to EAV for unknown keys
        return $this->getAttr($key, 'override');
    }

    /** Laravel-like fallback setter: $entity->name = 'X' */
    public function setAttribute($key, $value)
    {
        if (array_key_exists($key, $this->getAttributes())) {
            return parent::setAttribute($key, $value);
        }

        // Try to map attribute name to an attributes row for this entity_type
        $attr = DB::table('attributes')
            ->where('entity_type_id', $this->entity_type_id)
            ->where('name', $key)
            ->first();

        if ($attr) {
            $attributeModel = app(AttributeService::class)->findByName($this->entity_type_id, $key);

            // Handle relationship attributes separately
            if (in_array($attr->data_type, ['belongs_to','belongs_to_multi'], true)) {
                $ids = $attr->data_type === 'belongs_to' ? [$value] : (array) $value;
                app(AttributeService::class)->validateValue($attributeModel, $ids);
                $this->setRelated($attr->name, $ids);
            } else {
                // Handle regular attributes based on editable setting
                if ($attr->editable === 'no') {
                    throw new \InvalidArgumentException("Attribute '{$key}' is read-only and cannot be edited.");
                } elseif ($attr->editable === 'overridable') {
                    // For overridable attributes, set the override value
                    // Passing null clears the override
                    app(AttributeService::class)->validateValue($attributeModel, $value);
                    $encoded = app(AttributeService::class)->coerceIn($attributeModel, $value);
                    app(\App\Services\EavWriter::class)->setOverride($this->id, (int) $attr->id, $encoded);
                } else { // editable === 'yes'
                    // For editable attributes, update value_current (and possibly value_approved/value_live)
                    app(AttributeService::class)->validateValue($attributeModel, $value);
                    $encoded = app(AttributeService::class)->coerceIn($attributeModel, $value);
                    app(\App\Services\EavWriter::class)->upsertVersioned($this->id, (int) $attr->id, $encoded, []);
                }
            }

            // bust local cache so next read is fresh
            $this->attrCache = [];
            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    /** Scope: filter by EAV attribute via resolved view */
    public function scopeWhereAttr($query, string $name, $operator, $value, string $mode = 'override')
    {
        $col = $mode === 'current' ? 'resolved_current_only' : 'resolved_with_override';
        return $query
            ->join('entity_attribute_resolved as ear', 'ear.entity_id', '=', $this->getTable().'.id')
            ->join('attributes as a', 'a.id', '=', 'ear.attribute_id')
            ->where('a.name', $name)
            ->where("ear.$col", $operator, $value)
            ->select($this->getTable().'.*');
    }

    /** Scope: sort by EAV attribute */
    public function scopeOrderByAttr($query, string $name, string $direction = 'asc', string $mode = 'override')
    {
        $col = $mode === 'current' ? 'resolved_current_only' : 'resolved_with_override';
        return $query
            ->join('entity_attribute_resolved as ear', 'ear.entity_id', '=', $this->getTable().'.id')
            ->join('attributes as a', 'a.id', '=', 'ear.attribute_id')
            ->where('a.name', $name)
            ->orderBy("ear.$col", $direction)
            ->select($this->getTable().'.*');
    }

    /** Helper: get related entity IDs for belongs_to(_multi) attribute */
    public function getRelated(string $attributeName): array
    {
        $attr = DB::table('attributes')
            ->where('entity_type_id', $this->entity_type_id)
            ->where('name', $attributeName)
            ->first();
        if (!$attr) return [];
        return DB::table('entity_attr_links')
            ->where('entity_id', $this->id)
            ->where('attribute_id', $attr->id)
            ->pluck('target_entity_id')
            ->all();
    }

    /** Helper: set related entity IDs (replaces all) */
    public function setRelated(string $attributeName, array $targetEntityIds): void
    {
        $attr = DB::table('attributes')
            ->where('entity_type_id', $this->entity_type_id)
            ->where('name', $attributeName)
            ->first();
        if (!$attr) return;

        DB::transaction(function () use ($attr, $targetEntityIds) {
            DB::table('entity_attr_links')
                ->where('entity_id', $this->id)
                ->where('attribute_id', $attr->id)
                ->delete();
            if (!empty($targetEntityIds)) {
                $now = now();
                $rows = array_map(function ($tid) use ($attr, $now) {
                    return [
                        'entity_id' => $this->id,
                        'attribute_id' => $attr->id,
                        'target_entity_id' => $tid,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $targetEntityIds);
                DB::table('entity_attr_links')->insert($rows);
            }
        });
    }

    public function entityType()
    {
        return $this->belongsTo(EntityType::class, 'entity_type_id');
    }
}
