@php
    $typeColor = match ($attributeType) {
        'Editable' => 'text-blue-600',
        'Overridable' => 'text-purple-600',
        'Read-only' => 'text-gray-500',
        default => 'text-gray-600',
    };
@endphp

<div class="flex flex-col justify-center h-full pr-4 text-right">
    <div class="font-bold text-sm text-slate-900">
        {{ $displayName }}
    </div>
    <div class="text-xs mt-0.5 {{ $typeColor }}">
        {{ $attributeType }}
    </div>
    @if($dataType)
        <div class="text-xs text-gray-400 mt-0.5">
            {{ $dataType }}
        </div>
    @endif
</div>
