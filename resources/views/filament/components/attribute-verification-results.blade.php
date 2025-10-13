<div style="padding: 1.5rem;">
    {{-- Summary --}}
    <div style="background-color: #f3f4f6; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
            <h3 style="font-size: 1rem; font-weight: 600; color: #111827; margin: 0;">
                {{ $results['entity_type'] }}
            </h3>
            @if(isset($results['timestamp']))
                <span style="font-size: 0.75rem; color: #6b7280;">
                    {{ \Carbon\Carbon::parse($results['timestamp'])->diffForHumans() }}
                </span>
            @endif
        </div>
        <p style="font-size: 0.875rem; color: #6b7280; margin: 0;">
            {{ $results['summary'] }}
        </p>
    </div>

    {{-- Type Compatibility Checks --}}
    @if(!empty($results['type_checks']))
        <div style="margin-bottom: 1.5rem;">
            <h4 style="font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.75rem;">
                Type Compatibility
            </h4>
            <div style="border: 1px solid #e5e7eb; border-radius: 0.375rem; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background-color: #f9fafb;">
                        <tr>
                            <th style="padding: 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase;">Attribute</th>
                            <th style="padding: 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase;">SPIM Type</th>
                            <th style="padding: 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase;">Magento Type</th>
                            <th style="padding: 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase;">Status</th>
                        </tr>
                    </thead>
                    <tbody style="background-color: white; divide-y divide-gray-200;">
                        @foreach($results['type_checks'] as $check)
                            <tr style="border-top: 1px solid #e5e7eb;">
                                <td style="padding: 0.75rem; font-size: 0.875rem; color: #111827;">
                                    <code style="background-color: #f3f4f6; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-family: monospace;">
                                        {{ $check['attribute'] }}
                                    </code>
                                </td>
                                <td style="padding: 0.75rem; font-size: 0.875rem; color: #6b7280;">
                                    {{ $check['spim_type'] ?? 'N/A' }}
                                </td>
                                <td style="padding: 0.75rem; font-size: 0.875rem; color: #6b7280;">
                                    {{ $check['magento_type'] ?? 'N/A' }}
                                </td>
                                <td style="padding: 0.75rem; font-size: 0.875rem;">
                                    @if($check['status'] === 'compatible')
                                        <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background-color: #d1fae5; color: #065f46;">
                                            ✓ Compatible
                                        </span>
                                    @elseif($check['status'] === 'warning')
                                        <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background-color: #fef3c7; color: #92400e;">
                                            ⚠ Warning
                                        </span>
                                        <div style="margin-top: 0.25rem; font-size: 0.75rem; color: #92400e;">
                                            {{ $check['message'] }}
                                        </div>
                                    @elseif($check['status'] === 'incompatible')
                                        <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background-color: #fee2e2; color: #991b1b;">
                                            ✗ Incompatible
                                        </span>
                                        <div style="margin-top: 0.25rem; font-size: 0.75rem; color: #991b1b;">
                                            {{ $check['message'] }}
                                        </div>
                                    @else
                                        <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background-color: #fee2e2; color: #991b1b;">
                                            ✗ Error
                                        </span>
                                        <div style="margin-top: 0.25rem; font-size: 0.75rem; color: #991b1b;">
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
            <h4 style="font-size: 0.875rem; font-weight: 600; color: #111827; margin-bottom: 0.75rem;">
                Option Synchronization
            </h4>
            <div style="border: 1px solid #e5e7eb; border-radius: 0.375rem; overflow: hidden;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background-color: #f9fafb;">
                        <tr>
                            <th style="padding: 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase;">Attribute</th>
                            <th style="padding: 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase;">SPIM Options</th>
                            <th style="padding: 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase;">Magento Options</th>
                            <th style="padding: 0.75rem; text-align: left; font-size: 0.75rem; font-weight: 500; color: #6b7280; text-transform: uppercase;">Result</th>
                        </tr>
                    </thead>
                    <tbody style="background-color: white;">
                        @foreach($results['option_syncs'] as $sync)
                            <tr style="border-top: 1px solid #e5e7eb;">
                                <td style="padding: 0.75rem; font-size: 0.875rem; color: #111827;">
                                    <code style="background-color: #f3f4f6; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-family: monospace;">
                                        {{ $sync['attribute'] }}
                                    </code>
                                </td>
                                <td style="padding: 0.75rem; font-size: 0.875rem; color: #6b7280;">
                                    {{ $sync['spim_count'] ?? 0 }}
                                </td>
                                <td style="padding: 0.75rem; font-size: 0.875rem; color: #6b7280;">
                                    {{ $sync['magento_count'] ?? 0 }}
                                </td>
                                <td style="padding: 0.75rem; font-size: 0.875rem;">
                                    @if($sync['status'] === 'synced')
                                        <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background-color: #dbeafe; color: #1e40af;">
                                            ✓ Synced
                                        </span>
                                        <div style="margin-top: 0.25rem; font-size: 0.75rem; color: #6b7280;">
                                            {{ $sync['message'] }}
                                        </div>
                                    @elseif($sync['status'] === 'unchanged')
                                        <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background-color: #f3f4f6; color: #4b5563;">
                                            - No Change
                                        </span>
                                    @else
                                        <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; background-color: #fee2e2; color: #991b1b;">
                                            ✗ Error
                                        </span>
                                        <div style="margin-top: 0.25rem; font-size: 0.75rem; color: #991b1b;">
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
        <div style="text-align: center; padding: 2rem; color: #6b7280;">
            <p>No attribute verification data available.</p>
        </div>
    @endif
</div>

