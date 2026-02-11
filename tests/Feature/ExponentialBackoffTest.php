<?php

use App\Models\Site;

it('includes sites with 0 failures and old attempt in crawl queue', function () {
    $site = Site::factory()->create([
        'is_active' => true,
        'status' => 'pending',
        'consecutive_failures' => 0,
        'last_crawled_at' => now()->subHours(25),
        'last_attempted_at' => now()->subHours(2),
    ]);

    $queue = Site::query()->crawlQueue()->pluck('id');

    expect($queue)->toContain($site->id);
});

it('excludes sites with 1 failure and recent attempt from crawl queue', function () {
    $site = Site::factory()->create([
        'is_active' => true,
        'status' => 'pending',
        'consecutive_failures' => 1,
        'last_crawled_at' => now()->subHours(25),
        'last_attempted_at' => now()->subMinutes(30),
    ]);

    $queue = Site::query()->crawlQueue()->pluck('id');

    expect($queue)->not->toContain($site->id);
});

it('includes sites with 1 failure and old enough attempt in crawl queue', function () {
    $site = Site::factory()->create([
        'is_active' => true,
        'status' => 'pending',
        'consecutive_failures' => 1,
        'last_crawled_at' => now()->subHours(25),
        'last_attempted_at' => now()->subHours(2),
    ]);

    $queue = Site::query()->crawlQueue()->pluck('id');

    expect($queue)->toContain($site->id);
});

it('excludes sites with 4 failures and 48hr old attempt from crawl queue', function () {
    $site = Site::factory()->create([
        'is_active' => true,
        'status' => 'pending',
        'consecutive_failures' => 4,
        'last_crawled_at' => now()->subHours(25),
        'last_attempted_at' => now()->subHours(48),
    ]);

    // Needs 72hr backoff, 48hr is not enough
    $queue = Site::query()->crawlQueue()->pluck('id');

    expect($queue)->not->toContain($site->id);
});

it('includes sites with 4 failures after sufficient backoff in crawl queue', function () {
    $site = Site::factory()->create([
        'is_active' => true,
        'status' => 'pending',
        'consecutive_failures' => 4,
        'last_crawled_at' => now()->subHours(100),
        'last_attempted_at' => now()->subHours(73),
    ]);

    $queue = Site::query()->crawlQueue()->pluck('id');

    expect($queue)->toContain($site->id);
});

it('excludes sites at max consecutive failures from crawl queue', function () {
    $site = Site::factory()->create([
        'is_active' => true,
        'status' => 'pending',
        'consecutive_failures' => Site::MAX_CONSECUTIVE_FAILURES,
        'last_crawled_at' => now()->subDays(30),
        'last_attempted_at' => now()->subDays(30),
    ]);

    $queue = Site::query()->crawlQueue()->pluck('id');

    expect($queue)->not->toContain($site->id);
});

it('resets consecutive failures on successful crawl update', function () {
    $site = Site::factory()->create([
        'consecutive_failures' => 3,
    ]);

    $site->update(['consecutive_failures' => 0]);

    expect($site->fresh()->consecutive_failures)->toBe(0);
});
