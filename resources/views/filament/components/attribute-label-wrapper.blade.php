@php
    $typeColor = match ($attributeType) {
        'versioned' => '#2563eb', // blue-600
        'input' => '#16a34a',     // green-600
        'timeseries' => '#9333ea', // purple-600
        default => '#4b5563',      // gray-600
    };
@endphp

<div style="display: flex; flex-direction: column; justify-content: center; height: 100%; padding: 0 1rem 0 0; text-align: right;">
    <div style="font-weight: 700; font-size: 0.875rem; color: #0f172a;">
        {{ $displayName }}
    </div>
    <div style="font-size: 0.75rem; margin-top: 0.125rem; color: {{ $typeColor }};">
        [{{ $attributeType }}]
    </div>
</div>

