<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MagentoApiClient
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.magento.base_url'), '/');
        $this->token = config('services.magento.access_token');

        if (empty($this->baseUrl) || empty($this->token)) {
            throw new RuntimeException('Magento configuration is missing. Set MAGENTO_BASE_URL and MAGENTO_ACCESS_TOKEN in .env');
        }
    }

    /**
     * Get a configured HTTP client for Magento API calls
     */
    private function client(): PendingRequest
    {
        $client = Http::withToken($this->token)
            ->timeout(30)
            ->retry(3, 100, function ($exception, $request) {
                // Retry on connection errors and 5xx responses
                return $exception instanceof \Illuminate\Http\Client\ConnectionException ||
                       ($exception instanceof \Illuminate\Http\Client\RequestException && $exception->response->status() >= 500);
            })
            ->acceptJson()
            ->baseUrl($this->baseUrl);

        // For local development, disable SSL verification
        if (app()->environment('local')) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    /**
     * Fetch all products from Magento with incremental processing
     *
     * Products are fetched page-by-page (100 per page). If a callback is provided,
     * each page is processed immediately, preventing memory exhaustion on large catalogs.
     *
     * @param array $filters Search criteria filters
     * @param callable|null $pageCallback Optional callback: function(array $products, int $page, int $total)
     * @return array Products response with 'items' and 'total_count'
     */
    public function getProducts(array $filters = [], ?callable $pageCallback = null): array
    {
        $allItems = [];
        $currentPage = 1;
        $pageSize = 100;
        $totalCount = 0;

        do {
            try {
                $searchCriteria = $this->buildSearchCriteriaWithPagination($filters, $currentPage, $pageSize);

                $response = $this->client()->get('/rest/V1/products', $searchCriteria);

                $this->ensureSuccessful($response, 'Failed to fetch products from Magento');

                $data = $response->json();

                if ($data === null) {
                    throw new RuntimeException('Failed to decode JSON response from Magento');
                }

                $items = $data['items'] ?? [];
                $totalCount = $data['total_count'] ?? 0;

                if ($pageCallback) {
                    // Process page immediately via callback (memory efficient)
                    $pageCallback($items, $currentPage, $totalCount);
                } else {
                    // Accumulate all items (for single product requests or tests)
                    $allItems = array_merge($allItems, $items);
                }

                Log::info("Fetched page {$currentPage} of products", [
                    'fetched' => $pageCallback ? min($currentPage * $pageSize, $totalCount) : count($allItems),
                    'total' => $totalCount
                ]);

                $currentPage++;

                // Stop when we get an empty page or reached total count
                if (empty($items)) {
                    break;
                }
            } catch (\Illuminate\Http\Client\RequestException $e) {
                throw new RuntimeException("Magento API error: Failed to fetch products - " . $e->getMessage(), 0, $e);
            }
        } while (($pageCallback && (($currentPage - 1) * $pageSize < $totalCount)) ||
                 (!$pageCallback && count($allItems) < $totalCount));

        return [
            'items' => $allItems,  // Empty if using callback
            'total_count' => $totalCount,
        ];
    }

    /**
     * Fetch a single product by SKU
     *
     * @param string $sku Product SKU
     * @return array|null Product data or null if not found
     */
    public function getProduct(string $sku): ?array
    {
        try {
            $response = $this->client()->get("/rest/V1/products/{$sku}");

            if ($response->status() === 404) {
                return null;
            }

            $this->ensureSuccessful($response, "Failed to fetch product {$sku} from Magento");

            return $response->json();
        } catch (\Illuminate\Http\Client\RequestException $e) {
            if ($e->response->status() === 404) {
                return null;
            }
            throw new RuntimeException("Magento API error: Failed to fetch product {$sku} - " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a new product in Magento
     *
     * @param array $payload Product data
     * @return array Created product data
     */
    public function createProduct(array $payload): array
    {
        $response = $this->client()->post('/rest/V1/products', [
            'product' => $payload,
        ]);

        $this->ensureSuccessful($response, 'Failed to create product in Magento');

        return $response->json();
    }

    /**
     * Update an existing product in Magento
     *
     * @param string $sku Product SKU
     * @param array $payload Product data to update
     * @return array Updated product data
     */
    public function updateProduct(string $sku, array $payload): array
    {
        $response = $this->client()->put("/rest/V1/products/{$sku}", [
            'product' => $payload,
        ]);

        $this->ensureSuccessful($response, "Failed to update product {$sku} in Magento");

        return $response->json();
    }

    /**
     * Get attribute metadata including data type
     *
     * @param string $attributeCode Attribute code
     * @return array Attribute metadata
     */
    public function getAttribute(string $attributeCode): array
    {
        $response = $this->client()->get("/rest/V1/products/attributes/{$attributeCode}");

        $this->ensureSuccessful($response, "Failed to fetch attribute metadata for {$attributeCode}");

        return $response->json();
    }

    /**
     * Get attribute options for select/multiselect attributes
     *
     * @param string $attributeCode Attribute code
     * @return array Array of options with 'label' and 'value'
     */
    public function getAttributeOptions(string $attributeCode): array
    {
        $response = $this->client()->get("/rest/V1/products/attributes/{$attributeCode}/options");

        $this->ensureSuccessful($response, "Failed to fetch options for attribute {$attributeCode}");

        $options = $response->json();

        // Filter out the default empty option that Magento returns
        return array_filter($options, fn($opt) => !empty($opt['value']));
    }

    /**
     * Create a new attribute option
     *
     * @param string $attributeCode Attribute code
     * @param string $label Option label
     * @return array Created option data
     */
    public function createAttributeOption(string $attributeCode, string $label): array
    {
        $response = $this->client()->post("/rest/V1/products/attributes/{$attributeCode}/options", [
            'option' => [
                'label' => $label,
                'value' => null, // Magento will auto-generate
                'sort_order' => 0,
                'is_default' => false,
            ],
        ]);

        $this->ensureSuccessful($response, "Failed to create option '{$label}' for attribute {$attributeCode}");

        return $response->json();
    }

    /**
     * Upload an image to Magento from a URL
     *
     * @param string $sku Product SKU
     * @param string $imageUrl URL of the image to download and upload
     * @param string $filename Desired filename
     * @param array $types Image types (base, small, thumbnail, etc.)
     * @return array Uploaded media entry data
     */
    public function uploadImage(string $sku, string $imageUrl, string $filename, array $types = []): array
    {
        // Download the image
        $imageContent = $this->downloadImage($imageUrl);

        // Encode as base64
        $base64Content = base64_encode($imageContent);

        // Determine mime type
        $mimeType = $this->getMimeTypeFromUrl($imageUrl);

        $payload = [
            'entry' => [
                'media_type' => 'image',
                'label' => $filename,
                'position' => 0,
                'disabled' => false,
                'types' => $types,
                'content' => [
                    'base64_encoded_data' => $base64Content,
                    'type' => $mimeType,
                    'name' => $filename,
                ],
            ],
        ];

        $response = $this->client()->post("/rest/V1/products/{$sku}/media", $payload);

        $this->ensureSuccessful($response, "Failed to upload image for product {$sku}");

        return $response->json();
    }

    /**
     * Download an image from a URL
     *
     * @param string $url Image URL
     * @return string Image binary content
     */
    private function downloadImage(string $url): string
    {
        $response = Http::timeout(30)->get($url);

        if (!$response->successful()) {
            throw new RuntimeException("Failed to download image from {$url}");
        }

        return $response->body();
    }

    /**
     * Get MIME type from URL
     *
     * @param string $url Image URL
     * @return string MIME type
     */
    private function getMimeTypeFromUrl(string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return match($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /**
     * Build Magento search criteria from filters with pagination
     *
     * @param array $filters
     * @param int $currentPage
     * @param int $pageSize
     * @return array
     */
    private function buildSearchCriteriaWithPagination(array $filters, int $currentPage, int $pageSize): array
    {
        $criteria = [];

        // Always add pagination
        $criteria['searchCriteria[currentPage]'] = $currentPage;
        $criteria['searchCriteria[pageSize]'] = $pageSize;

        // Add filters if provided
        if (!empty($filters)) {
            $filterIndex = 0;
            foreach ($filters as $field => $value) {
                $criteria["searchCriteria[filterGroups][{$filterIndex}][filters][0][field]"] = $field;
                $criteria["searchCriteria[filterGroups][{$filterIndex}][filters][0][value]"] = $value;
                $criteria["searchCriteria[filterGroups][{$filterIndex}][filters][0][conditionType]"] = 'eq';
                $filterIndex++;
            }
        }

        return $criteria;
    }


    /**
     * Ensure the response was successful, throw exception if not
     *
     * @param Response $response
     * @param string $message Error message
     * @throws RuntimeException
     */
    private function ensureSuccessful(Response $response, string $message): void
    {
        if (!$response->successful()) {
            $error = $response->json('message') ?? $response->body();
            Log::error($message, [
                'status' => $response->status(),
                'error' => $error,
            ]);
            throw new RuntimeException("Magento API error: {$message} - {$error}");
        }
    }
}



