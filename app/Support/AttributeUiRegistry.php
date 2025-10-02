<?php

namespace App\Support;

use App\Contracts\AttributeUi;
use App\Models\Attribute;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class AttributeUiRegistry
{
    protected array $map = [];

    public function register(string $name, string $class): void
    {
        if (!is_subclass_of($class, AttributeUi::class)) {
            throw new InvalidArgumentException("{$class} must implement AttributeUi");
        }
        $this->map[$name] = $class;
    }

    public function resolve(Attribute $attribute): AttributeUi
    {
        $key = $attribute->ui_class ?? $attribute->data_type ?? 'text';
        $class = $this->map[$key] ?? $this->map['text'] ?? null;
        if (!$class) {
            throw new InvalidArgumentException("No UI class registered for {$key}");
        }
        return app($class);
    }
}
