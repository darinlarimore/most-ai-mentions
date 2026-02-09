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

    // mention: 10, animation: 15, ai_image: 1*20 + 50*0.5 = 45
    expect($scores['total_score'])->toBe(70)
        ->and($scores['ai_image_hype_bonus'])->toBe(45);
});

it('includes AI images in algorithm explanation', function () {
    $explanation = HypeScoreCalculator::getAlgorithmExplanation();

    $names = array_column($explanation, 'name');

    expect($names)->toContain('AI-Generated Images');
});
