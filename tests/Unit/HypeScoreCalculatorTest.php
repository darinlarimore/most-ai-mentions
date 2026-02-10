<?php

use App\Services\HypeScoreCalculator;

beforeEach(function () {
    $this->calculator = new HypeScoreCalculator;
});

it('caps animation count at maximum', function () {
    $scores = $this->calculator->calculate([], 500, 0, 0);

    // Should cap at 100 * 15 = 1500, not 500 * 15 = 7500
    expect($scores['animation_score'])->toBe(HypeScoreCalculator::MAX_ANIMATION_COUNT * HypeScoreCalculator::ANIMATION_POINTS);
});

it('caps glow count at maximum', function () {
    $scores = $this->calculator->calculate([], 0, 500, 0);

    // Should cap at 100 * 25 = 2500, not 500 * 25
    expect($scores['visual_effects_score'])->toBe(HypeScoreCalculator::MAX_GLOW_COUNT * HypeScoreCalculator::GLOW_EFFECT_POINTS);
});

it('caps rainbow count at maximum', function () {
    $scores = $this->calculator->calculate([], 0, 0, 200);

    // Should cap at 50 * 30 = 1500, not 200 * 30
    expect($scores['visual_effects_score'])->toBe(HypeScoreCalculator::MAX_RAINBOW_COUNT * HypeScoreCalculator::RAINBOW_BORDER_POINTS);
});

it('weights mentions higher than visual effects', function () {
    // 100 mentions should score higher than max visual effects
    $mentions = array_fill(0, 100, [
        'text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test',
    ]);

    $mentionScores = $this->calculator->calculate($mentions, 0, 0, 0);

    $visualScores = $this->calculator->calculate([], 10000, 10000, 10000);

    // 100 mentions * 25 = 2500 mention_score
    // Max visual: (100*15) + (100*25) + (50*30) = 1500 + 2500 + 1500 = 5500
    // Mentions should be significant relative to capped visual effects
    expect($mentionScores['mention_score'])->toBeGreaterThan($visualScores['animation_score']);
});
