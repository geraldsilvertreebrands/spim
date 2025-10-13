@php
    $textClass = $isEmpty ? 'text-gray-400 italic' : 'text-gray-900';
@endphp

<div class="text-sm">
    <div class="{{ $textClass }}">{{ $value }}</div>

    <button
        type="button"
        class="text-sm text-blue-600 hover:text-blue-800 underline italic mt-1 cursor-pointer bg-transparent border-0 p-0"
        x-show="!showOverride"
        x-on:click="showOverride = true"
        x-cloak
    >
        Override value...
    </button>
</div>

