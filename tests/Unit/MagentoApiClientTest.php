<?php

namespace Tests\Unit;

use App\Services\MagentoApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MagentoApiClientTest extends TestCase
{
    private MagentoApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.magento.base_url' => 'https://magento.test',
            'services.magento.access_token' => 'test-token-123',
        ]);

        $this->client = new MagentoApiClient();
    }

    /** @test */
    public function test_can_get_products_list(): void
    {
        Http::fake([
            'magento.test/rest/V1/products*' => Http::response([
                'items' => [
                    ['id' => 1, 'sku' => 'TEST-001', 'name' => 'Product 1'],
                    ['id' => 2, 'sku' => 'TEST-002', 'name' => 'Product 2'],
                ],
                'total_count' => 2,
            ], 200),
        ]);

        $result = $this->client->getProducts();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertCount(2, $result['items']);
        $this->assertEquals('TEST-001', $result['items'][0]['sku']);
    }

    /** @test */
    public function test_can_get_products_with_filters(): void
    {
        Http::fake([
            'magento.test/rest/V1/products*' => Http::response([
                'items' => [
                    ['id' => 1, 'sku' => 'TEST-001', 'name' => 'Product 1'],
                ],
                'total_count' => 1,
            ], 200),
        ]);

        $result = $this->client->getProducts([
            'searchCriteria' => [
                'filterGroups' => [
                    ['filters' => [['field' => 'sku', 'value' => 'TEST-001']]],
                ],
            ],
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertEquals('TEST-001', $result['items'][0]['sku']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/products') &&
                   str_contains($request->url(), 'searchCriteria');
        });
    }

    /** @test */
    public function test_can_get_single_product(): void
    {
        Http::fake([
            'magento.test/rest/V1/products/TEST-001' => Http::response([
                'id' => 1,
                'sku' => 'TEST-001',
                'name' => 'Product 1',
                'price' => 29.99,
                'custom_attributes' => [
                    ['attribute_code' => 'description', 'value' => 'Test description'],
                ],
            ], 200),
        ]);

        $result = $this->client->getProduct('TEST-001');

        $this->assertIsArray($result);
        $this->assertEquals('TEST-001', $result['sku']);
        $this->assertEquals('Product 1', $result['name']);
        $this->assertArrayHasKey('custom_attributes', $result);
    }

    /** @test */
    public function test_can_create_product(): void
    {
        Http::fake([
            'magento.test/rest/V1/products' => Http::response([
                'id' => 1,
                'sku' => 'NEW-001',
                'name' => 'New Product',
            ], 200),
        ]);

        $payload = [
            'product' => [
                'sku' => 'NEW-001',
                'name' => 'New Product',
                'price' => 19.99,
                'type_id' => 'simple',
                'attribute_set_id' => 4,
            ],
        ];

        $result = $this->client->createProduct($payload);

        $this->assertIsArray($result);
        $this->assertEquals('NEW-001', $result['sku']);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST' &&
                   str_contains($request->url(), '/products') &&
                   $request->hasHeader('Authorization', 'Bearer test-token-123');
        });
    }

    /** @test */
    public function test_can_update_product(): void
    {
        Http::fake([
            'magento.test/rest/V1/products/TEST-001' => Http::response([
                'id' => 1,
                'sku' => 'TEST-001',
                'name' => 'Updated Product',
            ], 200),
        ]);

        $payload = [
            'product' => [
                'name' => 'Updated Product',
            ],
        ];

        $result = $this->client->updateProduct('TEST-001', $payload);

        $this->assertIsArray($result);
        $this->assertEquals('Updated Product', $result['name']);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT' &&
                   str_contains($request->url(), '/products/TEST-001');
        });
    }

    /** @test */
    public function test_can_get_attribute_options(): void
    {
        Http::fake([
            'magento.test/rest/V1/products/attributes/color/options' => Http::response([
                ['label' => 'Red', 'value' => '10'],
                ['label' => 'Blue', 'value' => '11'],
                ['label' => 'Green', 'value' => '12'],
            ], 200),
        ]);

        $result = $this->client->getAttributeOptions('color');

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals('Red', $result[0]['label']);
        $this->assertEquals('10', $result[0]['value']);
    }

    /** @test */
    public function test_can_create_attribute_option(): void
    {
        Http::fake([
            'magento.test/rest/V1/products/attributes/color/options' => Http::response([
                'label' => 'Yellow',
                'value' => '13',
            ], 200),
        ]);

        $result = $this->client->createAttributeOption('color', 'Yellow');

        $this->assertIsArray($result);
        $this->assertEquals('Yellow', $result['label']);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST' &&
                   str_contains($request->url(), '/products/attributes/color/options');
        });
    }

    /** @test */
    public function test_can_upload_image(): void
    {
        Http::fake([
            'example.com/image.jpg' => Http::response('fake-image-content', 200),
            'magento.test/rest/V1/products/TEST-001/media' => Http::response([
                'id' => 1,
                'file' => '/t/e/test-image.jpg',
            ], 200),
        ]);

        $result = $this->client->uploadImage('TEST-001', 'https://example.com/image.jpg', 'test-image.jpg');

        $this->assertIsArray($result);
        $this->assertEquals('/t/e/test-image.jpg', $result['file']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/media') && $request->method() === 'POST';
        });
    }

    /** @test */
    public function test_handles_api_errors_gracefully(): void
    {
        Http::fake([
            'magento.test/rest/V1/products/INVALID' => Http::response([
                'message' => 'Product not found',
            ], 400),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Magento API error');

        $this->client->getProduct('INVALID');
    }

    /** @test */
    public function test_handles_server_errors(): void
    {
        Http::fake([
            'magento.test/rest/V1/products' => Http::response([
                'message' => 'Internal server error',
            ], 500),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Magento API error');

        $this->client->getProducts();
    }

    /** @test */
    public function test_retries_on_failure(): void
    {
        Http::fake([
            'magento.test/rest/V1/products/TEST-001' => Http::sequence()
                ->push(['error' => 'timeout'], 500)
                ->push(['error' => 'timeout'], 500)
                ->push(['id' => 1, 'sku' => 'TEST-001'], 200),
        ]);

        $result = $this->client->getProduct('TEST-001');

        $this->assertEquals('TEST-001', $result['sku']);

        // Should have made 3 requests (2 failures + 1 success)
        Http::assertSentCount(3);
    }

    /** @test */
    public function test_includes_authorization_header(): void
    {
        Http::fake([
            'magento.test/*' => Http::response(['success' => true], 200),
        ]);

        $this->client->getProducts();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer test-token-123');
        });
    }

    /** @test */
    public function test_uses_correct_base_url(): void
    {
        Http::fake([
            'magento.test/*' => Http::response(['success' => true], 200),
        ]);

        $this->client->getProducts();

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://magento.test/rest/V1/');
        });
    }
}

