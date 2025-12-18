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
                if (is_array($value) || is_object($value)) {
                    return $value;
                }
                $decoded = json_decode((string) $value, true);

                return $decoded === null && $value !== 'null' ? (string) $value : $decoded;
            case 'multiselect':
                if (is_array($value)) {
                    return array_values($value);
                }
                $decoded = json_decode((string) $value, true);
                if (is_array($decoded)) {
                    return array_values($decoded);
                }

                return [(string) $value];
            case 'select':
            case 'text':
            case 'html':
                return (string) $value;
            default:
                return $value;
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
                return json_encode(array_values((array) $value));
            case 'select':
                return (string) $value;
            case 'text':
            case 'html':
            default:
                return (string) $value;
        }
    }
}
