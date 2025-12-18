<?php

namespace Database\Factories;

use App\Models\Entity;
use App\Models\PriceAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PriceAlert>
 */
class PriceAlertFactory extends Factory
{
    protected $model = PriceAlert::class;

    /**
     * Common competitor names for realistic test data.
     */
    private const COMPETITORS = [
        'Takealot',
        'Wellness Warehouse',
        'Checkers',
        'Pick n Pay',
        'Clicks',
        'Dis-Chem',
        'Amazon',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $alertType = $this->faker->randomElement(PriceAlert::ALERT_TYPES);

        return [
            'user_id' => User::factory(),
            'product_id' => null, // Global alert by default
            'competitor_name' => $this->faker->optional(0.7)->randomElement(self::COMPETITORS),
            'alert_type' => $alertType,
            'threshold' => $this->getThresholdForType($alertType),
            'is_active' => true,
            'last_triggered_at' => null,
        ];
    }

    /**
     * Get appropriate threshold based on alert type.
     */
    protected function getThresholdForType(string $alertType): ?float
    {
        return match ($alertType) {
            PriceAlert::TYPE_PRICE_BELOW => $this->faker->randomFloat(2, 50, 500),
            PriceAlert::TYPE_PRICE_CHANGE => $this->faker->randomFloat(2, 5, 25),
            default => null,
        };
    }

    /**
     * Set the alert for a specific user.
     */
    public function forUser(User|int $user): static
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    /**
     * Set the alert for a specific product.
     */
    public function forProduct(Entity|string $product): static
    {
        $productId = $product instanceof Entity ? $product->id : $product;

        return $this->state(fn (array $attributes) => [
            'product_id' => $productId,
        ]);
    }

    /**
     * Set the alert for a specific competitor.
     */
    public function forCompetitor(string $competitorName): static
    {
        return $this->state(fn (array $attributes) => [
            'competitor_name' => $competitorName,
        ]);
    }

    /**
     * Create a global alert (no specific product or competitor).
     */
    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => null,
            'competitor_name' => null,
        ]);
    }

    /**
     * Create a "price below" alert.
     */
    public function priceBelow(float $threshold): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_type' => PriceAlert::TYPE_PRICE_BELOW,
            'threshold' => $threshold,
        ]);
    }

    /**
     * Create a "competitor beats" alert.
     */
    public function competitorBeats(): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_type' => PriceAlert::TYPE_COMPETITOR_BEATS,
            'threshold' => null,
        ]);
    }

    /**
     * Create a "price change" alert with percentage threshold.
     */
    public function priceChange(float $percentThreshold): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_type' => PriceAlert::TYPE_PRICE_CHANGE,
            'threshold' => $percentThreshold,
        ]);
    }

    /**
     * Create an "out of stock" alert.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'alert_type' => PriceAlert::TYPE_OUT_OF_STOCK,
            'threshold' => null,
        ]);
    }

    /**
     * Mark the alert as active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Mark the alert as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a specific threshold.
     */
    public function withThreshold(float $threshold): static
    {
        return $this->state(fn (array $attributes) => [
            'threshold' => $threshold,
        ]);
    }

    /**
     * Mark as triggered at a specific time.
     */
    public function triggeredAt(\DateTimeInterface|string $time): static
    {
        return $this->state(fn (array $attributes) => [
            'last_triggered_at' => $time,
        ]);
    }

    /**
     * Mark as triggered recently (within cooldown period).
     */
    public function triggeredRecently(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_triggered_at' => now()->subMinutes(30),
        ]);
    }

    /**
     * Mark as triggered a long time ago (outside cooldown period).
     */
    public function triggeredLongAgo(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_triggered_at' => now()->subHours(2),
        ]);
    }

    /**
     * Create an alert that has never been triggered.
     */
    public function neverTriggered(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_triggered_at' => null,
        ]);
    }
}
