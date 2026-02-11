<?php

use App\Jobs\CrawlSiteJob;
use App\Models\Site;
use Illuminate\Support\Facades\Queue;

it('includes sites with category "other" in needsBackfill scope', function () {
    $site = Site::factory()->create([
        'category' => 'other',
        'screenshot_path' => 'screenshots/test.png',
        'last_crawled_at' => now()->subDay(),
        'last_attempted_at' => now()->subDays(2),
        'status' => 'completed',
    ]);

    expect(Site::query()->needsBackfill()->pluck('id'))->toContain($site->id);
});

it('includes sites with null screenshot_path in needsBackfill scope', function () {
    $site = Site::factory()->create([
        'category' => 'ai-tools',
        'screenshot_path' => null,
        'last_crawled_at' => now()->subDay(),
        'last_attempted_at' => now()->subDays(2),
        'status' => 'completed',
    ]);

    expect(Site::query()->needsBackfill()->pluck('id'))->toContain($site->id);
});

it('excludes sites that have both category and screenshot from needsBackfill', function () {
    $site = Site::factory()->create([
        'category' => 'ai-tools',
        'screenshot_path' => 'screenshots/test.png',
        'last_crawled_at' => now()->subDay(),
        'status' => 'completed',
    ]);

    expect(Site::query()->needsBackfill()->pluck('id'))->not->toContain($site->id);
});

it('excludes never-crawled sites from needsBackfill', function () {
    $site = Site::factory()->create([
        'category' => 'other',
        'last_crawled_at' => null,
        'status' => 'pending',
    ]);

    expect(Site::query()->needsBackfill()->pluck('id'))->not->toContain($site->id);
});

it('excludes inactive sites from needsBackfill', function () {
    $site = Site::factory()->inactive()->create([
        'category' => 'other',
        'last_crawled_at' => now()->subDay(),
        'status' => 'completed',
    ]);

    expect(Site::query()->needsBackfill()->pluck('id'))->not->toContain($site->id);
});

it('excludes currently crawling sites from needsBackfill', function () {
    $site = Site::factory()->create([
        'category' => 'other',
        'last_crawled_at' => now()->subDay(),
        'last_attempted_at' => now()->subDays(2),
        'status' => 'crawling',
    ]);

    expect(Site::query()->needsBackfill()->pluck('id'))->not->toContain($site->id);
});

it('excludes recently attempted sites from needsBackfill', function () {
    $site = Site::factory()->create([
        'category' => 'other',
        'last_crawled_at' => now()->subDay(),
        'last_attempted_at' => now()->subHour(),
        'consecutive_failures' => 2,
        'status' => 'completed',
    ]);

    expect(Site::query()->needsBackfill()->pluck('id'))->not->toContain($site->id);
});

it('dispatches backfill job when normal queue is empty', function () {
    Queue::fake();

    // Create a site that's on cooldown but needs backfill, with old attempt
    Site::factory()->create([
        'category' => 'other',
        'last_crawled_at' => now()->subHour(),
        'last_attempted_at' => now()->subDays(2),
        'cooldown_hours' => 168,
        'status' => 'completed',
    ]);

    CrawlSiteJob::dispatchNext();

    Queue::assertPushed(CrawlSiteJob::class, fn (CrawlSiteJob $job) => $job->backfill === true);
});

it('prefers normal queue over backfill sites', function () {
    Queue::fake();

    // Backfill candidate
    Site::factory()->create([
        'category' => 'other',
        'last_crawled_at' => now()->subHour(),
        'last_attempted_at' => now()->subDays(2),
        'cooldown_hours' => 168,
        'status' => 'completed',
    ]);

    // Normal queue candidate (never crawled)
    $queued = Site::factory()->create([
        'category' => 'ai-tools',
        'last_crawled_at' => null,
        'last_attempted_at' => null,
        'status' => 'pending',
    ]);

    CrawlSiteJob::dispatchNext();

    Queue::assertPushed(CrawlSiteJob::class, fn (CrawlSiteJob $job) => $job->site->id === $queued->id && $job->backfill === false);
});
