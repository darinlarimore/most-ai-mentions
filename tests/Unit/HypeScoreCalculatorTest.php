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

it('mentions dominate over maxed visual effects', function () {
    $mentions = array_fill(0, 30, [
        'text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test',
    ]);

    $mentionScores = $this->calculator->calculate($mentions, 0, 0, 0);
    $visualScores = $this->calculator->calculate([], 10000, 10000, 10000);

    // 30 mentions * 25 = 750 mention_score
    // Max visual total: (10*15) + (10*25) + (5*30) = 150 + 250 + 150 = 550
    $maxVisualTotal = $visualScores['animation_score'] + $visualScores['visual_effects_score'];

    expect($mentionScores['mention_score'])->toBeGreaterThan($maxVisualTotal);
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
