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
                                        return '<span style="font-style: italic; color: #9ca3af;">No value</span>';
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
                                            return '<pre style="margin: 0; font-family: monospace; font-size: 11px;">' . e(json_encode($decoded, JSON_PRETTY_PRINT)) . '</pre>';
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
                            <div style="padding: 1.5rem; {{ $isSelected ? 'background-color: #eff6ff;' : '' }}">
                                <div style="display: flex; gap: 1rem; align-items: start;">
                                    {{-- Checkbox --}}
                                    <div style="padding-top: 2px;">
                                        <input
                                            type="checkbox"
                                            wire:click="toggleSelection('{{ $entity['entity_id'] }}', {{ $attr['attribute_id'] }})"
                                            {{ $isSelected ? 'checked' : '' }}
                                            style="width: 16px; height: 16px; cursor: pointer;"
                                        >
                                    </div>

                                    {{-- Attribute Info --}}
                                    <div style="flex: 1;">
                                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                                            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                                                <h4 style="font-size: 14px; font-weight: 600; margin: 0;">
                                                    {{ $attr['attribute_display_name'] ?? $attr['attribute_name'] }}
                                                </h4>
                                                <span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; background-color: #f3f4f6; color: #374151;">
                                                    {{ $attr['data_type'] }}
                                                </span>
                                                @if($attr['review_required'] === 'always')
                                                    <span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; background-color: #fee2e2; color: #991b1b;">
                                                        Always requires review
                                                    </span>
                                                @elseif($attr['review_required'] === 'low_confidence')
                                                    <span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; background-color: #fef3c7; color: #92400e;">
                                                        Low confidence ({{ number_format($attr['confidence'] ?? 0, 2) }})
                                                    </span>
                                                @endif
                                            </div>
                                            <span style="font-size: 11px; color: #6b7280; white-space: nowrap;">
                                                {{ \Carbon\Carbon::parse($attr['updated_at'])->diffForHumans() }}
                                            </span>
                                        </div>

                                        {{-- Value Changes --}}
                                        <div style="margin-top: 12px;">
                                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                                <div>
                                                    <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: #6b7280; margin-bottom: 4px;">Approved Value</div>
                                                    <div style="background-color: #fee2e2; border: 2px solid #fca5a5; border-radius: 6px; padding: 12px; max-height: 120px; overflow-y: auto; font-size: 13px;">
                                                        {!! $renderedApproved !!}
                                                    </div>
                                                </div>
                                                <div>
                                                    <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: #6b7280; margin-bottom: 4px;">
                                                        {{ $attr['has_override'] ? 'Override Value' : 'Current Value' }}
                                                    </div>
                                                    <div style="background-color: #dcfce7; border: 2px solid #86efac; border-radius: 6px; padding: 12px; max-height: 120px; overflow-y: auto; font-size: 13px;">
                                                        {!! $renderedDisplay !!}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Justification --}}
                                        @if(!empty($attr['justification']))
                                            <div style="margin-top: 12px; padding: 12px; background-color: #dbeafe; border-radius: 6px;">
                                                <p style="font-size: 13px; color: #1e40af; margin: 0;">
                                                    <strong>Justification:</strong> {{ $attr['justification'] }}
                                                </p>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Action Button --}}
                                    <div style="flex-shrink: 0;">
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

