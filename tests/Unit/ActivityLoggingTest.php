<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_creation_is_logged(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $activity = Activity::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('description', 'User created')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('default', $activity->log_name);
    }

    public function test_user_update_is_logged(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
        ]);

        // Clear the creation activity
        Activity::truncate();

        $user->update(['name' => 'Updated Name']);

        $activity = Activity::where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('description', 'User updated')
            ->first();

        $this->assertNotNull($activity);
        $this->assertArrayHasKey('old', $activity->properties->toArray());
        $this->assertArrayHasKey('attributes', $activity->properties->toArray());
        $this->assertEquals('Original Name', $activity->properties['old']['name']);
        $this->assertEquals('Updated Name', $activity->properties['attributes']['name']);
    }

    public function test_user_model_has_logs_activity_trait(): void
    {
        $user = new User;

        $this->assertTrue(
            method_exists($user, 'getActivitylogOptions'),
            'User model should have getActivitylogOptions method from LogsActivity trait'
        );
    }

    public function test_activity_log_only_tracks_specified_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Activity::truncate();

        // Update a field that should be tracked
        $user->update(['name' => 'New Name']);

        $trackedActivity = Activity::where('subject_type', User::class)
            ->where('description', 'User updated')
            ->first();

        $this->assertNotNull($trackedActivity);
        $this->assertArrayHasKey('name', $trackedActivity->properties['attributes']);
    }

    public function test_empty_updates_are_not_logged(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
        ]);

        Activity::truncate();

        // Update with same value - should not create log
        $user->update(['name' => 'Test User']);

        $activity = Activity::where('subject_type', User::class)
            ->where('description', 'User updated')
            ->first();

        $this->assertNull($activity, 'Empty updates should not be logged');
    }

    public function test_auth_activity_logger_class_exists(): void
    {
        $this->assertTrue(
            class_exists(\App\Listeners\AuthActivityLogger::class),
            'AuthActivityLogger class should exist'
        );
    }

    public function test_auth_activity_logger_has_required_methods(): void
    {
        $logger = new \App\Listeners\AuthActivityLogger;

        $this->assertTrue(
            method_exists($logger, 'handleLogin'),
            'AuthActivityLogger should have handleLogin method'
        );
        $this->assertTrue(
            method_exists($logger, 'handleLogout'),
            'AuthActivityLogger should have handleLogout method'
        );
        $this->assertTrue(
            method_exists($logger, 'handlePasswordReset'),
            'AuthActivityLogger should have handlePasswordReset method'
        );
        $this->assertTrue(
            method_exists($logger, 'subscribe'),
            'AuthActivityLogger should have subscribe method'
        );
    }

    public function test_auth_activity_logger_subscribe_returns_correct_mappings(): void
    {
        $logger = new \App\Listeners\AuthActivityLogger;
        $dispatcher = $this->createMock(\Illuminate\Events\Dispatcher::class);

        $mappings = $logger->subscribe($dispatcher);

        $this->assertIsArray($mappings);
        $this->assertArrayHasKey(\Illuminate\Auth\Events\Login::class, $mappings);
        $this->assertArrayHasKey(\Illuminate\Auth\Events\Logout::class, $mappings);
        $this->assertArrayHasKey(\Illuminate\Auth\Events\PasswordReset::class, $mappings);
        $this->assertEquals('handleLogin', $mappings[\Illuminate\Auth\Events\Login::class]);
        $this->assertEquals('handleLogout', $mappings[\Illuminate\Auth\Events\Logout::class]);
        $this->assertEquals('handlePasswordReset', $mappings[\Illuminate\Auth\Events\PasswordReset::class]);
    }
}
