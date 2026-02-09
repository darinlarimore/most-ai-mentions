<?php

use App\Models\CrawlResult;
use App\Models\Site;

it('converts site screenshot_path to a full storage URL', function () {
    $site = Site::factory()->create([
        'screenshot_path' => 'screenshots/test-site-1234.jpg',
    ]);

    $fresh = Site::find($site->id);

    expect($fresh->screenshot_path)
        ->toContain('/storage/screenshots/test-site-1234.jpg')
        ->toStartWith('http');
});

it('returns null when site screenshot_path is null', function () {
    $site = Site::factory()->create([
        'screenshot_path' => null,
    ]);

    expect(Site::find($site->id)->screenshot_path)->toBeNull();
});

it('converts crawl result annotated_screenshot_path to a full storage URL', function () {
    $site = Site::factory()->create();
    $crawlResult = CrawlResult::factory()->create([
        'site_id' => $site->id,
        'annotated_screenshot_path' => 'screenshots/annotated-test-1234.jpg',
    ]);

    $fresh = CrawlResult::find($crawlResult->id);

    expect($fresh->annotated_screenshot_path)
        ->toContain('/storage/screenshots/annotated-test-1234.jpg')
        ->toStartWith('http');
});
