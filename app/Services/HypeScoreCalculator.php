<?php

namespace App\Services;

class HypeScoreCalculator
{
    /** @var int Base points awarded per AI keyword mention found on the page. */
    public const MENTION_BASE_POINTS = 5;

    /** @var int Maximum total score from mentions alone. */
    public const MENTION_MAX_SCORE = 200;

    /** @var int Maximum score from AI buzzword density. */
    public const DENSITY_MAX_SCORE = 1000;

    /** @var int Pages with fewer words than this get density_score = 0. */
    public const MIN_WORD_COUNT = 50;

    /** @var float Maximum word count multiplier applied to the density score. */
    public const WORD_COUNT_MULTIPLIER_MAX = 1.5;

    /**
     * Density breakpoints for piecewise linear interpolation.
     *
     * @var array<int, array{density: float, score: int}>
     */
    public const DENSITY_BREAKPOINTS = [
        ['density' => 0.0, 'score' => 0],
        ['density' => 0.5, 'score' => 100],
        ['density' => 1.0, 'score' => 250],
        ['density' => 2.0, 'score' => 500],
        ['density' => 5.0, 'score' => 800],
        ['density' => 10.0, 'score' => 1000],
    ];

    /** @var float Additional points per pixel of font size above the 16px baseline. */
    public const FONT_SIZE_MULTIPLIER = 1.5;

    /** @var int Points awarded for each CSS/JS animation detected (after cap). */
    public const ANIMATION_POINTS = 15;

    /** @var int Points awarded for each glow effect (after cap). */
    public const GLOW_EFFECT_POINTS = 25;

    /** @var int Points awarded for each rainbow/gradient border found (after cap). */
    public const RAINBOW_BORDER_POINTS = 30;

    /** @var int Maximum animation count used for scoring. */
    public const MAX_ANIMATION_COUNT = 10;

    /** @var int Maximum glow effect count used for scoring. */
    public const MAX_GLOW_COUNT = 10;

    /** @var int Maximum rainbow border count used for scoring. */
    public const MAX_RAINBOW_COUNT = 5;

    /** @var int Baseline font size in pixels; sizes above this earn bonus points. */
    private const BASE_FONT_SIZE = 16;

    /**
     * AI-related keywords and phrases to scan for on crawled pages.
     *
     * @var list<string>
     */
    public const AI_KEYWORDS = [
        // Core AI terms
        'artificial intelligence', 'machine learning', 'deep learning', 'neural network', 'neural net',
        'AI-powered', 'AI-driven', 'GPT', 'LLM', 'large language model', 'generative AI',
        'ChatGPT', 'copilot', 'AI assistant', 'natural language processing', 'NLP',
        'computer vision', 'transformer', 'diffusion model', 'AI agent', 'agentic',
        'AI-first', 'AI-native', 'intelligent automation', 'cognitive', 'predictive AI',
        'conversational AI', 'responsible AI', 'AI ethics', 'foundation model',
        'multimodal', 'RAG', 'retrieval augmented', 'fine-tuning', 'prompt engineering',
        'AI infrastructure', 'MLOps', 'AI platform', 'superintelligence', 'AGI',

        // AI products and companies
        'Claude', 'Gemini', 'GPT-4', 'GPT-4o', 'Midjourney', 'DALL-E',
        'Stable Diffusion', 'Perplexity', 'Llama', 'Mistral',

        // Technical ML terms
        'reinforcement learning', 'embeddings', 'vector database', 'attention mechanism',
        'inference', 'training data', 'supervised learning',

        // Newer AI concepts
        'AI safety', 'AI alignment', 'hallucination', 'reasoning model',
        'chain of thought', 'AI governance', 'synthetic data', 'AI regulation',

        // Marketing buzzwords
        'AI-enhanced', 'AI-enabled', 'AI-optimized', 'powered by AI', 'built with AI',
        'AI integration', 'AI solution', 'AI transformation',
    ];

    /**
     * Calculate the full hype score breakdown for a crawled site.
     *
     * @param  array<int, array{text: string, font_size: int|float, has_animation: bool, has_glow: bool, context: string}>  $mentionDetails
     * @param  int  $animationCount  Total CSS/JS animations detected on the page.
     * @param  int  $glowCount  Total glow effects detected on the page.
     * @param  int  $rainbowCount  Total rainbow/gradient borders detected on the page.
     * @param  int  $totalWordCount  Total visible words on the page.
     * @return array{
     *     total_score: int,
     *     density_score: int,
     *     ai_density_percent: float,
     *     total_word_count: int,
     *     mention_score: int,
     *     font_size_score: int,
     *     animation_score: int,
     *     visual_effects_score: int,
     *     mention_count: int,
     *     animation_count: int,
     *     glow_count: int,
     *     rainbow_count: int,
     * }
     */
    public function calculate(
        array $mentionDetails,
        int $animationCount,
        int $glowCount,
        int $rainbowCount,
        int $totalWordCount = 0,
    ): array {
        [$mentionScore, $fontSizeScore] = $this->calculateMentionScore($mentionDetails);
        [$animationScore, $visualEffectsScore] = $this->calculateVisualEffectsScore($animationCount, $glowCount, $rainbowCount);
        [$densityScore, $densityPercent] = $this->calculateDensityScore($mentionDetails, $totalWordCount);

        $mentionScore = min($mentionScore, self::MENTION_MAX_SCORE);

        $totalScore = $densityScore + $mentionScore + $fontSizeScore + $animationScore + $visualEffectsScore;

        return [
            'total_score' => max(0, (int) round($totalScore)),
            'density_score' => $densityScore,
            'ai_density_percent' => round($densityPercent, 2),
            'total_word_count' => $totalWordCount,
            'mention_score' => (int) round($mentionScore),
            'font_size_score' => (int) round($fontSizeScore),
            'animation_score' => (int) round($animationScore),
            'visual_effects_score' => (int) round($visualEffectsScore),
            'mention_count' => count($mentionDetails),
            'animation_count' => $animationCount,
            'glow_count' => $glowCount,
            'rainbow_count' => $rainbowCount,
        ];
    }

    /**
     * Calculate the density score from AI keyword mentions relative to total word count.
     *
     * Counts the number of words contributed by AI keyword mentions, divides by
     * total word count to get a density percentage, then interpolates through
     * the breakpoint table.
     *
     * @param  array<int, array{text: string, font_size: int|float, has_animation: bool, has_glow: bool, context: string}>  $mentionDetails
     * @param  int  $totalWordCount  Total visible words on the page.
     * @return array{0: int, 1: float} [density_score, density_percent]
     */
    public function calculateDensityScore(array $mentionDetails, int $totalWordCount): array
    {
        if ($totalWordCount < self::MIN_WORD_COUNT) {
            return [0, 0.0];
        }

        $aiWordCount = 0;
        foreach ($mentionDetails as $mention) {
            $aiWordCount += str_word_count($mention['text']);
        }

        $densityPercent = ($aiWordCount / $totalWordCount) * 100;

        $baseScore = $this->interpolateDensityScore($densityPercent);

        // Pages with more content get a gentle boost: 1.0x at 50 words, up to 1.5x at 500+ words
        $multiplier = min(
            self::WORD_COUNT_MULTIPLIER_MAX,
            1.0 + 0.5 * log10($totalWordCount / self::MIN_WORD_COUNT),
        );

        $adjustedScore = min((int) round($baseScore * $multiplier), self::DENSITY_MAX_SCORE);

        return [$adjustedScore, $densityPercent];
    }

    /**
     * Piecewise linear interpolation through the density breakpoints.
     */
    public function interpolateDensityScore(float $density): int
    {
        $breakpoints = self::DENSITY_BREAKPOINTS;

        if ($density <= 0) {
            return 0;
        }

        if ($density >= $breakpoints[count($breakpoints) - 1]['density']) {
            return self::DENSITY_MAX_SCORE;
        }

        for ($i = 1; $i < count($breakpoints); $i++) {
            if ($density <= $breakpoints[$i]['density']) {
                $lower = $breakpoints[$i - 1];
                $upper = $breakpoints[$i];

                $fraction = ($density - $lower['density']) / ($upper['density'] - $lower['density']);

                return (int) round($lower['score'] + $fraction * ($upper['score'] - $lower['score']));
            }
        }

        return self::DENSITY_MAX_SCORE;
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
        $cappedAnimations = min($animationCount, self::MAX_ANIMATION_COUNT);
        $cappedGlows = min($glowCount, self::MAX_GLOW_COUNT);
        $cappedRainbows = min($rainbowCount, self::MAX_RAINBOW_COUNT);

        $animationScore = $cappedAnimations * self::ANIMATION_POINTS;
        $visualEffectsScore = ($cappedGlows * self::GLOW_EFFECT_POINTS) + ($cappedRainbows * self::RAINBOW_BORDER_POINTS);

        return [(float) $animationScore, (float) $visualEffectsScore];
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
                'name' => 'AI Buzzword Density',
                'description' => 'The percentage of a page\'s visible text that consists of AI buzzwords. This is the primary scoring factor. Pages with more content get a gentle boost (up to '.self::WORD_COUNT_MULTIPLIER_MAX.'x) rewarding sites that sustain high density across lots of text.',
                'weight' => 'Up to '.self::DENSITY_MAX_SCORE.' points (piecewise scale from 0% to 10%+, boosted by word count)',
                'example' => 'A 500-word page where 5% of words are AI buzzwords scores 1,000 points',
            ],
            [
                'name' => 'AI Keyword Mentions',
                'description' => 'Each occurrence of an AI-related keyword or phrase earns base points. We scan for '.count(self::AI_KEYWORDS).' distinct terms. Capped at '.self::MENTION_MAX_SCORE.' points.',
                'weight' => self::MENTION_BASE_POINTS.' points per mention (max '.self::MENTION_MAX_SCORE.')',
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
                'description' => 'CSS/JS animations detected on the page contribute to the hype score, capped at '.self::MAX_ANIMATION_COUNT.'.',
                'weight' => self::ANIMATION_POINTS.' points per animation (max '.self::MAX_ANIMATION_COUNT.')',
                'example' => 'Floating particles, pulsing buttons, auto-scrolling carousels',
            ],
            [
                'name' => 'Glow Effects',
                'description' => 'Glowing box-shadows and text-shadows with visible blur radii signal peak hype, capped at '.self::MAX_GLOW_COUNT.'.',
                'weight' => self::GLOW_EFFECT_POINTS.' points per glow effect (max '.self::MAX_GLOW_COUNT.')',
                'example' => 'Cards with neon box-shadow: 0 0 20px cyan',
            ],
            [
                'name' => 'Rainbow/Gradient Borders',
                'description' => 'Gradient or rainbow-colored borders are a hallmark of AI marketing aesthetics, capped at '.self::MAX_RAINBOW_COUNT.'.',
                'weight' => self::RAINBOW_BORDER_POINTS.' points per rainbow border (max '.self::MAX_RAINBOW_COUNT.')',
                'example' => 'A card with a rotating conic-gradient border',
            ],
        ];
    }
}
