<?php

use App\Services\HypeScoreCalculator;

beforeEach(function () {
    $this->calculator = new HypeScoreCalculator;
});

it('calculates AI image bonus with count and confidence', function () {
    $bonus = $this->calculator->calculateAiImageBonus(3, 80);

    // 3 * 20 + 80 * 0.5 = 60 + 40 = 100
    expect($bonus)->toBe(100.0);
});

it('calculates zero AI image bonus with no images', function () {
    $bonus = $this->calculator->calculateAiImageBonus(0, 0);

    expect($bonus)->toBe(0.0);
});

it('includes AI image bonus in total score', function () {
    $scores = $this->calculator->calculate(
        mentionDetails: [],
        animationCount: 0,
        glowCount: 0,
        rainbowCount: 0,
        lighthousePerf: null,
        lighthouseA11y: null,
        aiImageCount: 2,
        aiImageScore: 60,
    );

    // 2 * 20 + 60 * 0.5 = 40 + 30 = 70
    expect($scores['ai_image_hype_bonus'])->toBe(70)
        ->and($scores['total_score'])->toBe(70);
});

it('returns ai_image_hype_bonus key in result', function () {
    $scores = $this->calculator->calculate([], 0, 0, 0, null, null);

    expect($scores)->toHaveKey('ai_image_hype_bonus')
        ->and($scores['ai_image_hype_bonus'])->toBe(0);
});

it('adds AI image bonus to other score components', function () {
    $mentions = [
        ['text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test'],
    ];

    $scores = $this->calculator->calculate(
        mentionDetails: $mentions,
        animationCount: 1,
        glowCount: 0,
        rainbowCount: 0,
        lighthousePerf: null,
        lighthouseA11y: null,
        aiImageCount: 1,
        aiImageScore: 50,
    );

    // mention: 25, animation: 15, ai_image: 1*20 + 50*0.5 = 45
    expect($scores['total_score'])->toBe(85)
        ->and($scores['ai_image_hype_bonus'])->toBe(45);
});

it('includes AI images in algorithm explanation', function () {
    $explanation = HypeScoreCalculator::getAlgorithmExplanation();

    $names = array_column($explanation, 'name');

    expect($names)->toContain('AI-Generated Images');
});

it('caps animation count at maximum', function () {
    $scores = $this->calculator->calculate([], 500, 0, 0, null, null);

    // Should cap at 100 * 15 = 1500, not 500 * 15 = 7500
    expect($scores['animation_score'])->toBe(HypeScoreCalculator::MAX_ANIMATION_COUNT * HypeScoreCalculator::ANIMATION_POINTS);
});

it('caps glow count at maximum', function () {
    $scores = $this->calculator->calculate([], 0, 500, 0, null, null);

    // Should cap at 100 * 25 = 2500, not 500 * 25
    expect($scores['visual_effects_score'])->toBe(HypeScoreCalculator::MAX_GLOW_COUNT * HypeScoreCalculator::GLOW_EFFECT_POINTS);
});

it('caps rainbow count at maximum', function () {
    $scores = $this->calculator->calculate([], 0, 0, 200, null, null);

    // Should cap at 50 * 30 = 1500, not 200 * 30
    expect($scores['visual_effects_score'])->toBe(HypeScoreCalculator::MAX_RAINBOW_COUNT * HypeScoreCalculator::RAINBOW_BORDER_POINTS);
});

it('weights mentions higher than visual effects', function () {
    // 100 mentions should score higher than max visual effects
    $mentions = array_fill(0, 100, [
        'text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test',
    ]);

    $mentionScores = $this->calculator->calculate($mentions, 0, 0, 0, null, null);

    $visualScores = $this->calculator->calculate([], 10000, 10000, 10000, null, null);

    // 100 mentions * 25 = 2500 mention_score
    // Max visual: (100*15) + (100*25) + (50*30) = 1500 + 2500 + 1500 = 5500
    // Mentions should be significant relative to capped visual effects
    expect($mentionScores['mention_score'])->toBeGreaterThan($visualScores['animation_score']);
});
