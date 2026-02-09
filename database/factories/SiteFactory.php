<?php

namespace Database\Factories;

use App\Enums\SiteCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = fake()->url();
        $domain = parse_url($url, PHP_URL_HOST);

        return [
            'url' => $url,
            'domain' => $domain,
            'name' => fake()->company(),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(SiteCategory::cases())->value,
            'screenshot_path' => null,
            'hype_score' => fake()->numberBetween(0, 2000),
            'user_rating_avg' => fake()->randomFloat(1, 0, 5),
            'user_rating_count' => fake()->numberBetween(0, 100),
            'ai_content_score' => fake()->numberBetween(0, 100),
            'crawl_count' => fake()->numberBetween(0, 50),
            'status' => 'completed',
            'last_crawled_at' => fake()->dateTimeBetween('-30 days'),
            'cooldown_hours' => 24,
            'is_active' => true,
            'submitted_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the site is pending crawl.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'hype_score' => 0,
            'crawl_count' => 0,
            'last_crawled_at' => null,
        ]);
    }

    /**
     * Indicate that the site is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
