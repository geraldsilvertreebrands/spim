<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttributeSection extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function entityType()
    {
        return $this->belongsTo(EntityType::class, 'entity_type_id');
    }

    public function attributes()
    {
        return $this->hasMany(Attribute::class, 'attribute_section_id')->orderBy('sort_order');
    }
}

