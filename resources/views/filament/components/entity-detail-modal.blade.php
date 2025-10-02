@php
    use App\Support\AttributeUiRegistry;
    use App\Services\EavWriter;

    $attributes = App\Models\Attribute::where('entity_type_id', $entityType->id)->get();
    $registry = app(AttributeUiRegistry::class);
@endphp

<div class="space-y-2">
    @foreach($attributes as $attribute)
        @php
            try {
                $ui = $registry->resolve($attribute);
                $displayValue = $ui->show($entity, $attribute, 'override');
                $currentValue = $entity->getAttr($attribute->name, 'current');

                // Check if there's an override by comparing DB values directly
                $versionedRow = null;
                if ($attribute->attribute_type === 'versioned') {
                    $versionedRow = DB::table('eav_versioned')
                        ->where('entity_id', $entity->id)
                        ->where('attribute_id', $attribute->id)
                        ->first();
                }
                $hasOverride = $versionedRow && $versionedRow->value_override !== null;
            } catch (\Exception $e) {
                $displayValue = $entity->getAttr($attribute->name) ?? '';
                $hasOverride = false;
            }
        @endphp

        <div class="flex items-start justify-between border-b border-gray-200 pb-3 hover:bg-gray-50 px-2 py-2 rounded">
            <div class="flex-1 flex items-start space-x-4">
                <div class="w-1/3">
                    <div class="flex items-center space-x-2">
                        <span class="font-medium text-gray-700">{{ $attribute->name }}</span>

                        {{-- Icons for attribute metadata --}}
                        <div class="flex items-center space-x-1">
                            {{-- Attribute type icon --}}
                            @if($attribute->attribute_type === 'versioned')
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800" title="Versioned">
                                    V
                                </span>
                            @elseif($attribute->attribute_type === 'input')
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800" title="Input">
                                    I
                                </span>
                            @elseif($attribute->attribute_type === 'timeseries')
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800" title="Timeseries">
                                    T
                                </span>
                            @endif

                            {{-- Synced icon --}}
                            @if($attribute->is_synced)
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20" title="Synced">
                                    <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                                </svg>
                            @endif

                            {{-- Override icon --}}
                            @if($hasOverride)
                                <svg class="w-4 h-4 text-orange-500" fill="currentColor" viewBox="0 0 20 20" title="Overridden">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </div>
                    </div>
                    <span class="text-xs text-gray-500">{{ $attribute->data_type }}</span>
                </div>

                <div class="w-1/2">
                    <div class="text-gray-900">
                        {!! $displayValue !!}
                    </div>
                    @if($hasOverride && $currentValue !== null)
                        <div class="text-xs text-gray-500 mt-1">
                            <span class="font-medium">Current value:</span> {{ $currentValue }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Action buttons --}}
            <div class="flex items-center space-x-2 ml-4">
                @if($attribute->attribute_type === 'versioned')
                    @if(!$hasOverride)
                        <button
                            wire:click="$dispatch('open-override-modal', { entityId: '{{ $entity->id }}', attributeId: {{ $attribute->id }} })"
                            class="text-sm text-blue-600 hover:text-blue-800"
                            title="Override value"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                    @else
                        <button
                            onclick="if(confirm('Clear override for {{ $attribute->name }}?')) {
                                fetch('{{ route('filament.admin.clear-override') }}', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                    },
                                    body: JSON.stringify({
                                        entity_id: '{{ $entity->id }}',
                                        attribute_id: {{ $attribute->id }}
                                    })
                                }).then(() => window.location.reload());
                            }"
                            class="text-sm text-red-600 hover:text-red-800"
                            title="Clear override"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    @endif
                @endif
            </div>
        </div>
    @endforeach
</div>

