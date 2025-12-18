<?php

namespace Tests\Unit;

use App\Models\Attribute;
use Tests\TestCase;

class AttributeAllowedValuesTest extends TestCase
{
    public function test_numeric_keys_are_preserved(): void
    {
        $attribute = new Attribute;
        $attribute->allowed_values = ['1' => 'Fresh', '2' => 'Organic', '3' => 'Local'];

        $stored = json_decode($attribute->getAttributes()['allowed_values'], true);

        $this->assertEquals([
            '1' => 'Fresh',
            '2' => 'Organic',
            '3' => 'Local',
        ], $stored);
    }

    public function test_sequential_array_uses_labels_as_keys(): void
    {
        $attribute = new Attribute;
        $attribute->allowed_values = ['Fresh', 'Organic']; // Sequential: [0 => 'Fresh', 1 => 'Organic']

        $stored = json_decode($attribute->getAttributes()['allowed_values'], true);

        $this->assertEquals([
            'Fresh' => 'Fresh',
            'Organic' => 'Organic',
        ], $stored);
    }

    public function test_string_keys_are_preserved(): void
    {
        $attribute = new Attribute;
        $attribute->allowed_values = ['fresh' => 'Freshly Harvested', 'organic' => 'USDA Certified Organic'];

        $stored = json_decode($attribute->getAttributes()['allowed_values'], true);

        $this->assertEquals([
            'fresh' => 'Freshly Harvested',
            'organic' => 'USDA Certified Organic',
        ], $stored);
    }
}
