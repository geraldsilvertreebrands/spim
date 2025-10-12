<?php

namespace Database\Factories;

use App\Models\EntityType;
use App\Models\SyncRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SyncRunFactory extends Factory
{
    protected $model = SyncRun::class;

    public function definition(): array
    {
        $entityTypeId = EntityType::query()->inRandomOrder()->value('id') ?? EntityType::factory()->create()->id;
        $status = $this->faker->randomElement(['running', 'completed', 'failed', 'partial']);
        $startedAt = $this->faker->dateTimeBetween('-7 days', 'now');
        $completedAt = $status === 'running' ? null : $this->faker->dateTimeBetween($startedAt, 'now');

        $total = $this->faker->numberBetween(0, 100);
        $failed = $status === 'failed' ? $this->faker->numberBetween(1, $total) : 0;
        $successful = $total - $failed - $this->faker->numberBetween(0, min(5, $total - $failed));
        $skipped = $total - $successful - $failed;

        return [
            'entity_type_id' => $entityTypeId,
            'sync_type' => $this->faker->randomElement(['options', 'products', 'full']),
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'status' => $status,
            'total_items' => $total,
            'successful_items' => $successful,
            'failed_items' => $failed,
            'skipped_items' => $skipped,
            'error_summary' => $failed > 0 ? "{$failed} items failed during sync." : null,
            'triggered_by' => $this->faker->randomElement(['user', 'schedule', 'api']),
            'user_id' => $this->faker->boolean(50) ? User::factory() : null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => $this->faker->dateTimeBetween($attributes['started_at'] ?? '-1 hour', 'now'),
            'failed_items' => 0,
            'error_summary' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'completed_at' => $this->faker->dateTimeBetween($attributes['started_at'] ?? '-1 hour', 'now'),
            'failed_items' => $this->faker->numberBetween(1, $attributes['total_items'] ?? 10),
            'error_summary' => 'Multiple errors occurred during sync.',
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'triggered_by' => 'user',
            'user_id' => $user->id,
        ]);
    }

    public function forSchedule(): static
    {
        return $this->state(fn (array $attributes) => [
            'triggered_by' => 'schedule',
            'user_id' => null,
        ]);
    }
}

