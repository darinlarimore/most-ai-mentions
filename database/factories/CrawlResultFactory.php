<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CrawlResult>
 */
class CrawlResultFactory extends Factory
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
            'total_score' => fake()->numberBetween(0, 2000),
            'ai_mention_count' => fake()->numberBetween(0, 100),
            'mention_details' => $this->generateMentionDetails(),
            'mention_score' => fake()->numberBetween(0, 500),
            'font_size_score' => fake()->numberBetween(0, 300),
            'animation_score' => fake()->numberBetween(0, 200),
            'visual_effects_score' => fake()->numberBetween(0, 300),
            'total_word_count' => fake()->numberBetween(100, 5000),
            'ai_density_percent' => fake()->randomFloat(2, 0, 10),
            'density_score' => fake()->numberBetween(0, 1000),
            'animation_count' => fake()->numberBetween(0, 20),
            'glow_effect_count' => fake()->numberBetween(0, 10),
            'rainbow_border_count' => fake()->numberBetween(0, 5),
            'status' => 'completed',
            'redirect_chain' => null,
            'final_url' => null,
            'response_time_ms' => null,
            'html_size_bytes' => null,
            'crawl_duration_ms' => fake()->numberBetween(3000, 120000),
            'detected_tech_stack' => null,
            'axe_violations_count' => fake()->numberBetween(0, 50),
            'axe_passes_count' => fake()->numberBetween(10, 100),
            'axe_violations_summary' => $this->generateAxeViolationsSummary(),
            'lighthouse_performance' => fake()->numberBetween(20, 100),
            'lighthouse_accessibility' => fake()->numberBetween(40, 100),
            'lighthouse_best_practices' => fake()->numberBetween(50, 100),
            'lighthouse_seo' => fake()->numberBetween(60, 100),
        ];
    }

    /**
     * Generate a sample array of mention detail objects.
     *
     * @return array<int, array<string, mixed>>
     */
    private function generateMentionDetails(): array
    {
        $aiTerms = ['AI', 'artificial intelligence', 'machine learning', 'GPT', 'neural network', 'deep learning', 'LLM'];
        $count = fake()->numberBetween(3, 5);
        $mentions = [];

        for ($i = 0; $i < $count; $i++) {
            $mentions[] = [
                'text' => fake()->randomElement($aiTerms),
                'font_size' => fake()->numberBetween(12, 72),
                'has_animation' => fake()->boolean(30),
                'has_glow' => fake()->boolean(20),
                'context' => fake()->sentence(),
            ];
        }

        return $mentions;
    }

    /**
     * Generate a sample array of axe-core violation summaries.
     *
     * @return array<int, array<string, mixed>>
     */
    private function generateAxeViolationsSummary(): array
    {
        $violations = [
            ['id' => 'color-contrast', 'impact' => 'serious', 'description' => 'Elements must have sufficient color contrast'],
            ['id' => 'image-alt', 'impact' => 'critical', 'description' => 'Images must have alternate text'],
            ['id' => 'link-name', 'impact' => 'serious', 'description' => 'Links must have discernible text'],
            ['id' => 'label', 'impact' => 'critical', 'description' => 'Form elements must have labels'],
            ['id' => 'heading-order', 'impact' => 'moderate', 'description' => 'Heading levels should only increase by one'],
        ];

        $count = fake()->numberBetween(1, 4);
        $selected = fake()->randomElements($violations, $count);

        return array_map(fn (array $v) => [
            ...$v,
            'nodes_count' => fake()->numberBetween(1, 10),
        ], $selected);
    }

    /**
     * Indicate that the crawl result failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'total_score' => 0,
            'error_message' => fake()->sentence(),
        ]);
    }
}
