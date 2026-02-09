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
        $lighthousePerformance = fake()->numberBetween(10, 100);
        $lighthouseAccessibility = fake()->numberBetween(10, 100);

        return [
            'site_id' => Site::factory(),
            'total_score' => fake()->numberBetween(0, 2000),
            'ai_mention_count' => fake()->numberBetween(0, 100),
            'mention_details' => $this->generateMentionDetails(),
            'mention_score' => fake()->numberBetween(0, 500),
            'font_size_score' => fake()->numberBetween(0, 300),
            'animation_score' => fake()->numberBetween(0, 200),
            'visual_effects_score' => fake()->numberBetween(0, 300),
            'lighthouse_performance' => $lighthousePerformance,
            'lighthouse_accessibility' => $lighthouseAccessibility,
            'lighthouse_perf_bonus' => (int) round($lighthousePerformance * 0.5),
            'lighthouse_a11y_bonus' => (int) round($lighthouseAccessibility * 0.3),
            'animation_count' => fake()->numberBetween(0, 20),
            'glow_effect_count' => fake()->numberBetween(0, 10),
            'rainbow_border_count' => fake()->numberBetween(0, 5),
            'ai_image_count' => fake()->numberBetween(0, 8),
            'ai_image_score' => fake()->numberBetween(0, 100),
            'ai_image_details' => $this->generateAiImageDetails(),
            'ai_image_hype_bonus' => fake()->numberBetween(0, 200),
            'status' => 'completed',
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
     * Generate a sample array of AI image detail objects.
     *
     * @return array<int, array<string, mixed>>
     */
    private function generateAiImageDetails(): array
    {
        $aiTools = ['DALL-E', 'Midjourney', 'Stable Diffusion', 'Flux', 'Leonardo AI', 'Ideogram'];
        $domains = [
            'oaidalleapiprodscus.blob.core.windows.net',
            'cdn.midjourney.com',
            'replicate.delivery',
        ];
        $count = fake()->numberBetween(0, 4);
        $details = [];

        for ($i = 0; $i < $count; $i++) {
            $confidence = fake()->numberBetween(20, 95);
            $details[] = [
                'url' => 'https://'.fake()->randomElement($domains).'/'.fake()->uuid().'.png',
                'confidence' => $confidence,
                'signals' => [
                    'AI CDN domain: '.fake()->randomElement($domains),
                    'Context keyword: '.strtolower(fake()->randomElement($aiTools)),
                ],
                'breakdown' => [
                    'url_patterns' => fake()->numberBetween(0, 100),
                    'metadata' => fake()->numberBetween(0, 100),
                    'html_context' => fake()->numberBetween(0, 100),
                    'resolution' => fake()->numberBetween(0, 100),
                    'format_quirks' => fake()->numberBetween(0, 100),
                ],
            ];
        }

        return $details;
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
