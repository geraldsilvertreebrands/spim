<?php

namespace App\Contracts;

use App\Models\Attribute;
use App\Models\Entity;
use Illuminate\Contracts\Support\Htmlable;

interface AttributeUi
{
    public function summarise(Entity $entity, Attribute $attribute, string $mode = 'override'): string|Htmlable;

    public function show(Entity $entity, Attribute $attribute, string $mode = 'override'): string|Htmlable;

    public function form(Entity $entity, Attribute $attribute): mixed;

    public function save(Entity $entity, Attribute $attribute, $input): void;
}
