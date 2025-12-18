<?php

namespace App\Filament\Shared\Components;

use Illuminate\View\Component;

/**
 * BigQuery Error Display Component.
 *
 * Shows user-friendly error messages when BigQuery operations fail,
 * with retry button and technical details (for admins/debugging).
 *
 * Usage:
 * <x-bigquery-error
 *     :error="$error"
 *     retry-action="loadData"
 * />
 */
class BigQueryError extends Component
{
    public string $message;

    public ?string $retryAction;

    public ?string $technicalDetails;

    public bool $showTechnicalDetails;

    /**
     * Create a new component instance.
     */
    public function __construct(
        ?\Exception $error = null,
        ?string $message = null,
        ?string $retryAction = null,
        bool $showTechnicalDetails = false
    ) {
        // User-friendly message
        $this->message = $message ?? $this->getErrorMessage($error);

        // Technical details (for debugging)
        $this->technicalDetails = $error?->getMessage();
        $this->showTechnicalDetails = $showTechnicalDetails || auth()->user()?->hasRole('admin');

        // Retry action (Livewire method name)
        $this->retryAction = $retryAction;
    }

    /**
     * Get user-friendly error message from exception.
     */
    private function getErrorMessage(?\Exception $error): string
    {
        if ($error === null) {
            return 'An error occurred while loading data.';
        }

        $message = strtolower($error->getMessage());

        // Check for common error types (case-insensitive)
        if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
            return 'The query took too long to complete. Please try again with a shorter time period.';
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

        // Generic error
        return 'Unable to load analytics data. Please try again.';
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('filament.shared.components.bigquery-error');
    }
}
