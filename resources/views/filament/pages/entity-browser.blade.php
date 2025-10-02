<x-filament-panels::page>
    <div class="space-y-6">
        @if($entityType)
            <div>
                {{ $this->table }}
            </div>
        @else
            <div class="text-center py-12">
                <p class="text-gray-500">Please select an entity type to browse.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>

