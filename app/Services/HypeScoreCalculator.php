<?php

namespace App\Services;

class HypeScoreCalculator
{
    /** @var int Base points awarded per AI keyword mention found on the page. */
    public const MENTION_BASE_POINTS = 10;

    /** @var float Additional points per pixel of font size above the 16px baseline. */
    public const FONT_SIZE_MULTIPLIER = 0.5;

    /** @var int Points awarded for each CSS/JS animation detected. */
    public const ANIMATION_POINTS = 15;

    /** @var int Points awarded for each glow effect (box-shadow, text-shadow with blur). */
    public const GLOW_EFFECT_POINTS = 25;

    /** @var int Points awarded for each rainbow/gradient border found. */
    public const RAINBOW_BORDER_POINTS = 30;

    /** @var float Weight applied to Lighthouse performance penalty: (100 - score) * weight. */
    public const LIGHTHOUSE_PERF_WEIGHT = 1.0;

    /** @var float Weight applied to Lighthouse accessibility penalty: (100 - score) * weight. */
    public const LIGHTHOUSE_A11Y_WEIGHT = 0.75;

    /** @var int Points awarded per detected AI-generated image. */
    public const AI_IMAGE_POINTS = 20;

    /** @var float Additional points based on max image confidence score (0-100). */
    public const AI_IMAGE_CONFIDENCE_MULTIPLIER = 0.5;

    /** @var int Baseline font size in pixels; sizes above this earn bonus points. */
    private const BASE_FONT_SIZE = 16;

    /**
     * AI-related keywords and phrases to scan for on crawled pages.
     *
     * @var list<string>
     */
    public const AI_KEYWORDS = [
        'artificial intelligence', 'machine learning', 'deep learning', 'neural network',
        'AI-powered', 'AI-driven', 'GPT', 'LLM', 'large language model', 'generative AI',
        'ChatGPT', 'copilot', 'AI assistant', 'natural language processing', 'NLP',
        'computer vision', 'transformer', 'diffusion model', 'AI agent', 'agentic',
        'AI-first', 'AI-native', 'intelligent automation', 'cognitive', 'predictive AI',
        'conversational AI', 'responsible AI', 'AI ethics', 'foundation model',
        'multimodal', 'RAG', 'retrieval augmented', 'fine-tuning', 'prompt engineering',
        'AI infrastructure', 'MLOps', 'AI platform', 'superintelligence', 'AGI',
    ];

    /**
     * Calculate the full hype score breakdown for a crawled site.
     *
     * Aggregates mention scoring, visual effects scoring, and Lighthouse audit
     * bonuses into a single total score with a detailed component breakdown.
     *
     * @param  array<int, array{text: string, font_size: int|float, has_animation: bool, has_glow: bool, context: string}>  $mentionDetails
     * @param  int  $animationCount  Total CSS/JS animations detected on the page.
     * @param  int  $glowCount  Total glow effects detected on the page.
     * @param  int  $rainbowCount  Total rainbow/gradient borders detected on the page.
     * @param  int|null  $lighthousePerf  Lighthouse performance score (0-100), or null if unavailable.
     * @param  int|null  $lighthouseA11y  Lighthouse accessibility score (0-100), or null if unavailable.
     * @param  int  $aiImageCount  Number of detected AI-generated images.
     * @param  int  $aiImageScore  Max confidence score of detected AI images (0-100).
     * @return array{
     *     total_score: int,
     *     mention_score: int,
     *     font_size_score: int,
     *     animation_score: int,
     *     visual_effects_score: int,
     *     lighthouse_perf_bonus: int,
     *     lighthouse_a11y_bonus: int,
     *     ai_image_hype_bonus: int,
     *     mention_count: int,
     *     animation_count: int,
     *     glow_count: int,
     *     rainbow_count: int,
     *     lighthouse_performance: int|null,
     *     lighthouse_accessibility: int|null,
     * }
     */
    public function calculate(
        array $mentionDetails,
        int $animationCount,
        int $glowCount,
        int $rainbowCount,
        ?int $lighthousePerf,
        ?int $lighthouseA11y,
        int $aiImageCount = 0,
        int $aiImageScore = 0,
    ): array {
        [$mentionScore, $fontSizeScore] = $this->calculateMentionScore($mentionDetails);
        [$animationScore, $visualEffectsScore] = $this->calculateVisualEffectsScore($animationCount, $glowCount, $rainbowCount);
        [$perfBonus, $a11yBonus] = $this->calculateLighthouseBonus($lighthousePerf, $lighthouseA11y);
        $aiImageBonus = $this->calculateAiImageBonus($aiImageCount, $aiImageScore);

        $totalScore = $mentionScore + $fontSizeScore + $animationScore + $visualEffectsScore + $perfBonus + $a11yBonus + $aiImageBonus;

        return [
            'total_score' => max(0, (int) round($totalScore)),
            'mention_score' => (int) round($mentionScore),
            'font_size_score' => (int) round($fontSizeScore),
            'animation_score' => (int) round($animationScore),
            'visual_effects_score' => (int) round($visualEffectsScore),
            'lighthouse_perf_bonus' => (int) round($perfBonus),
            'lighthouse_a11y_bonus' => (int) round($a11yBonus),
            'ai_image_hype_bonus' => (int) round($aiImageBonus),
            'mention_count' => count($mentionDetails),
            'animation_count' => $animationCount,
            'glow_count' => $glowCount,
            'rainbow_count' => $rainbowCount,
            'lighthouse_performance' => $lighthousePerf,
            'lighthouse_accessibility' => $lighthouseA11y,
        ];
    }

    /**
     * Calculate the score contribution from AI keyword mentions.
     *
     * Each mention earns base points. Mentions displayed at font sizes larger
     * than the 16px baseline earn additional points proportional to the size
     * difference. Mentions that are animated or glowing earn inline bonuses
     * on top of the global visual effects tally.
     *
     * @param  array<int, array{text: string, font_size: int|float, has_animation: bool, has_glow: bool, context: string}>  $mentionDetails
     * @return array{0: float, 1: float} [mention_score, font_size_score]
     */
    public function calculateMentionScore(array $mentionDetails): array
    {
        $mentionScore = 0.0;
        $fontSizeScore = 0.0;

        foreach ($mentionDetails as $mention) {
            // Base points for each mention found
            $mentionScore += self::MENTION_BASE_POINTS;

            // Bonus for font sizes above the baseline
            $fontSize = (float) ($mention['font_size'] ?? self::BASE_FONT_SIZE);
            if ($fontSize > self::BASE_FONT_SIZE) {
                $fontSizeScore += ($fontSize - self::BASE_FONT_SIZE) * self::FONT_SIZE_MULTIPLIER;
            }

            // Inline animation bonus (per-mention, stacks with global animation count)
            if (! empty($mention['has_animation'])) {
                $mentionScore += self::ANIMATION_POINTS;
            }

            // Inline glow bonus (per-mention, stacks with global glow count)
            if (! empty($mention['has_glow'])) {
                $mentionScore += self::GLOW_EFFECT_POINTS;
            }
        }

        return [$mentionScore, $fontSizeScore];
    }

    /**
     * Calculate the score contribution from page-wide visual effects.
     *
     * Animations, glow effects, and rainbow/gradient borders each contribute
     * points proportional to their count.
     *
     * @param  int  $animationCount  Total CSS/JS animations on the page.
     * @param  int  $glowCount  Total glow effects on the page.
     * @param  int  $rainbowCount  Total rainbow/gradient borders on the page.
     * @return array{0: float, 1: float} [animation_score, visual_effects_score]
     */
    public function calculateVisualEffectsScore(int $animationCount, int $glowCount, int $rainbowCount): array
    {
        $animationScore = $animationCount * self::ANIMATION_POINTS;
        $visualEffectsScore = ($glowCount * self::GLOW_EFFECT_POINTS) + ($rainbowCount * self::RAINBOW_BORDER_POINTS);

        return [(float) $animationScore, (float) $visualEffectsScore];
    }

    /**
     * Calculate the Lighthouse audit bonus (or penalty avoidance).
     *
     * Sites that sacrifice performance or accessibility in pursuit of hype
     * earn bonus points: the further below 100 a Lighthouse score is, the
     * more "hype bonus" points are awarded, reflecting the trade-off between
     * flashy effects and technical quality.
     *
     * @param  int|null  $perfScore  Lighthouse performance score (0-100), or null if unavailable.
     * @param  int|null  $a11yScore  Lighthouse accessibility score (0-100), or null if unavailable.
     * @return array{0: float, 1: float} [perf_bonus, a11y_bonus]
     */
    public function calculateLighthouseBonus(?int $perfScore, ?int $a11yScore): array
    {
        $perfBonus = 0.0;
        $a11yBonus = 0.0;

        if ($perfScore !== null) {
            $clampedPerf = max(0, min(100, $perfScore));
            $perfBonus = (100 - $clampedPerf) * self::LIGHTHOUSE_PERF_WEIGHT;
        }

        if ($a11yScore !== null) {
            $clampedA11y = max(0, min(100, $a11yScore));
            $a11yBonus = (100 - $clampedA11y) * self::LIGHTHOUSE_A11Y_WEIGHT;
        }

        return [$perfBonus, $a11yBonus];
    }

    /**
     * Calculate the hype bonus from AI-generated images.
     *
     * Each detected AI image earns base points, plus a confidence-weighted
     * bonus based on how certain the detection was.
     *
     * @param  int  $aiImageCount  Number of images above the confidence threshold.
     * @param  int  $aiImageScore  Max confidence score (0-100).
     */
    public function calculateAiImageBonus(int $aiImageCount, int $aiImageScore): float
    {
        return ($aiImageCount * self::AI_IMAGE_POINTS) + ($aiImageScore * self::AI_IMAGE_CONFIDENCE_MULTIPLIER);
    }

    /**
     * Get a human-readable explanation of the scoring algorithm.
     *
     * Returns an array of scoring factors, each with a label, description,
     * and weight/formula so the algorithm can be displayed transparently
     * to users.
     *
     * @return array<int, array{name: string, description: string, weight: string, example: string}>
     */
    public static function getAlgorithmExplanation(): array
    {
        return [
            [
                'name' => 'AI Keyword Mentions',
                'description' => 'Each occurrence of an AI-related keyword or phrase earns base points. We scan for '.count(self::AI_KEYWORDS).' distinct terms.',
                'weight' => self::MENTION_BASE_POINTS.' points per mention',
                'example' => 'Our AI-powered platform uses machine learning to...',
            ],
            [
                'name' => 'Font Size Bonus',
                'description' => 'Mentions displayed in fonts larger than 16px earn extra points, rewarding oversized AI buzzwords.',
                'weight' => self::FONT_SIZE_MULTIPLIER.' points per pixel above 16px baseline',
                'example' => 'A 48px heading saying "AI-POWERED" earns 16 bonus points',
            ],
            [
                'name' => 'Inline Animation Bonus',
                'description' => 'AI mentions that are individually animated (e.g., spinning, pulsing) earn additional points per mention.',
                'weight' => self::ANIMATION_POINTS.' points per animated mention',
                'example' => 'The word "AI" spinning in a hero section',
            ],
            [
                'name' => 'Inline Glow Bonus',
                'description' => 'AI mentions that have their own glow effect earn additional points per mention.',
                'weight' => self::GLOW_EFFECT_POINTS.' points per glowing mention',
                'example' => 'Neon-glowing "GENERATIVE AI" text',
            ],
            [
                'name' => 'Page Animations',
                'description' => 'Total CSS/JS animations detected on the page contribute to the hype score.',
                'weight' => self::ANIMATION_POINTS.' points per animation',
                'example' => 'Floating particles, pulsing buttons, auto-scrolling carousels',
            ],
            [
                'name' => 'Glow Effects',
                'description' => 'Glowing box-shadows and text-shadows with visible blur radii signal peak hype.',
                'weight' => self::GLOW_EFFECT_POINTS.' points per glow effect',
                'example' => 'Cards with neon box-shadow: 0 0 20px cyan',
            ],
            [
                'name' => 'Rainbow/Gradient Borders',
                'description' => 'Gradient or rainbow-colored borders are a hallmark of AI marketing aesthetics.',
                'weight' => self::RAINBOW_BORDER_POINTS.' points per rainbow border',
                'example' => 'A card with a rotating conic-gradient border',
            ],
            [
                'name' => 'Lighthouse Performance',
                'description' => 'Lower Lighthouse performance scores suggest the site prioritizes flashy effects over speed, earning hype bonus.',
                'weight' => '(100 - performance_score) * '.self::LIGHTHOUSE_PERF_WEIGHT,
                'example' => 'A score of 30 earns 70 bonus hype points',
            ],
            [
                'name' => 'Lighthouse Accessibility',
                'description' => 'Lower Lighthouse accessibility scores suggest visual spectacle over usability, earning hype bonus.',
                'weight' => '(100 - accessibility_score) * '.self::LIGHTHOUSE_A11Y_WEIGHT,
                'example' => 'A score of 40 earns 45 bonus hype points',
            ],
            [
                'name' => 'AI-Generated Images',
                'description' => 'Detected AI-generated images (DALL-E, Midjourney, Stable Diffusion, etc.) earn hype points based on count and detection confidence.',
                'weight' => self::AI_IMAGE_POINTS.' points per image + confidence * '.self::AI_IMAGE_CONFIDENCE_MULTIPLIER,
                'example' => '3 AI images at 80% confidence earns 100 bonus points',
            ],
        ];
    }
}
