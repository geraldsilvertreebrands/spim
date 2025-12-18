<?php

namespace App\Filament\Shared\Concerns;

use App\Services\BigQueryService;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Livewire trait for handling BigQuery data loading.
 *
 * Provides:
 * - Loading state management
 * - Error handling with retry
 * - Timeout handling
 * - User-friendly error messages
 *
 * Usage in Livewire component:
 *
 * use WithBigQueryData;
 *
 * public function loadKpis()
 * {
 *     $this->executeWithLoading('kpisLoading', function() {
 *         $this->kpis = app(BigQueryService::class)->getBrandKpis('MyBrand', '30d');
 *     });
 * }
 */
trait WithBigQueryData
{
    /**
     * Loading states for different sections.
     */
    public bool $isLoading = false;

    public ?Exception $loadError = null;

    public int $retryCount = 0;

    public int $maxRetries = 3;

    /**
     * Execute a BigQuery operation with loading state and error handling.
     *
     * @param  callable  $callback  The BigQuery operation to execute
     * @param  string  $errorContext  Context for error logging
     * @return bool True if successful, false if error occurred
     */
    protected function executeWithLoading(callable $callback, string $errorContext = 'BigQuery operation'): bool
    {
        $this->isLoading = true;
        $this->loadError = null;

        try {
            // Execute the callback
            $callback();

            // Success - reset retry count
            $this->retryCount = 0;

            return true;
        } catch (Exception $e) {
            // Log the error
            Log::error("{$errorContext} failed", [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'retry_count' => $this->retryCount,
            ]);

            // Store error for display
            $this->loadError = $e;

            return false;
        } finally {
            $this->isLoading = false;
        }
    }

    /**
     * Retry a failed operation.
     *
     * @param  callable  $callback  The operation to retry
     * @param  string  $errorContext  Context for error logging
     */
    protected function retryOperation(callable $callback, string $errorContext = 'BigQuery operation'): void
    {
        if ($this->retryCount >= $this->maxRetries) {
            Log::warning("{$errorContext} max retries exceeded", [
                'user_id' => auth()->id(),
                'retry_count' => $this->retryCount,
            ]);

            return;
        }

        $this->retryCount++;
        $this->executeWithLoading($callback, $errorContext);
    }

    /**
     * Check if BigQuery service is configured.
     */
    protected function isBigQueryConfigured(): bool
    {
        try {
            $service = app(BigQueryService::class);

            return $service->isConfigured();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get user-friendly error message.
     */
    protected function getErrorMessage(): ?string
    {
        if ($this->loadError === null) {
            return null;
        }

        $message = $this->loadError->getMessage();

        // Check for common error types and return user-friendly messages
        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return 'The query took too long. Try selecting a shorter time period.';
        }

        if (str_contains($message, 'not configured') || str_contains($message, 'credentials')) {
            return 'Analytics service is not configured. Please contact support.';
        }

        if (str_contains($message, 'quota') || str_contains($message, 'rate limit')) {
            return 'Too many requests. Please wait a moment and try again.';
        }

        if (str_contains($message, 'permission') || str_contains($message, 'access denied')) {
            return 'Access denied. You may not have permission to view this data.';
        }

        return 'Unable to load data. Please try again.';
    }

    /**
     * Check if data is currently loading.
     */
    public function isDataLoading(): bool
    {
        return $this->isLoading;
    }

    /**
     * Check if there was a load error.
     */
    public function hasLoadError(): bool
    {
        return $this->loadError !== null;
    }

    /**
     * Clear the current error.
     */
    public function clearError(): void
    {
        $this->loadError = null;
        $this->retryCount = 0;
    }
}
