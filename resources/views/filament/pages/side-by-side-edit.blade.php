<x-filament-panels::page>
    @php
        $formBuilder = app(\App\Services\SideBySideFormBuilder::class);
    @endphp

    @push('styles')
    <style>
        /* Remove default max-width constraints for wide layout */
        .fi-body > div {
            max-width: none !important;
        }

        /* Side-by-side table styling */
        .side-by-side-container {
            overflow-x: auto;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }

        .side-by-side-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: max-content;
        }

        .side-by-side-table thead th {
            position: sticky;
            top: 0;
            z-index: 20;
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
            padding: 1rem;
            font-weight: 600;
            text-align: left;
            white-space: nowrap;
        }

        .side-by-side-table thead th:first-child {
            position: sticky;
            left: 0;
            z-index: 30;
            background: #f3f4f6;
            min-width: 200px;
            max-width: 200px;
        }

        .side-by-side-table tbody td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .side-by-side-table tbody td:first-child {
            position: sticky;
            left: 0;
            background: white;
            font-weight: 500;
            z-index: 10;
            border-right: 2px solid #e5e7eb;
        }

        .entity-column {
            min-width: 250px;
            max-width: 350px;
        }

        /* Form field adjustments for side-by-side context */
        .side-by-side-table .fi-fo-field-wrp {
            margin: 0;
        }

        .side-by-side-table .fi-fo-text-input,
        .side-by-side-table .fi-fo-select,
        .side-by-side-table .fi-fo-textarea {
            width: 100%;
        }

        /* Errors display */
        .entity-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 0.375rem;
            padding: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: #991b1b;
        }
    </style>
    @endpush

    @if(empty($entities))
        <x-filament::section>
            <div class="text-center py-12">
                <h3 class="text-sm font-medium">No entities loaded</h3>
                <p class="mt-2 text-sm text-gray-500">Please select entities from the list page.</p>
                <div class="mt-4">
                    <a href="{{ static::getResource()::getUrl('index') }}" class="text-primary-600 hover:text-primary-700">
                        Return to list
                    </a>
                </div>
            </div>
        </x-filament::section>
    @elseif(empty($selectedAttributes))
        <x-filament::section>
            <div class="text-center py-12">
                <h3 class="text-sm font-medium">No attributes selected</h3>
                <p class="mt-2 text-sm text-gray-500">Please configure which attributes to display.</p>
                <div class="mt-4">
                    {{ ($this->configureAttributesAction)(['selected_attributes' => []]) }}
                </div>
            </div>
        </x-filament::section>
    @else
        <div class="side-by-side-container">
                <table class="side-by-side-table">
                    <thead>
                        <tr>
                            <th>Attribute</th>
                            @foreach($entities as $entityId => $entityData)
                                @php
                                    $entity = \App\Models\Entity::find($entityId);
                                @endphp
                                <th class="entity-column">
                                    {{ $entity->entity_id ?? $entityId }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($selectedAttributes as $attributeName)
                            @php
                                $attribute = $this->getAttribute($attributeName);
                                if (!$attribute) continue;
                                $metadata = $formBuilder->getAttributeMetadata($attribute);
                            @endphp
                            <tr>
                                <td>
                                    @include('filament.components.attribute-label-wrapper', [
                                        'displayName' => $metadata['display_name'],
                                        'attributeType' => $metadata['editable_label'],
                                        'dataType' => $metadata['data_type'],
                                        'typeColor' => $metadata['color_class']
                                    ])
                                </td>
                                @foreach($entities as $entityId => $entityData)
                                    <td class="entity-column">
                                        @if($attribute->editable === 'no')
                                            <span class="text-gray-400 text-sm italic">
                                                {{ $formData[$entityId][$attributeName] ?? 'Not set' }}
                                            </span>
                                        @else
                                            @php
                                                $fieldName = "formData.{$entityId}.{$attributeName}";
                                                $value = $formData[$entityId][$attributeName] ?? '';
                                            @endphp

                                            @if($attribute->data_type === 'select')
                                                <select
                                                    wire:model.blur="{{ $fieldName }}"
                                                    class="fi-select-input block w-full border-gray-300 focus:border-primary-500 focus:ring-primary-500 rounded-lg shadow-sm transition duration-75 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:border-primary-500"
                                                >
                                                    <option value="">Select...</option>
                                                    @foreach($attribute->allowedValues() as $key => $label)
                                                        <option value="{{ $key }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif($attribute->data_type === 'multiselect')
                                                <select
                                                    wire:model.blur="{{ $fieldName }}"
                                                    multiple
                                                    class="fi-select-input block w-full border-gray-300 focus:border-primary-500 focus:ring-primary-500 rounded-lg shadow-sm transition duration-75 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:border-primary-500"
                                                >
                                                    @foreach($attribute->allowedValues() as $key => $label)
                                                        <option value="{{ $key }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif($attribute->data_type === 'integer')
                                                <input
                                                    type="number"
                                                    wire:model.blur="{{ $fieldName }}"
                                                    class="fi-input block w-full border-gray-300 focus:border-primary-500 focus:ring-primary-500 rounded-lg shadow-sm transition duration-75 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:border-primary-500"
                                                    placeholder="Enter number"
                                                >
                                            @elseif($attribute->data_type === 'html')
                                                <textarea
                                                    wire:model.blur="{{ $fieldName }}"
                                                    rows="4"
                                                    class="fi-input block w-full border-gray-300 focus:border-primary-500 focus:ring-primary-500 rounded-lg shadow-sm transition duration-75 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:border-primary-500"
                                                    placeholder="Enter HTML"
                                                ></textarea>
                                            @elseif($attribute->data_type === 'json')
                                                <textarea
                                                    wire:model.blur="{{ $fieldName }}"
                                                    rows="3"
                                                    class="fi-input block w-full border-gray-300 focus:border-primary-500 focus:ring-primary-500 rounded-lg shadow-sm transition duration-75 font-mono text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:border-primary-500"
                                                    placeholder="Enter JSON"
                                                ></textarea>
                                            @else
                                                <input
                                                    type="text"
                                                    wire:model.blur="{{ $fieldName }}"
                                                    class="fi-input block w-full border-gray-300 focus:border-primary-500 focus:ring-primary-500 rounded-lg shadow-sm transition duration-75 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:border-primary-500"
                                                    placeholder="Enter value"
                                                >
                                            @endif
                                        @endif

                                        @if(isset($errors[$entityId][$attributeName]))
                                            <div class="entity-error">
                                                {{ $errors[$entityId][$attributeName] }}
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Global errors section --}}
            @if(!empty($errors))
                <x-filament::section class="mt-4">
                    <x-slot name="heading">
                        Errors
                    </x-slot>
                    <div class="space-y-2">
                        @foreach($errors as $entityId => $entityErrors)
                            @if(is_string($entityErrors))
                                <div class="entity-error">
                                    <strong>Entity {{ $entityId }}:</strong> {{ $entityErrors }}
                                </div>
                            @elseif(is_array($entityErrors))
                                <div class="entity-error">
                                    <strong>Entity {{ $entityId }}:</strong>
                                    <ul class="list-disc list-inside ml-2">
                                        @foreach($entityErrors as $attr => $error)
                                            <li>{{ $attr }}: {{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

        <div class="mt-4 text-gray-500">
            <p>Editing {{ count($entities) }} {{ \Illuminate\Support\Str::plural('entity', count($entities)) }} •
               {{ count($selectedAttributes) }} {{ \Illuminate\Support\Str::plural('attribute', count($selectedAttributes)) }} visible</p>
        </div>
    @endif
</x-filament-panels::page>
