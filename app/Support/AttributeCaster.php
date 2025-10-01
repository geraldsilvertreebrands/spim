<?php

namespace App\Support;

final class AttributeCaster
{
    public static function castOut(?string $dataType, $value)
    {
        if ($value === null) {
            return null;
        }
        switch ($dataType) {
            case 'integer':
                return is_numeric($value) ? (int) $value : null;
            case 'json':
                if (is_array($value) || is_object($value)) return $value;
                $decoded = json_decode((string) $value, true);
                return $decoded === null && $value !== 'null' ? (string) $value : $decoded;
            case 'multiselect':
                if (is_array($value)) return $value;
                $decoded = json_decode((string) $value, true);
                return is_array($decoded) ? $decoded : [(string) $value];
            case 'text':
            case 'html':
            case 'select':
            default:
                return (string) $value;
        }
    }

    public static function castIn(?string $dataType, $value): ?string
    {
        if ($value === null) {
            return null;
        }
        switch ($dataType) {
            case 'integer':
                return (string) (int) $value;
            case 'json':
                return is_string($value) ? $value : json_encode($value);
            case 'multiselect':
                return is_string($value) ? $value : json_encode(array_values((array) $value));
            case 'text':
            case 'html':
            case 'select':
            default:
                return (string) $value;
        }
    }
}
