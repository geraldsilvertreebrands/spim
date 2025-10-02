<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EntityType extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function attributes()
    {
        return $this->hasMany(Attribute::class, 'entity_type_id');
    }

    public function entities()
    {
        return $this->hasMany(Entity::class, 'entity_type_id');
    }
}
