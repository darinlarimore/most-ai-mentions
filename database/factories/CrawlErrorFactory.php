<?php

namespace Database\Factories;

use App\Enums\CrawlErrorCategory;
use App\Models\CrawlResult;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CrawlError>
 */
class CrawlErrorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'crawl_result_id' => null,
            'category' => fake()->randomElement(CrawlErrorCategory::cases()),
            'message' => fake()->sentence(),
            'url' => fake()->url(),
        ];
    }

    /**
     * Associate the error with a crawl result.
     */
    public function withCrawlResult(): static
    {
        return $this->state(fn (array $attributes) => [
            'crawl_result_id' => CrawlResult::factory(),
        ]);
    }
}
