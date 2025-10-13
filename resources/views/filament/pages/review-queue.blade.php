<x-filament-panels::page>
    <div class="space-y-6">
    @if(empty($pendingApprovals))
        <x-filament::section>
            <div class="text-center py-12">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                    <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h3 class="mt-4 text-sm font-medium">No pending approvals</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">All attributes are up to date.</p>
            </div>
        </x-filament::section>
    @else
            @foreach($pendingApprovals as $entity)
                <x-filament::section>
                    <x-slot name="heading">
                        {{ $entity['entity_type_name'] }}: {{ $entity['entity_natural_id'] }}
                    </x-slot>

                    <x-slot name="description">
                        {{ count($entity['attributes']) }} attribute(s) pending approval
                    </x-slot>

                    <x-slot name="headerEnd">
                        <x-filament::button
                            wire:click="toggleAllForEntity('{{ $entity['entity_id'] }}')"
                            size="sm"
                            color="gray"
                            outlined
                        >
                            Toggle All
                        </x-filament::button>
                    </x-slot>

                    {{-- Attributes List --}}
                    <div class="space-y-4">
                        @foreach($entity['attributes'] as $attr)
                            @php
                                $isSelected = $this->isSelected($entity['entity_id'], $attr['attribute_id']);

                                // Format values based on data type
                                $formatValue = function($value, $dataType) {
                                    if (empty($value)) {
                                        return '<span class="italic text-gray-400">No value</span>';
                                    }

                                    switch ($dataType) {
                                        case 'html':
                                            // Render HTML (already sanitized in DB)
                                            return $value;
                                        case 'text':
                                            // Escape and preserve line breaks
                                            return nl2br(e($value));
                                        case 'integer':
                                            // Format as number
                                            return number_format((int)$value);
                                        case 'json':
                                            // Pretty print JSON
                                            $decoded = json_decode($value, true);
                                            return '<pre class="m-0 font-mono text-xs">' . e(json_encode($decoded, JSON_PRETTY_PRINT)) . '</pre>';
                                        case 'select':
                                        case 'multiselect':
                                            return e($value);
                                        default:
                                            return e($value);
                                    }
                                };

                                $renderedApproved = $formatValue($attr['value_approved'], $attr['data_type']);
                                $renderedDisplay = $formatValue($attr['value_display'], $attr['data_type']);
                            @endphp
                            <div class="p-6 {{ $isSelected ? 'bg-blue-50' : '' }}">
                                <div class="flex gap-4 items-start">
                                    {{-- Checkbox --}}
                                    <div class="pt-0.5">
                                        <input
                                            type="checkbox"
                                            wire:click="toggleSelection('{{ $entity['entity_id'] }}', {{ $attr['attribute_id'] }})"
                                            {{ $isSelected ? 'checked' : '' }}
                                            class="w-4 h-4 cursor-pointer"
                                        >
                                    </div>

                                    {{-- Attribute Info --}}
                                    <div class="flex-1">
                                        <div class="flex justify-between items-start mb-3">
                                            <div class="flex gap-2 flex-wrap items-center">
                                                <h4 class="text-sm font-semibold m-0">
                                                    {{ $attr['attribute_display_name'] ?? $attr['attribute_name'] }}
                                                </h4>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                                    {{ $attr['data_type'] }}
                                                </span>
                                                @if($attr['needs_approval'] === 'yes')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                        Always requires approval
                                                    </span>
                                                @elseif($attr['needs_approval'] === 'only_low_confidence')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">
                                                        Low confidence ({{ number_format($attr['confidence'] ?? 0, 2) }})
                                                    </span>
                                                @endif
                                            </div>
                                            <span class="text-xs text-gray-500 whitespace-nowrap">
                                                {{ \Carbon\Carbon::parse($attr['updated_at'])->diffForHumans() }}
                                            </span>
                                        </div>

                                        {{-- Value Changes --}}
                                        <div class="mt-3">
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <div class="text-xs font-semibold uppercase text-gray-500 mb-1">Approved Value</div>
                                                    <div class="bg-red-100 border-2 border-red-300 rounded-lg p-3 max-h-30 overflow-y-auto text-sm">
                                                        {!! $renderedApproved !!}
                                                    </div>
                                                </div>
                                                <div>
                                                    <div class="text-xs font-semibold uppercase text-gray-500 mb-1">
                                                        {{ $attr['has_override'] ? 'Override Value' : 'Current Value' }}
                                                    </div>
                                                    <div class="bg-green-100 border-2 border-green-300 rounded-lg p-3 max-h-30 overflow-y-auto text-sm">
                                                        {!! $renderedDisplay !!}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Justification --}}
                                        @if(!empty($attr['justification']))
                                            <div class="mt-3 p-3 bg-blue-100 rounded-lg">
                                                <p class="text-sm text-blue-800 m-0">
                                                    <strong>Justification:</strong> {{ $attr['justification'] }}
                                                </p>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Action Button --}}
                                    <div class="flex-shrink-0">
                                        <x-filament::button
                                            wire:click="approveSingle('{{ $entity['entity_id'] }}', {{ $attr['attribute_id'] }})"
                                            size="sm"
                                            color="success"
                                        >
                                            Approve
                                        </x-filament::button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endforeach
    @endif
    </div>
</x-filament-panels::page>
