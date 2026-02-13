<?php

use App\Services\HypeScoreCalculator;

beforeEach(function () {
    $this->calculator = new HypeScoreCalculator;
});

it('caps animation count at maximum', function () {
    $scores = $this->calculator->calculate([], 500, 0, 0);

    // Should cap at 10 * 15 = 150, not 500 * 15
    expect($scores['animation_score'])->toBe(HypeScoreCalculator::MAX_ANIMATION_COUNT * HypeScoreCalculator::ANIMATION_POINTS);
});

it('caps glow count at maximum', function () {
    $scores = $this->calculator->calculate([], 0, 500, 0);

    // Should cap at 10 * 25 = 250, not 500 * 25
    expect($scores['visual_effects_score'])->toBe(HypeScoreCalculator::MAX_GLOW_COUNT * HypeScoreCalculator::GLOW_EFFECT_POINTS);
});

it('caps rainbow count at maximum', function () {
    $scores = $this->calculator->calculate([], 0, 0, 200);

    // Should cap at 5 * 30 = 150, not 200 * 30
    expect($scores['visual_effects_score'])->toBe(HypeScoreCalculator::MAX_RAINBOW_COUNT * HypeScoreCalculator::RAINBOW_BORDER_POINTS);
});

it('density plus mentions dominate over maxed visual effects', function () {
    $mentions = array_fill(0, 30, [
        'text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test',
    ]);

    // 30 AI mentions in 300 words = 10% density = 1000 pts + 30*5=150 mention pts = 1150
    $mentionScores = $this->calculator->calculate($mentions, 0, 0, 0, 300);
    $visualScores = $this->calculator->calculate([], 10000, 10000, 10000);

    // Max visual total: (10*15) + (10*25) + (5*30) = 150 + 250 + 150 = 550
    $maxVisualTotal = $visualScores['animation_score'] + $visualScores['visual_effects_score'];
    $densityPlusMentions = $mentionScores['density_score'] + $mentionScores['mention_score'];

    expect($densityPlusMentions)->toBeGreaterThan($maxVisualTotal);
});

it('includes AI product and company keywords', function () {
    $keywords = HypeScoreCalculator::AI_KEYWORDS;

    expect($keywords)->toContain('Claude')
        ->toContain('Gemini')
        ->toContain('GPT-4')
        ->toContain('Midjourney')
        ->toContain('Llama')
        ->toContain('Mistral');
});

it('includes newer AI concept keywords', function () {
    $keywords = HypeScoreCalculator::AI_KEYWORDS;

    expect($keywords)->toContain('AI safety')
        ->toContain('hallucination')
        ->toContain('chain of thought')
        ->toContain('embeddings')
        ->toContain('vector database')
        ->toContain('reinforcement learning');
});

it('includes marketing buzzword keywords', function () {
    $keywords = HypeScoreCalculator::AI_KEYWORDS;

    expect($keywords)->toContain('AI-enhanced')
        ->toContain('powered by AI')
        ->toContain('built with AI')
        ->toContain('AI transformation');
});

it('awards base points per mention', function () {
    $mentions = [
        ['text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test'],
    ];

    $scores = $this->calculator->calculate($mentions, 0, 0, 0);

    expect($scores['mention_score'])->toBe(HypeScoreCalculator::MENTION_BASE_POINTS);
    expect($scores['font_size_score'])->toBe(0);
});

it('awards font size bonus for large text', function () {
    $mentions = [
        ['text' => 'AI', 'font_size' => 48, 'has_animation' => false, 'has_glow' => false, 'context' => 'test'],
    ];

    $scores = $this->calculator->calculate($mentions, 0, 0, 0);

    // (48 - 16) * 1.5 = 48
    expect($scores['font_size_score'])->toBe(48);
});

it('caps mention score at maximum', function () {
    // 50 mentions * 5 = 250, but capped at 200
    $mentions = array_fill(0, 50, [
        'text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test',
    ]);

    $scores = $this->calculator->calculate($mentions, 0, 0, 0);

    expect($scores['mention_score'])->toBe(HypeScoreCalculator::MENTION_MAX_SCORE);
});

it('calculates density score from word count', function () {
    // 10 single-word mentions ("AI") in 1000 words = 1% density
    // Base score 250 × uncapped log multiplier at 1000 words ≈ 1.65 = 413
    $mentions = array_fill(0, 10, [
        'text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test',
    ]);

    $scores = $this->calculator->calculate($mentions, 0, 0, 0, 1000);

    expect($scores['ai_density_percent'])->toBe(1.0);
    expect($scores['density_score'])->toBe(413);
    expect($scores['total_word_count'])->toBe(1000);
});

it('returns zero density for pages under minimum word count', function () {
    $mentions = [
        ['text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test'],
    ];

    $scores = $this->calculator->calculate($mentions, 0, 0, 0, 30);

    expect($scores['density_score'])->toBe(0);
    expect($scores['ai_density_percent'])->toBe(0.0);
});

it('boosts density score above base max with word count multiplier', function () {
    // 20 mentions of "AI" in 200 words = 10% density, base score 1000
    // Multiplier at 200 words ≈ 1.301, so adjusted = round(1000 * 1.301) = 1301
    $mentions = array_fill(0, 20, [
        'text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test',
    ]);

    $scores = $this->calculator->calculate($mentions, 0, 0, 0, 200);

    expect($scores['density_score'])->toBeGreaterThan(HypeScoreCalculator::DENSITY_MAX_SCORE);
});

it('gives same density score for 10% and 25% at same word count', function () {
    // Base interpolation maxes out at 10%, so 25% density has the same base score
    $tenPercent = array_fill(0, 20, [
        'text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test',
    ]);
    $twentyFivePercent = array_fill(0, 50, [
        'text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test',
    ]);

    $scores10 = $this->calculator->calculate($tenPercent, 0, 0, 0, 200);
    $scores25 = $this->calculator->calculate($twentyFivePercent, 0, 0, 0, 200);

    expect($scores10['density_score'])->toBe($scores25['density_score']);
});

it('interpolates density score between breakpoints', function () {
    // 0.5% = 100, 1% = 250 → 0.75% should be ~175
    $score = $this->calculator->interpolateDensityScore(0.75);

    expect($score)->toBe(175);
});

it('counts multi-word mentions correctly for density', function () {
    // 5 mentions of "machine learning" (2 words each) in 1000 words = 10/1000 = 1%
    // Base score 250 × uncapped log multiplier ≈ 1.65 = 413
    $mentions = array_fill(0, 5, [
        'text' => 'machine learning', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test',
    ]);

    $scores = $this->calculator->calculate($mentions, 0, 0, 0, 1000);

    expect($scores['ai_density_percent'])->toBe(1.0);
    expect($scores['density_score'])->toBe(413);
});

it('boosts density score for pages with more words', function () {
    // Same 1% density at different word counts should yield different scores
    // 1 mention in 100 words vs 10 mentions in 1000 words
    $smallPage = $this->calculator->calculate(
        [['text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test']],
        0, 0, 0, 100,
    );
    $largePage = $this->calculator->calculate(
        array_fill(0, 10, ['text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test']),
        0, 0, 0, 1000,
    );

    // Both have 1% density, but the larger page should score higher
    expect($smallPage['ai_density_percent'])->toBe(1.0);
    expect($largePage['ai_density_percent'])->toBe(1.0);
    expect($largePage['density_score'])->toBeGreaterThan($smallPage['density_score']);
});

it('applies no multiplier at minimum word count', function () {
    // At exactly MIN_WORD_COUNT (50), multiplier = 1.0 + 0.5*log10(1) = 1.0
    // 5 mentions of "AI" in 50 words = 10% density => base score 1000 × 1.0 = 1000
    $mentions = array_fill(0, 5, [
        'text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test',
    ]);

    $scores = $this->calculator->calculate($mentions, 0, 0, 0, 50);

    expect($scores['density_score'])->toBe(1000);
});

it('returns zero density for zero word count', function () {
    $mentions = [
        ['text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test'],
    ];

    $scores = $this->calculator->calculate($mentions, 0, 0, 0, 0);

    expect($scores['density_score'])->toBe(0);
    expect($scores['ai_density_percent'])->toBe(0.0);
});
