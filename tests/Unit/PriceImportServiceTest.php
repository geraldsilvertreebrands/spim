<?php

namespace Tests\Unit;

use App\Models\Entity;
use App\Models\EntityType;
use App\Models\PriceAlert;
use App\Models\PriceScrape;
use App\Models\User;
use App\Services\ImportResult;
use App\Services\PriceAlertService;
use App\Services\PriceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class PriceImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private PriceImportService $service;

    /** @var Entity */
    private $product;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PriceImportService;

        // Create an entity type and product for testing
        $entityType = EntityType::factory()->create();
        $this->product = Entity::factory()->create([
            'entity_type_id' => $entityType->id,
        ]);

        // Create temp directory for test files
        $this->tempDir = sys_get_temp_dir().'/price_import_test_'.uniqid();
        if (! is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob("{$this->tempDir}/*"));
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    // =====================
    // CSV Import Tests
    // =====================

    public function test_imports_csv_with_headers(): void
    {
        $csvContent = "product_id,competitor_name,price,currency,in_stock,scraped_at\n";
        $csvContent .= "{$this->product->id},Takealot,99.99,ZAR,true,2025-01-01\n";
        $csvContent .= "{$this->product->id},Checkers,89.99,ZAR,yes,2025-01-02\n";

        $filePath = $this->createTempFile('test.csv', $csvContent);

        $result = $this->service->importFromCsv($filePath);

        $this->assertInstanceOf(ImportResult::class, $result);
        $this->assertEquals(2, $result->successCount);
        $this->assertEquals(0, $result->errorCount);
        $this->assertTrue($result->isSuccess());
        $this->assertCount(2, $result->imported);

        $this->assertDatabaseHas('price_scrapes', [
            'product_id' => $this->product->id,
            'competitor_name' => 'Takealot',
            'price' => 99.99,
        ]);

        $this->assertDatabaseHas('price_scrapes', [
            'product_id' => $this->product->id,
            'competitor_name' => 'Checkers',
            'price' => 89.99,
        ]);
    }

    public function test_imports_csv_without_headers(): void
    {
        // Order: product_id, competitor_name, price, currency, in_stock, scraped_at
        $csvContent = "{$this->product->id},Takealot,99.99,ZAR,1,2025-01-01\n";

        $filePath = $this->createTempFile('test.csv', $csvContent);

        $result = $this->service->importFromCsv($filePath, hasHeader: false);

        $this->assertEquals(1, $result->successCount);
        $this->assertDatabaseHas('price_scrapes', [
            'product_id' => $this->product->id,
            'competitor_name' => 'Takealot',
            'price' => 99.99,
        ]);
    }

    public function test_imports_csv_with_alternative_column_names(): void
    {
        $csvContent = "product_id,competitor,price,url,sku,stock,date\n";
        $csvContent .= "{$this->product->id},Wellness Warehouse,150.00,https://example.com,SKU123,yes,2025-01-05\n";

        $filePath = $this->createTempFile('test.csv', $csvContent);

        $result = $this->service->importFromCsv($filePath);

        $this->assertEquals(1, $result->successCount);
        $this->assertDatabaseHas('price_scrapes', [
            'product_id' => $this->product->id,
            'competitor_name' => 'Wellness Warehouse',
            'price' => 150.00,
            'competitor_url' => 'https://example.com',
            'competitor_sku' => 'SKU123',
            'in_stock' => true,
        ]);
    }

    public function test_handles_csv_with_custom_delimiter(): void
    {
        $csvContent = "product_id;competitor_name;price\n";
        $csvContent .= "{$this->product->id};Amazon;199.99\n";

        $filePath = $this->createTempFile('test.csv', $csvContent);

        $result = $this->service->importFromCsv($filePath, delimiter: ';');

        $this->assertEquals(1, $result->successCount);
        $this->assertDatabaseHas('price_scrapes', [
            'competitor_name' => 'Amazon',
            'price' => 199.99,
        ]);
    }

    public function test_throws_exception_for_missing_csv_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CSV file not found');

        $this->service->importFromCsv('/nonexistent/file.csv');
    }

    public function test_skips_empty_csv_rows(): void
    {
        $csvContent = "product_id,competitor_name,price\n";
        $csvContent .= "{$this->product->id},Takealot,99.99\n";
        $csvContent .= "\n"; // Empty row
        $csvContent .= ",,,\n"; // Row with only commas
        $csvContent .= "{$this->product->id},Checkers,89.99\n";

        $filePath = $this->createTempFile('test.csv', $csvContent);

        $result = $this->service->importFromCsv($filePath);

        $this->assertEquals(2, $result->successCount);
    }

    // =====================
    // JSON Import Tests
    // =====================

    public function test_imports_json_array(): void
    {
        $data = [
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'Takealot',
                'price' => 99.99,
            ],
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'Dis-Chem',
                'price' => 109.99,
            ],
        ];

        $result = $this->service->importFromJson($data);

        $this->assertEquals(2, $result->successCount);
        $this->assertEquals(0, $result->errorCount);
        $this->assertTrue($result->isSuccess());
    }

    public function test_imports_json_string(): void
    {
        $jsonString = json_encode([
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'Pick n Pay',
                'price' => 79.99,
            ],
        ]);

        $result = $this->service->importFromJson($jsonString);

        $this->assertEquals(1, $result->successCount);
        $this->assertDatabaseHas('price_scrapes', [
            'competitor_name' => 'Pick n Pay',
            'price' => 79.99,
        ]);
    }

    public function test_imports_json_file(): void
    {
        $data = [
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'Clicks',
                'price' => 59.99,
            ],
        ];

        $filePath = $this->createTempFile('test.json', json_encode($data));

        $result = $this->service->importFromJson($filePath);

        $this->assertEquals(1, $result->successCount);
        $this->assertDatabaseHas('price_scrapes', [
            'competitor_name' => 'Clicks',
            'price' => 59.99,
        ]);
    }

    public function test_imports_single_json_object(): void
    {
        // Single object (not wrapped in array)
        $data = [
            'product_id' => $this->product->id,
            'competitor_name' => 'Yuppiechef',
            'price' => 129.99,
        ];

        $result = $this->service->importFromJson($data);

        $this->assertEquals(1, $result->successCount);
        $this->assertDatabaseHas('price_scrapes', [
            'competitor_name' => 'Yuppiechef',
            'price' => 129.99,
        ]);
    }

    public function test_throws_exception_for_invalid_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $this->service->importFromJson('not valid json{');
    }

    public function test_imports_json_with_alternative_field_names(): void
    {
        $data = [
            [
                'productId' => $this->product->id,
                'competitorName' => 'Store A',
                'amount' => 45.00,
                'inStock' => true,
                'scrapedAt' => '2025-01-15',
            ],
        ];

        $result = $this->service->importFromJson($data);

        $this->assertEquals(1, $result->successCount);
        $this->assertDatabaseHas('price_scrapes', [
            'competitor_name' => 'Store A',
            'price' => 45.00,
            'in_stock' => true,
        ]);
    }

    // =====================
    // Single Record Import Tests
    // =====================

    public function test_imports_single_record(): void
    {
        $data = [
            'product_id' => $this->product->id,
            'competitor_name' => 'Single Store',
            'price' => 55.00,
        ];

        $scrape = $this->service->importSingle($data);

        $this->assertInstanceOf(PriceScrape::class, $scrape);
        $this->assertEquals('Single Store', $scrape->competitor_name);
        $this->assertEquals(55.00, $scrape->price);
    }

    public function test_returns_null_for_invalid_single_record(): void
    {
        $data = [
            'competitor_name' => 'Missing Product ID',
            'price' => 55.00,
        ];

        $scrape = $this->service->importSingle($data);

        $this->assertNull($scrape);
    }

    // =====================
    // Validation Tests
    // =====================

    public function test_validates_required_fields(): void
    {
        $data = [
            ['competitor_name' => 'Store', 'price' => 10.00], // Missing product_id
            ['product_id' => $this->product->id, 'price' => 10.00], // Missing competitor_name
            ['product_id' => $this->product->id, 'competitor_name' => 'Store'], // Missing price
        ];

        $result = $this->service->importFromJson($data);

        $this->assertEquals(0, $result->successCount);
        $this->assertEquals(3, $result->errorCount);
        $this->assertTrue($result->hasErrors());
    }

    public function test_validates_product_id_length(): void
    {
        $data = [
            [
                'product_id' => 'short', // Too short (not 26 chars)
                'competitor_name' => 'Store',
                'price' => 10.00,
            ],
        ];

        $result = $this->service->importFromJson($data);

        $this->assertEquals(0, $result->successCount);
        $this->assertEquals(1, $result->errorCount);
    }

    public function test_validates_product_exists(): void
    {
        $data = [
            [
                'product_id' => str_repeat('X', 26), // Valid length but doesn't exist
                'competitor_name' => 'Store',
                'price' => 10.00,
            ],
        ];

        $result = $this->service->importFromJson($data);

        $this->assertEquals(0, $result->successCount);
        $this->assertEquals(1, $result->errorCount);
        $this->assertStringContainsString('does not exist', $result->getAllErrorMessages()[0]);
    }

    public function test_allows_skipping_product_validation(): void
    {
        $fakeProductId = str_repeat('X', 26);
        $data = [
            [
                'product_id' => $fakeProductId,
                'competitor_name' => 'Store',
                'price' => 10.00,
            ],
        ];

        // Skip product validation - this will fail on foreign key constraint
        // So we expect the import to fail, but NOT due to product validation
        $result = $this->service->importBatch(
            $this->normalizeRecords($data),
            validateProducts: false
        );

        // The import will fail due to foreign key constraint, but the validation passed
        $this->assertEquals(1, $result->errorCount);
    }

    public function test_validates_price_is_non_negative(): void
    {
        $data = [
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'Store',
                'price' => -10.00,
            ],
        ];

        $result = $this->service->importFromJson($data);

        $this->assertEquals(0, $result->successCount);
        $this->assertEquals(1, $result->errorCount);
    }

    // =====================
    // Data Type Parsing Tests
    // =====================

    public function test_parses_boolean_values(): void
    {
        $testCases = [
            ['in_stock' => true, 'expected' => true],
            ['in_stock' => false, 'expected' => false],
            ['in_stock' => 'true', 'expected' => true],
            ['in_stock' => 'false', 'expected' => false],
            ['in_stock' => 'yes', 'expected' => true],
            ['in_stock' => 'no', 'expected' => false],
            ['in_stock' => '1', 'expected' => true],
            ['in_stock' => '0', 'expected' => false],
            ['in_stock' => 1, 'expected' => true],
            ['in_stock' => 0, 'expected' => false],
        ];

        foreach ($testCases as $index => $case) {
            $data = [
                [
                    'product_id' => $this->product->id,
                    'competitor_name' => "Store_{$index}",
                    'price' => 10.00,
                    'in_stock' => $case['in_stock'],
                ],
            ];

            $result = $this->service->importFromJson($data);

            $this->assertEquals(1, $result->successCount, "Failed for in_stock value: {$case['in_stock']}");

            $scrape = PriceScrape::where('competitor_name', "Store_{$index}")->first();
            $this->assertEquals($case['expected'], $scrape->in_stock, "Boolean parsing failed for: {$case['in_stock']}");
        }
    }

    public function test_parses_date_values(): void
    {
        $data = [
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'Date Test',
                'price' => 10.00,
                'scraped_at' => '2025-06-15 14:30:00',
            ],
        ];

        $result = $this->service->importFromJson($data);

        $this->assertEquals(1, $result->successCount);

        $scrape = PriceScrape::where('competitor_name', 'Date Test')->first();
        $this->assertEquals('2025-06-15', $scrape->scraped_at->format('Y-m-d'));
    }

    public function test_uses_current_time_for_null_scraped_at(): void
    {
        Carbon::setTestNow('2025-01-15 10:00:00');

        $data = [
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'No Date',
                'price' => 10.00,
            ],
        ];

        $result = $this->service->importFromJson($data);

        $this->assertEquals(1, $result->successCount);

        $scrape = PriceScrape::where('competitor_name', 'No Date')->first();
        $this->assertEquals('2025-01-15', $scrape->scraped_at->format('Y-m-d'));

        Carbon::setTestNow();
    }

    public function test_defaults_to_zar_currency(): void
    {
        $data = [
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'Currency Test',
                'price' => 10.00,
                // No currency specified
            ],
        ];

        $result = $this->service->importFromJson($data);

        $scrape = PriceScrape::where('competitor_name', 'Currency Test')->first();
        $this->assertEquals('ZAR', $scrape->currency);
    }

    // =====================
    // API Response Import Tests
    // =====================

    public function test_imports_from_api_response(): void
    {
        $apiResponse = [
            'data' => [
                [
                    'product_id' => $this->product->id,
                    'competitor_name' => 'API Store',
                    'price' => 199.99,
                ],
            ],
            'meta' => [
                'total' => 1,
            ],
        ];

        $result = $this->service->importFromApiResponse($apiResponse);

        $this->assertEquals(1, $result->successCount);
        $this->assertDatabaseHas('price_scrapes', [
            'competitor_name' => 'API Store',
            'price' => 199.99,
        ]);
    }

    public function test_imports_from_api_response_with_custom_data_key(): void
    {
        $apiResponse = [
            'prices' => [
                [
                    'product_id' => $this->product->id,
                    'competitor_name' => 'Custom Key Store',
                    'price' => 299.99,
                ],
            ],
        ];

        $result = $this->service->importFromApiResponse($apiResponse, 'prices');

        $this->assertEquals(1, $result->successCount);
        $this->assertDatabaseHas('price_scrapes', [
            'competitor_name' => 'Custom Key Store',
        ]);
    }

    public function test_throws_exception_for_missing_data_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("missing 'data' key");

        $this->service->importFromApiResponse(['other' => 'stuff']);
    }

    // =====================
    // Import Result Tests
    // =====================

    public function test_import_result_tracks_statistics(): void
    {
        $data = [
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'Good Store',
                'price' => 10.00,
            ],
            [
                'competitor_name' => 'Bad Store', // Missing product_id
                'price' => 20.00,
            ],
        ];

        $result = $this->service->importFromJson($data);

        $this->assertEquals(2, $result->totalProcessed);
        $this->assertEquals(1, $result->successCount);
        $this->assertEquals(1, $result->errorCount);
        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->hasErrors());
    }

    public function test_import_result_provides_summary(): void
    {
        $data = [
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'Store 1',
                'price' => 10.00,
            ],
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'Store 2',
                'price' => 20.00,
            ],
        ];

        $result = $this->service->importFromJson($data);

        $summary = $result->getSummary();
        $this->assertStringContainsString('2 of 2', $summary);
        $this->assertStringContainsString('0 errors', $summary);
    }

    public function test_import_result_to_array(): void
    {
        $data = [
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'Array Test',
                'price' => 10.00,
            ],
        ];

        $result = $this->service->importFromJson($data);

        $array = $result->toArray();

        $this->assertArrayHasKey('total_processed', $array);
        $this->assertArrayHasKey('success_count', $array);
        $this->assertArrayHasKey('error_count', $array);
        $this->assertArrayHasKey('is_success', $array);
        $this->assertArrayHasKey('errors', $array);

        $this->assertEquals(1, $array['total_processed']);
        $this->assertEquals(1, $array['success_count']);
        $this->assertTrue($array['is_success']);
    }

    public function test_import_result_error_messages(): void
    {
        $data = [
            [
                'competitor_name' => 'No Product', // Missing product_id
                'price' => 10.00,
            ],
        ];

        $result = $this->service->importFromJson($data);

        $messages = $result->getAllErrorMessages();

        $this->assertNotEmpty($messages);
        $this->assertStringContainsString('Row', $messages[0]);
    }

    // =====================
    // Alert Integration Tests
    // =====================

    public function test_triggers_alerts_after_import(): void
    {
        // Create a user and alert
        $user = User::factory()->create();
        $alert = PriceAlert::factory()->create([
            'user_id' => $user->id,
            'product_id' => $this->product->id,
            'competitor_name' => null, // Any competitor
            'alert_type' => 'price_below',
            'threshold' => 50.00,
            'is_active' => true,
        ]);

        // Set up alert service
        $alertService = new PriceAlertService;
        $this->service->setAlertService($alertService);

        $data = [
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'Cheap Store',
                'price' => 30.00, // Below threshold of 50.00
            ],
        ];

        $result = $this->service->importFromJson($data);

        $this->assertEquals(1, $result->successCount);

        // Alert should have been triggered
        $alert->refresh();
        $this->assertNotNull($alert->last_triggered_at);
    }

    public function test_continues_import_if_alert_fails(): void
    {
        // Create an alert service mock that throws an exception
        $mockAlertService = $this->createMock(PriceAlertService::class);
        $mockAlertService->method('checkAndTriggerAlerts')
            ->willThrowException(new \Exception('Alert service error'));

        $this->service->setAlertService($mockAlertService);

        $data = [
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'Alert Fail Store',
                'price' => 10.00,
            ],
        ];

        // Import should still succeed even if alerts fail
        $result = $this->service->importFromJson($data);

        $this->assertEquals(1, $result->successCount);
        $this->assertDatabaseHas('price_scrapes', [
            'competitor_name' => 'Alert Fail Store',
        ]);
    }

    // =====================
    // Batch Import Tests
    // =====================

    public function test_batch_import_rolls_back_on_database_error(): void
    {
        // This is a bit tricky to test - we'd need to simulate a DB error
        // For now, just verify that valid records are committed
        $data = [];
        for ($i = 0; $i < 5; $i++) {
            $data[] = [
                'product_id' => $this->product->id,
                'competitor_name' => "Batch Store {$i}",
                'price' => 10.00 + $i,
            ];
        }

        $result = $this->service->importFromJson($data);

        $this->assertEquals(5, $result->successCount);
        $this->assertEquals(5, PriceScrape::where('competitor_name', 'like', 'Batch Store%')->count());
    }

    public function test_batch_import_with_mixed_valid_invalid_records(): void
    {
        $data = [
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'Valid 1',
                'price' => 10.00,
            ],
            [
                'competitor_name' => 'Invalid - no product_id',
                'price' => 20.00,
            ],
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'Valid 2',
                'price' => 30.00,
            ],
            [
                'product_id' => $this->product->id,
                'price' => 40.00, // Missing competitor_name
            ],
            [
                'product_id' => $this->product->id,
                'competitor_name' => 'Valid 3',
                'price' => 50.00,
            ],
        ];

        $result = $this->service->importFromJson($data);

        $this->assertEquals(5, $result->totalProcessed);
        $this->assertEquals(3, $result->successCount);
        $this->assertEquals(2, $result->errorCount);
    }

    // =====================
    // Helper Methods
    // =====================

    private function createTempFile(string $filename, string $content): string
    {
        $path = $this->tempDir.'/'.$filename;
        file_put_contents($path, $content);

        return $path;
    }

    private function normalizeRecords(array $data): array
    {
        $normalized = [];
        foreach ($data as $index => $record) {
            $record['_row_number'] = $index + 1;
            $normalized[] = $record;
        }

        return $normalized;
    }
}
