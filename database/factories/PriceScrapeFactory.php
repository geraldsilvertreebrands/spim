<?php

namespace Database\Factories;

use App\Models\Entity;
use App\Models\PriceScrape;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PriceScrape>
 */
class PriceScrapeFactory extends Factory
{
    protected $model = PriceScrape::class;

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
        'Faithful to Nature',
        'Yuppiechef',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var Entity $product */
        $product = Entity::query()->inRandomOrder()->first()
            ?? Entity::factory()->create();

        return [
            'product_id' => $product->id,
            'competitor_name' => $this->faker->randomElement(self::COMPETITORS),
            'competitor_url' => $this->faker->optional(0.8)->url(),
            'competitor_sku' => $this->faker->optional(0.6)->bothify('SKU-####??'),
            'price' => $this->faker->randomFloat(2, 10, 1500),
            'currency' => 'ZAR',
            'in_stock' => $this->faker->boolean(85), // 85% chance of being in stock
            'scraped_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Associate the scrape with a specific product (entity).
     */
    public function forProduct(Entity|string $product): static
    {
        $productId = $product instanceof Entity ? $product->id : $product;

        return $this->state(fn (array $attributes) => [
            'product_id' => $productId,
        ]);
    }

    /**
     * Set a specific competitor name.
     */
    public function forCompetitor(string $competitorName): static
    {
        return $this->state(fn (array $attributes) => [
            'competitor_name' => $competitorName,
        ]);
    }

    /**
     * Set a specific price.
     */
    public function withPrice(float $price): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => $price,
        ]);
    }

    /**
     * Set a specific currency.
     */
    public function withCurrency(string $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => $currency,
        ]);
    }

    /**
     * Mark the product as in stock.
     */
    public function inStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'in_stock' => true,
        ]);
    }

    /**
     * Mark the product as out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'in_stock' => false,
        ]);
    }

    /**
     * Set a specific scraped_at timestamp.
     */
    public function scrapedAt(Carbon|string $date): static
    {
        $timestamp = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $this->state(fn (array $attributes) => [
            'scraped_at' => $timestamp,
        ]);
    }

    /**
     * Set scraped_at to today.
     */
    public function scrapedToday(): static
    {
        return $this->state(fn (array $attributes) => [
            'scraped_at' => now(),
        ]);
    }

    /**
     * Set scraped_at to N days ago.
     */
    public function scrapedDaysAgo(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'scraped_at' => now()->subDays($days),
        ]);
    }

    /**
     * Create a price scrape from a specific date in the past.
     */
    public function fromPast(int $daysAgo = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'scraped_at' => $this->faker->dateTimeBetween("-{$daysAgo} days", '-1 day'),
        ]);
    }

    /**
     * Set a competitor URL.
     */
    public function withUrl(string $url): static
    {
        return $this->state(fn (array $attributes) => [
            'competitor_url' => $url,
        ]);
    }

    /**
     * Set a competitor SKU.
     */
    public function withSku(string $sku): static
    {
        return $this->state(fn (array $attributes) => [
            'competitor_sku' => $sku,
        ]);
    }

    /**
     * Create with no URL (null).
     */
    public function withoutUrl(): static
    {
        return $this->state(fn (array $attributes) => [
            'competitor_url' => null,
        ]);
    }

    /**
     * Create with no SKU (null).
     */
    public function withoutSku(): static
    {
        return $this->state(fn (array $attributes) => [
            'competitor_sku' => null,
        ]);
    }
}
