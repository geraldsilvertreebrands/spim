@php
    $hasContent = !empty($justification) || !empty($confidence);
@endphp

@if($hasContent)
<div class="text-xs text-gray-500 mt-1 space-y-1">
    @if($justification)
        <div>
            <span class="font-medium">Justification:</span> {{ $justification }}
        </div>
    @endif

    @if($confidence !== null)
        <div>
            <span class="font-medium">Confidence:</span> {{ number_format($confidence * 100, 1) }}%
        </div>
    @endif

    <div class="mt-2">
        <button
            type="button"
            class="text-blue-600 hover:text-blue-800 underline text-xs bg-transparent border-0 p-0 cursor-pointer"
            wire:click="$dispatch('addAsEval', { pipelineId: '{{ $pipelineId }}', currentValue: @js($currentValue) })"
        >
            Add this value as an eval
        </button>
    </div>
</div>
@endif

