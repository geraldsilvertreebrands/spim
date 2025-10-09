<?php

namespace App\Services\Sync;

use App\Services\MagentoApiClient;
use Illuminate\Support\Facades\Log;

abstract class AbstractSync
{
    protected MagentoApiClient $magentoClient;
    protected string $logChannel = 'magento-sync';
    protected array $stats = [
        'created' => 0,
        'updated' => 0,
        'errors' => 0,
        'skipped' => 0,
    ];

    public function __construct(MagentoApiClient $magentoClient)
    {
        $this->magentoClient = $magentoClient;
    }

    /**
     * Execute the sync
     */
    abstract public function sync(): array;

    /**
     * Get sync statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Reset sync statistics
     */
    protected function resetStats(): void
    {
        $this->stats = [
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
            'skipped' => 0,
        ];
    }

    /**
     * Log a message
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        Log::channel($this->logChannel)->$level($message, $context);
    }

    /**
     * Log info message
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * Log error message
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * Log warning message
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * Log debug message
     */
    protected function logDebug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * Start a sync operation
     */
    protected function startSync(string $operation): void
    {
        $this->resetStats();
        $this->logInfo("Starting {$operation}");
    }

    /**
     * Complete a sync operation
     */
    protected function completeSync(string $operation): void
    {
        $this->logInfo("Completed {$operation}", $this->stats);
    }
}


