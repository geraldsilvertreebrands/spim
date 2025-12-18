<?php

namespace Tests\Unit;

use App\Filament\Shared\Components\BigQueryError;
use App\Filament\Shared\Components\LoadingSkeleton;
use App\Filament\Shared\Concerns\WithBigQueryData;
use App\Models\User;
use App\Services\BigQueryService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tests for BigQuery error handling and loading states.
 *
 * Covers:
 * - BigQueryError component behavior
 * - LoadingSkeleton component rendering
 * - WithBigQueryData trait functionality
 * - Timeout and retry mechanisms
 */
class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function bigquery_error_component_renders_generic_error_message(): void
    {
        $error = new Exception('Something went wrong');
        $component = new BigQueryError($error);

        $this->assertEquals('Unable to load analytics data. Please try again.', $component->message);
        $this->assertEquals('Something went wrong', $component->technicalDetails);
    }

    /** @test */
    public function bigquery_error_component_detects_timeout_errors(): void
    {
        $error = new Exception('Query timed out after 30 seconds');
        $component = new BigQueryError($error);

        $this->assertEquals('The query took too long to complete. Please try again with a shorter time period.', $component->message);
    }

    /** @test */
    public function bigquery_error_component_detects_configuration_errors(): void
    {
        $error = new Exception('BigQuery not configured');
        $component = new BigQueryError($error);

        $this->assertEquals('Analytics service is not configured. Please contact support.', $component->message);
    }

    /** @test */
    public function bigquery_error_component_detects_quota_errors(): void
    {
        $error = new Exception('Rate limit exceeded');
        $component = new BigQueryError($error);

        $this->assertEquals('Too many requests. Please wait a moment and try again.', $component->message);
    }

    /** @test */
    public function bigquery_error_component_detects_permission_errors(): void
    {
        $error = new Exception('Access denied to dataset');
        $component = new BigQueryError($error);

        $this->assertEquals('Access denied. You may not have permission to view this data.', $component->message);
    }

    /** @test */
    public function bigquery_error_component_accepts_custom_message(): void
    {
        $component = new BigQueryError(null, 'Custom error message');

        $this->assertEquals('Custom error message', $component->message);
        $this->assertNull($component->technicalDetails);
    }

    /** @test */
    public function bigquery_error_component_shows_technical_details_to_admins(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $error = new Exception('Database connection failed');
        $component = new BigQueryError($error);

        $this->assertTrue($component->showTechnicalDetails);
    }

    /** @test */
    public function bigquery_error_component_hides_technical_details_from_non_admins(): void
    {
        $user = User::factory()->create();
        $user->assignRole('pim-editor');
        $this->actingAs($user);

        $error = new Exception('Database connection failed');
        $component = new BigQueryError($error);

        $this->assertFalse($component->showTechnicalDetails);
    }

    /** @test */
    public function loading_skeleton_component_has_default_values(): void
    {
        $component = new LoadingSkeleton;

        $this->assertEquals('card', $component->type);
        $this->assertEquals(5, $component->rows);
        $this->assertEquals(4, $component->columns);
        $this->assertEquals(1, $component->count);
        $this->assertEquals('200px', $component->height);
    }

    /** @test */
    public function loading_skeleton_component_accepts_custom_values(): void
    {
        $component = new LoadingSkeleton('table', 10, 6, 3, '300px');

        $this->assertEquals('table', $component->type);
        $this->assertEquals(10, $component->rows);
        $this->assertEquals(6, $component->columns);
        $this->assertEquals(3, $component->count);
        $this->assertEquals('300px', $component->height);
    }

    /** @test */
    public function with_bigquery_data_trait_executes_callback_successfully(): void
    {
        $component = new class extends Component
        {
            use WithBigQueryData;

            public bool $callbackExecuted = false;

            public bool $resultWasTrue = false;

            public function execute(): void
            {
                $result = $this->executeWithLoading(function () {
                    $this->callbackExecuted = true;
                });

                $this->resultWasTrue = $result === true;
            }

            public function render(): string
            {
                return '<div></div>';
            }
        };

        Livewire::test($component::class)
            ->call('execute')
            ->assertSet('callbackExecuted', true)
            ->assertSet('resultWasTrue', true)
            ->assertSet('isLoading', false)
            ->assertSet('loadError', null)
            ->assertSet('retryCount', 0);
    }

    /** @test */
    public function with_bigquery_data_trait_handles_errors(): void
    {
        $component = new class extends Component
        {
            use WithBigQueryData;

            public bool $resultWasFalse = false;

            public function execute(): void
            {
                $result = $this->executeWithLoading(function () {
                    throw new Exception('Test error');
                }, 'Test operation');

                $this->resultWasFalse = $result === false;
            }

            public function render(): string
            {
                return '<div></div>';
            }
        };

        $livewire = Livewire::test($component::class)
            ->call('execute')
            ->assertSet('resultWasFalse', true)
            ->assertSet('isLoading', false);

        // Verify error was stored
        $this->assertInstanceOf(Exception::class, $livewire->get('loadError'));
        $this->assertEquals('Test error', $livewire->get('loadError')->getMessage());
    }

    /** @test */
    public function with_bigquery_data_trait_retry_increments_count(): void
    {
        $component = new class extends Component
        {
            use WithBigQueryData;

            public int $attemptCount = 0;

            public function execute(): void
            {
                $this->retryOperation(function () {
                    $this->attemptCount++;
                    throw new Exception('Still failing');
                }, 'Retry test');
            }

            public function render(): string
            {
                return '<div></div>';
            }
        };

        Livewire::test($component::class)
            ->call('execute')
            ->assertSet('retryCount', 1);
    }

    /** @test */
    public function with_bigquery_data_trait_stops_after_max_retries(): void
    {
        $component = new class extends Component
        {
            use WithBigQueryData;

            public int $attemptCount = 0;

            public function execute(): void
            {
                // Set retry count to max
                $this->retryCount = $this->maxRetries;

                $this->retryOperation(function () {
                    $this->attemptCount++;
                }, 'Max retry test');
            }

            public function getAttemptCount(): int
            {
                return $this->attemptCount;
            }

            public function render(): string
            {
                return '<div></div>';
            }
        };

        $livewire = Livewire::test($component::class)
            ->call('execute');

        // Verify attempt count is still 0 because we didn't execute
        $this->assertEquals(0, $livewire->get('attemptCount'));

        // Verify retry count wasn't incremented
        $this->assertEquals(3, $livewire->get('retryCount'));
    }

    /** @test */
    public function with_bigquery_data_trait_checks_configuration(): void
    {
        $this->mock(BigQueryService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')
                ->andReturn(true);
        });

        $component = new class extends Component
        {
            use WithBigQueryData;

            public bool $configCheckResult = false;

            public function checkConfig(): void
            {
                $this->configCheckResult = $this->isBigQueryConfigured();
            }

            public function render(): string
            {
                return '<div></div>';
            }
        };

        Livewire::test($component::class)
            ->call('checkConfig')
            ->assertSet('configCheckResult', true);
    }

    /** @test */
    public function with_bigquery_data_trait_returns_user_friendly_timeout_message(): void
    {
        $component = new class extends Component
        {
            use WithBigQueryData;

            public ?string $errorMessageResult = null;

            public function test_timeout(): void
            {
                $this->loadError = new Exception('Query timed out');
                $this->errorMessageResult = $this->getErrorMessage();
            }

            public function render(): string
            {
                return '<div></div>';
            }
        };

        Livewire::test($component::class)
            ->call('testTimeout')
            ->assertSet('errorMessageResult', 'The query took too long. Try selecting a shorter time period.');
    }

    /** @test */
    public function with_bigquery_data_trait_returns_user_friendly_quota_message(): void
    {
        $component = new class extends Component
        {
            use WithBigQueryData;

            public ?string $errorMessageResult = null;

            public function test_quota(): void
            {
                $this->loadError = new Exception('Rate limit exceeded');
                $this->errorMessageResult = $this->getErrorMessage();
            }

            public function render(): string
            {
                return '<div></div>';
            }
        };

        Livewire::test($component::class)
            ->call('testQuota')
            ->assertSet('errorMessageResult', 'Too many requests. Please wait a moment and try again.');
    }

    /** @test */
    public function with_bigquery_data_trait_clears_error_and_retry_count(): void
    {
        $component = new class extends Component
        {
            use WithBigQueryData;

            public function test_clear(): void
            {
                $this->loadError = new Exception('Test error');
                $this->retryCount = 2;

                $this->clearError();
            }

            public function render(): string
            {
                return '<div></div>';
            }
        };

        Livewire::test($component::class)
            ->call('testClear')
            ->assertSet('loadError', null)
            ->assertSet('retryCount', 0);
    }

    /** @test */
    public function bigquery_service_timeout_configuration_exists(): void
    {
        $timeout = config('bigquery.timeout');

        $this->assertIsInt($timeout);
        $this->assertGreaterThan(0, $timeout);
    }

    /** @test */
    public function with_bigquery_data_trait_provides_loading_state_check(): void
    {
        $component = new class extends Component
        {
            use WithBigQueryData;

            public bool $loadingStateResult = false;

            public function checkLoading(): void
            {
                $this->isLoading = true;
                $this->loadingStateResult = $this->isDataLoading();
                $this->isLoading = false;
            }

            public function render(): string
            {
                return '<div></div>';
            }
        };

        Livewire::test($component::class)
            ->call('checkLoading')
            ->assertSet('loadingStateResult', true);
    }

    /** @test */
    public function with_bigquery_data_trait_provides_error_state_check(): void
    {
        $component = new class extends Component
        {
            use WithBigQueryData;

            public bool $errorStateResult = false;

            public function checkError(): void
            {
                $this->loadError = new Exception('Test');
                $this->errorStateResult = $this->hasLoadError();
            }

            public function render(): string
            {
                return '<div></div>';
            }
        };

        Livewire::test($component::class)
            ->call('checkError')
            ->assertSet('errorStateResult', true);
    }
}
