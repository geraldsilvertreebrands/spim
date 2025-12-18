<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandCompetitor;
use App\Services\BigQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SupplyChartController extends Controller
{
    public function __construct(
        private readonly BigQueryService $bigQueryService
    ) {}

    /**
     * Get sales trend chart data.
     */
    public function salesTrend(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateBrandRequest($request, [
                'months' => 'sometimes|integer|min:1|max:24',
            ]);

            $brand = Brand::findOrFail($validated['brand_id']);
            if ($error = $this->ensureBrandAccess($brand)) {
                return $error;
            }

            $months = $validated['months'] ?? 12;
            $data = $this->bigQueryService->getSalesTrend($brand->name, $months);

            return $this->successResponse($data, 900); // 15 min cache
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to load sales trend data: '.$e->getMessage());
        }
    }

    /**
     * Get competitor comparison chart data.
     */
    public function competitorComparison(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateBrandRequest($request, [
                'period' => 'sometimes|string|in:30d,90d,1yr,365d',
            ]);

            $brand = Brand::findOrFail($validated['brand_id']);
            if ($error = $this->ensureBrandAccess($brand)) {
                return $error;
            }

            // Get competitor brands from database
            $competitorBrands = BrandCompetitor::where('brand_id', $brand->id)
                ->with('competitor')
                ->get()
                ->pluck('competitor.name')
                ->filter()
                ->toArray();

            $period = $validated['period'] ?? '30d';
            $data = $this->bigQueryService->getCompetitorComparison($brand->name, $competitorBrands, $period);

            return $this->successResponse($data, 1800); // 30 min cache
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to load competitor comparison data: '.$e->getMessage());
        }
    }

    /**
     * Get market share chart data.
     */
    public function marketShare(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateBrandRequest($request, [
                'period' => 'sometimes|string|in:30d,90d,1yr,365d',
            ]);

            $brand = Brand::findOrFail($validated['brand_id']);
            if ($error = $this->ensureBrandAccess($brand)) {
                return $error;
            }

            // Get competitor brands from database
            $competitorBrands = BrandCompetitor::where('brand_id', $brand->id)
                ->with('competitor')
                ->get()
                ->pluck('competitor.name')
                ->filter()
                ->toArray();

            $period = $validated['period'] ?? '30d';
            $data = $this->bigQueryService->getMarketShareByCategory($brand->name, $competitorBrands, $period);

            return $this->successResponse($data, 1800); // 30 min cache
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to load market share data: '.$e->getMessage());
        }
    }

    /**
     * Get products table data.
     */
    public function productsTable(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateBrandRequest($request, [
                'period' => 'sometimes|string|in:3m,6m,12m',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:10|max:100',
            ]);

            $brand = Brand::findOrFail($validated['brand_id']);
            if ($error = $this->ensureBrandAccess($brand)) {
                return $error;
            }

            $period = $validated['period'] ?? '12m';
            $allData = $this->bigQueryService->getProductPerformanceTable($brand->name, $period);

            // Manual pagination
            $page = $validated['page'] ?? 1;
            $perPage = $validated['per_page'] ?? 25;
            $total = count($allData);
            $offset = ($page - 1) * $perPage;
            $data = array_slice($allData, $offset, $perPage);

            return $this->successResponse([
                'data' => $data,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => (int) ceil($total / $perPage),
                ],
            ], 900);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to load products table data: '.$e->getMessage());
        }
    }

    /**
     * Get stock table data.
     */
    public function stockTable(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateBrandRequest($request, [
                'months' => 'sometimes|integer|min:1|max:24',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:10|max:100',
            ]);

            $brand = Brand::findOrFail($validated['brand_id']);
            if ($error = $this->ensureBrandAccess($brand)) {
                return $error;
            }

            $months = $validated['months'] ?? 12;
            $stockData = $this->bigQueryService->getStockSupply($brand->name, $months);

            // Apply pagination to each table if requested
            $page = $validated['page'] ?? 1;
            $perPage = $validated['per_page'] ?? 50;

            $paginateTable = function ($tableData) use ($page, $perPage) {
                $total = count($tableData);
                $offset = ($page - 1) * $perPage;
                $data = array_slice($tableData, $offset, $perPage);

                return [
                    'data' => $data,
                    'total' => $total,
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'last_page' => (int) ceil($total / $perPage),
                ];
            };

            $data = [
                'sell_in' => $paginateTable($stockData['sell_in']),
                'sell_out' => $paginateTable($stockData['sell_out']),
                'closing_stock' => $paginateTable($stockData['closing_stock']),
            ];

            return $this->successResponse($data, 900);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to load stock table data: '.$e->getMessage());
        }
    }

    /**
     * Get purchase orders table data.
     */
    public function purchaseOrdersTable(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateBrandRequest($request, [
                'months' => 'sometimes|integer|min:1|max:24',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:10|max:100',
            ]);

            $brand = Brand::findOrFail($validated['brand_id']);
            if ($error = $this->ensureBrandAccess($brand)) {
                return $error;
            }

            $months = $validated['months'] ?? 12;
            $poData = $this->bigQueryService->getPurchaseOrders($brand->name, $months);

            // Paginate the orders list
            $page = $validated['page'] ?? 1;
            $perPage = $validated['per_page'] ?? 25;
            $orders = $poData['orders'];
            $total = count($orders);
            $offset = ($page - 1) * $perPage;
            $paginatedOrders = array_slice($orders, $offset, $perPage);

            $data = [
                'summary' => $poData['summary'],
                'monthly' => $poData['monthly'],
                'orders' => [
                    'data' => $paginatedOrders,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'last_page' => (int) ceil($total / $perPage),
                    ],
                ],
            ];

            return $this->successResponse($data, 900);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to load purchase orders data: '.$e->getMessage());
        }
    }

    /**
     * Validate request with brand_id and additional rules.
     *
     * @param  array<string, mixed>  $additionalRules
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validateBrandRequest(Request $request, array $additionalRules = []): array
    {
        $rules = array_merge([
            'brand_id' => 'required|integer|exists:brands,id',
        ], $additionalRules);

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Ensure the authenticated user has access to the brand.
     *
     * @return JsonResponse|null Returns JsonResponse with 403 error if access denied, null if access granted
     */
    private function ensureBrandAccess(Brand $brand): ?JsonResponse
    {
        $user = auth()->user();

        if (! $user instanceof \App\Models\User || ! $user->canAccessBrand($brand)) {
            return $this->errorResponse('You do not have access to this brand.', 403);
        }

        return null;
    }

    /**
     * Return a success JSON response with cache metadata.
     *
     * @param  array<mixed>  $data
     */
    private function successResponse(array $data, int $cacheTtl = 900): JsonResponse
    {
        $cachedUntil = now()->addSeconds($cacheTtl);

        return response()->json([
            'success' => true,
            'data' => $data,
            'cached_until' => $cachedUntil->toIso8601String(),
        ]);
    }

    /**
     * Return an error JSON response.
     */
    private function errorResponse(string $message, int $status = 500): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $message,
        ], $status);
    }
}
