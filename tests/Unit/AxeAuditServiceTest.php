<?php

use App\Services\AxeAuditService;

it('parses valid axe-core JSON output', function () {
    $json = json_encode([
        'violations_count' => 12,
        'passes_count' => 45,
        'violations_summary' => [
            ['id' => 'color-contrast', 'impact' => 'serious', 'description' => 'Elements must have sufficient color contrast', 'nodes_count' => 8],
            ['id' => 'image-alt', 'impact' => 'critical', 'description' => 'Images must have alternate text', 'nodes_count' => 4],
        ],
    ]);

    $service = new AxeAuditService;
    $result = $service->parseOutput($json);

    expect($result)->toBeArray()
        ->and($result['violations_count'])->toBe(12)
        ->and($result['passes_count'])->toBe(45)
        ->and($result['violations_summary'])->toHaveCount(2)
        ->and($result['violations_summary'][0]['id'])->toBe('color-contrast')
        ->and($result['violations_summary'][1]['impact'])->toBe('critical');
});

it('returns null for invalid JSON', function () {
    $service = new AxeAuditService;
    $result = $service->parseOutput('not valid json');

    expect($result)->toBeNull();
});

it('returns null when violations_count is missing', function () {
    $json = json_encode(['passes_count' => 10, 'violations_summary' => []]);

    $service = new AxeAuditService;
    $result = $service->parseOutput($json);

    expect($result)->toBeNull();
});

it('handles empty violations summary', function () {
    $json = json_encode([
        'violations_count' => 0,
        'passes_count' => 50,
        'violations_summary' => [],
    ]);

    $service = new AxeAuditService;
    $result = $service->parseOutput($json);

    expect($result['violations_count'])->toBe(0)
        ->and($result['passes_count'])->toBe(50)
        ->and($result['violations_summary'])->toBeEmpty();
});

it('handles missing violations_summary key gracefully', function () {
    $json = json_encode([
        'violations_count' => 3,
        'passes_count' => 20,
    ]);

    $service = new AxeAuditService;
    $result = $service->parseOutput($json);

    expect($result['violations_summary'])->toBeEmpty();
});
