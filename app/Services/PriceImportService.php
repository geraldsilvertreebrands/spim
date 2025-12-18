<?php

namespace App\Services;

use App\Models\Entity;
use App\Models\PriceScrape;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Service for importing scraped price data from external sources.
 *
 * Supports CSV files, JSON arrays, and API-style data.
 * Actual web scraping is out of scope - this just imports pre-scraped data.
 */
class PriceImportService
{
    /**
     * The validation rules for a single price scrape record.
     */
    private const VALIDATION_RULES = [
        'product_id' => 'required|string|size:26',
        'competitor_name' => 'required|string|max:255',
        'price' => 'required|numeric|min:0',
        'competitor_url' => 'nullable|string|max:2048',
        'competitor_sku' => 'nullable|string|max:255',
        'currency' => 'nullable|string|size:3',
        'in_stock' => 'nullable',
        'scraped_at' => 'nullable|date',
    ];

    /**
     * CSV column mapping (CSV header => model attribute).
     */
    private const CSV_COLUMN_MAP = [
        'product_id' => 'product_id',
        'competitor_name' => 'competitor_name',
        'competitor' => 'competitor_name',
        'price' => 'price',
        'competitor_url' => 'competitor_url',
        'url' => 'competitor_url',
        'competitor_sku' => 'competitor_sku',
        'sku' => 'competitor_sku',
        'currency' => 'currency',
        'in_stock' => 'in_stock',
        'stock' => 'in_stock',
        'scraped_at' => 'scraped_at',
        'date' => 'scraped_at',
        'timestamp' => 'scraped_at',
    ];

    private ?PriceAlertService $alertService = null;

    /**
     * Set the PriceAlertService for triggering alerts after imports.
     */
    public function setAlertService(PriceAlertService $alertService): self
    {
        $this->alertService = $alertService;

        return $this;
    }

    /**
     * Import price data from a CSV file.
     *
     * @param  string  $filePath  Path to the CSV file
     * @param  bool  $hasHeader  Whether the CSV has a header row
     * @param  string  $delimiter  CSV delimiter character
     * @return ImportResult Import results with success/failure counts
     */
    public function importFromCsv(
        string $filePath,
        bool $hasHeader = true,
        string $delimiter = ','
    ): ImportResult {
        if (! file_exists($filePath)) {
            throw new InvalidArgumentException("CSV file not found: {$filePath}");
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new InvalidArgumentException("Unable to open CSV file: {$filePath}");
        }

        try {
            $records = [];
            $headers = null;
            $rowNumber = 0;

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;

                // Skip empty rows
                if ($row === [null] || empty(array_filter($row))) {
                    continue;
                }

                // Handle header row
                if ($hasHeader && $headers === null) {
                    $headers = array_map('strtolower', array_map('trim', $row));

                    continue;
                }

                // Map row to record
                if ($hasHeader) {
                    /** @var array<string> $headers */
                    $record = $this->mapCsvRowToRecord($row, $headers, $rowNumber);
                } else {
                    // Without header, assume standard column order
                    $record = $this->mapCsvRowToRecordByPosition($row, $rowNumber);
                }

                $records[] = $record;
            }

            return $this->importBatch($records);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Import price data from a JSON array or file.
     *
     * @param  string|array  $data  JSON string, file path, or array of records
     * @return ImportResult Import results
     */
    public function importFromJson(string|array $data): ImportResult
    {
        if (is_string($data)) {
            // Check if it's a file path
            if (file_exists($data)) {
                $content = file_get_contents($data);
                if ($content === false) {
                    throw new InvalidArgumentException("Unable to read JSON file: {$data}");
                }
                $data = $content;
            }

            // Parse JSON string
            $decoded = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('Invalid JSON: '.json_last_error_msg());
            }
            $data = $decoded;
        }

        // Handle single record vs array of records
        if (isset($data['product_id'])) {
            $data = [$data];
        }

        // Normalize records
        $records = [];
        foreach ($data as $index => $item) {
            $records[] = $this->normalizeRecord($item, $index + 1);
        }

        return $this->importBatch($records);
    }

    /**
     * Import a single price scrape record.
     *
     * @param  array  $data  Record data
     * @return PriceScrape|null Created record or null if validation failed
     */
    public function importSingle(array $data): ?PriceScrape
    {
        $normalized = $this->normalizeRecord($data, 1);
        $result = $this->importBatch([$normalized]);

        if ($result->successCount > 0 && ! empty($result->imported)) {
            return $result->imported[0];
        }

        return null;
    }

    /**
     * Import a batch of price scrape records.
     *
     * @param  array  $records  Array of record data
     * @param  bool  $validateProducts  Whether to validate product IDs exist
     * @return ImportResult Import results
     */
    public function importBatch(array $records, bool $validateProducts = true): ImportResult
    {
        $result = new ImportResult;

        if (empty($records)) {
            return $result;
        }

        // Pre-fetch valid product IDs if validation enabled
        $validProductIds = [];
        if ($validateProducts) {
            $productIds = array_unique(array_column($records, 'product_id'));
            $validProductIds = Entity::query()
                ->whereIn('id', $productIds)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        }

        $importedScrapes = [];

        DB::beginTransaction();
        try {
            foreach ($records as $record) {
                $rowNum = $record['_row_number'] ?? null;
                unset($record['_row_number']);

                // Validate the record
                $validator = Validator::make($record, self::VALIDATION_RULES);
                if ($validator->fails()) {
                    $result->addError($rowNum, $validator->errors()->all());

                    continue;
                }

                // Validate product exists
                if ($validateProducts && ! in_array((string) $record['product_id'], $validProductIds, true)) {
                    $result->addError($rowNum, ["Product ID '{$record['product_id']}' does not exist"]);

                    continue;
                }

                // Prepare the record for insertion
                $scrapeData = $this->prepareRecordForInsert($record);

                // Create the price scrape
                try {
                    $scrape = PriceScrape::create($scrapeData);
                    $importedScrapes[] = $scrape;
                    $result->addSuccess($scrape);
                } catch (\Exception $e) {
                    Log::warning('Failed to import price scrape', [
                        'record' => $record,
                        'error' => $e->getMessage(),
                    ]);
                    $result->addError($rowNum, [$e->getMessage()]);
                }
            }

            DB::commit();

            // Trigger alerts for imported scrapes
            if ($this->alertService && ! empty($importedScrapes)) {
                $this->triggerAlertsForImportedScrapes($importedScrapes);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $result;
    }

    /**
     * Import from an API response format.
     *
     * @param  array  $apiResponse  API response with data array
     * @param  string  $dataKey  Key containing the records array
     * @return ImportResult Import results
     */
    public function importFromApiResponse(array $apiResponse, string $dataKey = 'data'): ImportResult
    {
        if (! isset($apiResponse[$dataKey])) {
            throw new InvalidArgumentException("API response missing '{$dataKey}' key");
        }

        $records = $apiResponse[$dataKey];
        if (! is_array($records)) {
            throw new InvalidArgumentException("'{$dataKey}' must be an array");
        }

        return $this->importFromJson($records);
    }

    /**
     * Map a CSV row to a record using headers.
     */
    private function mapCsvRowToRecord(array $row, array $headers, int $rowNumber): array
    {
        $record = ['_row_number' => $rowNumber];

        foreach ($headers as $index => $header) {
            $value = $row[$index] ?? null;
            $mappedKey = self::CSV_COLUMN_MAP[$header] ?? null;

            if ($mappedKey !== null && $value !== null && $value !== '') {
                $record[$mappedKey] = trim($value);
            }
        }

        return $record;
    }

    /**
     * Map a CSV row to a record by position (when no header).
     * Expected order: product_id, competitor_name, price, currency, in_stock, scraped_at, competitor_url, competitor_sku
     */
    private function mapCsvRowToRecordByPosition(array $row, int $rowNumber): array
    {
        return [
            '_row_number' => $rowNumber,
            'product_id' => $row[0] ?? null,
            'competitor_name' => $row[1] ?? null,
            'price' => $row[2] ?? null,
            'currency' => $row[3] ?? null,
            'in_stock' => $row[4] ?? null,
            'scraped_at' => $row[5] ?? null,
            'competitor_url' => $row[6] ?? null,
            'competitor_sku' => $row[7] ?? null,
        ];
    }

    /**
     * Normalize a record to standard format.
     */
    private function normalizeRecord(array $record, int $rowNumber): array
    {
        $normalized = ['_row_number' => $rowNumber];

        // Map known alternative field names
        $fieldMaps = [
            'product_id' => ['product_id', 'productId', 'product', 'entity_id', 'entityId'],
            'competitor_name' => ['competitor_name', 'competitorName', 'competitor', 'store', 'retailer'],
            'price' => ['price', 'amount', 'value'],
            'competitor_url' => ['competitor_url', 'competitorUrl', 'url', 'link'],
            'competitor_sku' => ['competitor_sku', 'competitorSku', 'sku', 'external_sku'],
            'currency' => ['currency', 'curr'],
            'in_stock' => ['in_stock', 'inStock', 'stock', 'available', 'is_available'],
            'scraped_at' => ['scraped_at', 'scrapedAt', 'date', 'timestamp', 'scraped_date'],
        ];

        foreach ($fieldMaps as $targetField => $sourceFields) {
            foreach ($sourceFields as $sourceField) {
                if (isset($record[$sourceField]) && $record[$sourceField] !== '') {
                    $normalized[$targetField] = $record[$sourceField];
                    break;
                }
            }
        }

        return $normalized;
    }

    /**
     * Prepare a record for database insertion.
     */
    private function prepareRecordForInsert(array $record): array
    {
        $data = [
            'product_id' => (string) $record['product_id'],
            'competitor_name' => $record['competitor_name'],
            'price' => (float) $record['price'],
            'currency' => $record['currency'] ?? 'ZAR',
            'in_stock' => $this->parseBoolean($record['in_stock'] ?? true),
            'scraped_at' => $this->parseDateTime($record['scraped_at'] ?? null),
        ];

        // Optional fields
        if (! empty($record['competitor_url'])) {
            $data['competitor_url'] = $record['competitor_url'];
        }

        if (! empty($record['competitor_sku'])) {
            $data['competitor_sku'] = $record['competitor_sku'];
        }

        return $data;
    }

    /**
     * Parse a value to boolean.
     */
    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower(trim($value));

            return in_array($lower, ['1', 'true', 'yes', 'y', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * Parse a value to datetime.
     */
    private function parseDateTime(mixed $value): Carbon
    {
        if ($value === null || $value === '') {
            return now();
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        // Try to parse various date formats
        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return now();
        }
    }

    /**
     * Trigger alerts for newly imported scrapes.
     *
     * @param  array<PriceScrape>  $scrapes
     */
    private function triggerAlertsForImportedScrapes(array $scrapes): void
    {
        foreach ($scrapes as $scrape) {
            try {
                $this->alertService?->checkAndTriggerAlerts($scrape);
            } catch (\Exception $e) {
                Log::warning('Failed to trigger alerts for imported scrape', [
                    'scrape_id' => $scrape->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}

/**
 * Result object for import operations.
 */
class ImportResult
{
    public int $totalProcessed = 0;

    public int $successCount = 0;

    public int $errorCount = 0;

    /** @var array<PriceScrape> */
    public array $imported = [];

    /** @var array<int, array<string>> */
    public array $errors = [];

    /**
     * Add a successful import.
     */
    public function addSuccess(PriceScrape $scrape): void
    {
        $this->totalProcessed++;
        $this->successCount++;
        $this->imported[] = $scrape;
    }

    /**
     * Add an error for a row.
     *
     * @param  int|null  $rowNumber  Row number (null if unknown)
     * @param  array<string>  $messages  Error messages
     */
    public function addError(?int $rowNumber, array $messages): void
    {
        $this->totalProcessed++;
        $this->errorCount++;
        $key = $rowNumber ?? count($this->errors);
        $this->errors[$key] = $messages;
    }

    /**
     * Check if the import was fully successful.
     */
    public function isSuccess(): bool
    {
        return $this->errorCount === 0 && $this->successCount > 0;
    }

    /**
     * Check if the import had any errors.
     */
    public function hasErrors(): bool
    {
        return $this->errorCount > 0;
    }

    /**
     * Get a summary string.
     */
    public function getSummary(): string
    {
        return sprintf(
            'Imported %d of %d records (%d errors)',
            $this->successCount,
            $this->totalProcessed,
            $this->errorCount
        );
    }

    /**
     * Get all error messages as a flat array.
     *
     * @return array<string>
     */
    public function getAllErrorMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $row => $rowMessages) {
            foreach ($rowMessages as $message) {
                $messages[] = "Row {$row}: {$message}";
            }
        }

        return $messages;
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_processed' => $this->totalProcessed,
            'success_count' => $this->successCount,
            'error_count' => $this->errorCount,
            'is_success' => $this->isSuccess(),
            'errors' => $this->errors,
        ];
    }
}
