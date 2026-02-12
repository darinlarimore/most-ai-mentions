<?php

use App\Enums\CrawlErrorCategory;
use App\Models\CrawlError;
use App\Models\Site;

it('records errors via factory', function () {
    $site = Site::factory()->create();

    $error = CrawlError::factory()->create([
        'site_id' => $site->id,
        'category' => CrawlErrorCategory::Timeout,
        'message' => 'Connection timed out',
        'url' => $site->url,
    ]);

    expect($error->category)->toBe(CrawlErrorCategory::Timeout)
        ->and($error->site_id)->toBe($site->id)
        ->and($error->message)->toBe('Connection timed out');
});

it('associates errors with sites via relationship', function () {
    $site = Site::factory()->create();

    CrawlError::factory()->count(3)->create(['site_id' => $site->id]);

    expect($site->crawlErrors)->toHaveCount(3);
});

it('casts category to enum', function () {
    $error = CrawlError::factory()->create([
        'category' => CrawlErrorCategory::SslError,
    ]);

    $error->refresh();

    expect($error->category)->toBe(CrawlErrorCategory::SslError);
});

it('allows nullable crawl_result_id', function () {
    $error = CrawlError::factory()->create([
        'crawl_result_id' => null,
    ]);

    expect($error->crawl_result_id)->toBeNull();
});

it('cascades deletion when site is deleted', function () {
    $site = Site::factory()->create();
    CrawlError::factory()->count(2)->create(['site_id' => $site->id]);

    expect(CrawlError::where('site_id', $site->id)->count())->toBe(2);

    $site->delete();

    expect(CrawlError::where('site_id', $site->id)->count())->toBe(0);
});
