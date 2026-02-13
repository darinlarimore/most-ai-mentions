<?php

use App\Services\LighthouseService;

it('parses valid Lighthouse JSON output', function () {
    $json = json_encode([
        'categories' => [
            'performance' => ['score' => 0.92],
            'accessibility' => ['score' => 0.85],
            'best-practices' => ['score' => 1.0],
            'seo' => ['score' => 0.78],
        ],
    ]);

    $service = new LighthouseService;
    $result = $service->parseOutput($json);

    expect($result)->toBeArray()
        ->and($result['performance'])->toBe(92)
        ->and($result['accessibility'])->toBe(85)
        ->and($result['best_practices'])->toBe(100)
        ->and($result['seo'])->toBe(78);
});

it('returns null for invalid JSON', function () {
    $service = new LighthouseService;
    $result = $service->parseOutput('not valid json');

    expect($result)->toBeNull();
});

it('returns null when categories key is missing', function () {
    $json = json_encode(['audits' => []]);

    $service = new LighthouseService;
    $result = $service->parseOutput($json);

    expect($result)->toBeNull();
});

it('returns zero for missing category scores', function () {
    $json = json_encode([
        'categories' => [
            'performance' => ['score' => 0.75],
            // accessibility, best-practices, seo missing
        ],
    ]);

    $service = new LighthouseService;
    $result = $service->parseOutput($json);

    expect($result['performance'])->toBe(75)
        ->and($result['accessibility'])->toBe(0)
        ->and($result['best_practices'])->toBe(0)
        ->and($result['seo'])->toBe(0);
});

it('rounds scores correctly', function () {
    $json = json_encode([
        'categories' => [
            'performance' => ['score' => 0.895],
            'accessibility' => ['score' => 0.334],
            'best-practices' => ['score' => 0.505],
            'seo' => ['score' => 0.999],
        ],
    ]);

    $service = new LighthouseService;
    $result = $service->parseOutput($json);

    expect($result['performance'])->toBe(90)  // 89.5 rounds to 90
        ->and($result['accessibility'])->toBe(33) // 33.4 rounds to 33
        ->and($result['best_practices'])->toBe(51) // 50.5 rounds to 51
        ->and($result['seo'])->toBe(100); // 99.9 rounds to 100
});

it('handles null score values', function () {
    $json = json_encode([
        'categories' => [
            'performance' => ['score' => null],
            'accessibility' => ['score' => 0.5],
            'best-practices' => ['score' => null],
            'seo' => ['score' => 0.8],
        ],
    ]);

    $service = new LighthouseService;
    $result = $service->parseOutput($json);

    expect($result['performance'])->toBe(0)
        ->and($result['accessibility'])->toBe(50)
        ->and($result['best_practices'])->toBe(0)
        ->and($result['seo'])->toBe(80);
});
