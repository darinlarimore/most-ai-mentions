<?php

use App\Models\Site;
use App\Services\SiteDiscoveryService;
use Illuminate\Support\Facades\Http;

it('discovers sites from hacker news html', function () {
    Http::fake([
        'news.ycombinator.com/*' => Http::response('
            <html><body>
                <span class="titleline"><a href="https://coolai.example.com/post">Cool AI Startup launches GPT tool</a></span>
                <span class="titleline"><a href="https://unrelated.example.com/news">Sports News Today</a></span>
                <span class="titleline"><a href="https://another-ai.example.com">New Machine Learning Framework</a></span>
            </body></html>
        '),
        '*' => Http::response('', 404),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromHackerNews();

    expect($sites)->toHaveCount(2);
    expect($sites->pluck('domain')->toArray())->toContain('coolai.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('another-ai.example.com');
    expect($sites->pluck('domain')->toArray())->not->toContain('unrelated.example.com');
});

it('skips duplicate domains', function () {
    Site::factory()->create(['domain' => 'coolai.example.com']);

    Http::fake([
        'news.ycombinator.com/*' => Http::response('
            <html><body>
                <span class="titleline"><a href="https://coolai.example.com">AI Tool</a></span>
                <span class="titleline"><a href="https://newai.example.com">New AI thing</a></span>
            </body></html>
        '),
        '*' => Http::response('', 404),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromHackerNews();

    expect($sites)->toHaveCount(1);
    expect($sites->first()->domain)->toBe('newai.example.com');
});

it('skips excluded domains', function () {
    Http::fake([
        'news.ycombinator.com/*' => Http::response('
            <html><body>
                <span class="titleline"><a href="https://github.com/ai-project">AI project on GitHub</a></span>
                <span class="titleline"><a href="https://youtube.com/ai-video">AI video on YouTube</a></span>
            </body></html>
        '),
        '*' => Http::response('', 404),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromHackerNews();

    expect($sites)->toHaveCount(0);
});

it('normalizes urls to homepage', function () {
    $service = new SiteDiscoveryService;

    expect($service->normalizeUrl('https://www.example.com/some/path'))->toBe('https://example.com');
    expect($service->normalizeUrl('http://example.com/page'))->toBe('https://example.com');
    expect($service->normalizeUrl('example.com'))->toBe('https://example.com');
    expect($service->normalizeUrl(''))->toBeNull();
});

it('sets source and status correctly on discovered sites', function () {
    Http::fake([
        'news.ycombinator.com/*' => Http::response('
            <html><body>
                <span class="titleline"><a href="https://newai.example.com">New AI tool</a></span>
            </body></html>
        '),
        '*' => Http::response('', 404),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromHackerNews();

    expect($sites->first()->source)->toBe('hackernews');
    expect($sites->first()->status)->toBe('queued');
});
