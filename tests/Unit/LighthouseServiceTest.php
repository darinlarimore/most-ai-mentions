<?php

use App\Services\LighthouseService;

it('parses valid Lighthouse JSON output', function () {
    $json = json_encode([
        'categories' => [
            'performance' => ['score' => 0.92],
        ],
    ]);

    $service = new LighthouseService;
    $result = $service->parseOutput($json);

    expect($result)->toBeArray()
        ->and($result['performance'])->toBe(92);
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

it('returns zero for missing performance score', function () {
    $json = json_encode([
        'categories' => [],
    ]);

    $service = new LighthouseService;
    $result = $service->parseOutput($json);

    expect($result['performance'])->toBe(0);
});

it('rounds scores correctly', function () {
    $json = json_encode([
        'categories' => [
            'performance' => ['score' => 0.895],
        ],
    ]);

    $service = new LighthouseService;
    $result = $service->parseOutput($json);

    expect($result['performance'])->toBe(90); // 89.5 rounds to 90
});

it('handles null score values', function () {
    $json = json_encode([
        'categories' => [
            'performance' => ['score' => null],
        ],
    ]);

    $service = new LighthouseService;
    $result = $service->parseOutput($json);

    expect($result['performance'])->toBe(0);
});
