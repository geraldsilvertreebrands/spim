<div class="p-6">
    {{-- Summary --}}
    <div class="bg-gray-100 p-4 rounded-lg mb-6">
        <div class="flex justify-between items-start mb-2">
            <h3 class="text-base font-semibold text-gray-900 m-0">
                {{ $results['entity_type'] }}
            </h3>
            @if(isset($results['timestamp']))
                <span class="text-xs text-gray-500">
                    {{ \Carbon\Carbon::parse($results['timestamp'])->diffForHumans() }}
                </span>
            @endif
        </div>
        <p class="text-sm text-gray-500 m-0">
            {{ $results['summary'] }}
        </p>
    </div>

    {{-- Type Compatibility Checks --}}
    @if(!empty($results['type_checks']))
        <div class="mb-6">
            <h4 class="text-sm font-semibold text-gray-900 mb-3">
                Type Compatibility
            </h4>
            <div class="border border-gray-200 rounded-md overflow-hidden">
                <table class="w-full border-collapse">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attribute</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">SPIM Type</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Magento Type</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($results['type_checks'] as $check)
                            <tr class="border-t border-gray-200">
                                <td class="px-3 py-3 text-sm text-gray-900">
                                    <code class="bg-gray-100 px-2 py-1 rounded font-mono text-xs">
                                        {{ $check['attribute'] }}
                                    </code>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-500">
                                    {{ $check['spim_type'] ?? 'N/A' }}
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-500">
                                    {{ $check['magento_type'] ?? 'N/A' }}
                                </td>
                                <td class="px-3 py-3 text-sm">
                                    @if($check['status'] === 'compatible')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            ✓ Compatible
                                        </span>
                                    @elseif($check['status'] === 'warning')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                            ⚠ Warning
                                        </span>
                                        <div class="mt-1 text-xs text-amber-800">
                                            {{ $check['message'] }}
                                        </div>
                                    @elseif($check['status'] === 'incompatible')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            ✗ Incompatible
                                        </span>
                                        <div class="mt-1 text-xs text-red-800">
                                            {{ $check['message'] }}
                                        </div>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            ✗ Error
                                        </span>
                                        <div class="mt-1 text-xs text-red-800">
                                            {{ $check['message'] }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Option Sync Results --}}
    @if(!empty($results['option_syncs']))
        <div>
            <h4 class="text-sm font-semibold text-gray-900 mb-3">
                Option Synchronization
            </h4>
            <div class="border border-gray-200 rounded-md overflow-hidden">
                <table class="w-full border-collapse">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attribute</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">SPIM Options</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Magento Options</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Result</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($results['option_syncs'] as $sync)
                            <tr class="border-t border-gray-200">
                                <td class="px-3 py-3 text-sm text-gray-900">
                                    <code class="bg-gray-100 px-2 py-1 rounded font-mono text-xs">
                                        {{ $sync['attribute'] }}
                                    </code>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-500">
                                    {{ $sync['spim_count'] ?? 0 }}
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-500">
                                    {{ $sync['magento_count'] ?? 0 }}
                                </td>
                                <td class="px-3 py-3 text-sm">
                                    @if($sync['status'] === 'synced')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            ✓ Synced
                                        </span>
                                        <div class="mt-1 text-xs text-gray-500">
                                            {{ $sync['message'] }}
                                        </div>
                                    @elseif($sync['status'] === 'unchanged')
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                            - No Change
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            ✗ Error
                                        </span>
                                        <div class="mt-1 text-xs text-red-800">
                                            {{ $sync['message'] }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- No Results --}}
    @if(empty($results['type_checks']) && empty($results['option_syncs']))
        <div class="text-center py-8 text-gray-500">
            <p>No attribute verification data available.</p>
        </div>
    @endif
</div>
