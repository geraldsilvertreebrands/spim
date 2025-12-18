<?php

namespace App\Filament\Shared\Concerns;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Trait for exporting data from Supply Panel pages.
 * Provides CSV export for tables and chart export helpers.
 */
trait ExportsData
{
    /**
     * Export data to CSV.
     *
     * @param  array<int, array<string, mixed>>  $data  The data rows to export
     * @param  array<string>  $headers  Column headers
     * @param  array<string>  $columns  Column keys to extract from data
     * @param  string  $filenamePrefix  Prefix for the filename
     */
    protected function exportToCsvResponse(
        array $data,
        array $headers,
        array $columns,
        string $filenamePrefix = 'export'
    ): StreamedResponse {
        $filename = $filenamePrefix.'_'.date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($data, $headers, $columns) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Write headers
            fputcsv($handle, $headers);

            // Write data rows
            foreach ($data as $row) {
                $rowData = [];
                foreach ($columns as $column) {
                    $value = $row[$column] ?? '';

                    // Handle nested keys with dot notation
                    if (str_contains($column, '.')) {
                        $value = data_get($row, $column, '');
                    }

                    // Format specific types
                    if (is_bool($value)) {
                        $value = $value ? 'Yes' : 'No';
                    } elseif (is_array($value)) {
                        $value = implode(', ', $value);
                    }

                    $rowData[] = $value;
                }
                fputcsv($handle, $rowData);
            }

            fclose($handle);
        }, $filename);
    }

    /**
     * Get the export filename with date.
     */
    protected function getExportFilename(string $prefix): string
    {
        $brandName = '';
        if (property_exists($this, 'brandId') && $this->brandId) {
            $brand = \App\Models\Brand::find($this->brandId);
            if ($brand) {
                $brandName = '_'.str_replace(' ', '_', strtolower($brand->name));
            }
        }

        return $prefix.$brandName.'_'.date('Y-m-d');
    }
}
