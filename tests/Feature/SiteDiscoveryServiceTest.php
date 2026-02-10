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

it('discovers sites from downdetector html', function () {
    Http::fake([
        'downdetector.com/*' => Http::response('
            <html><body>
                <a href="/status/netflix/">Netflix</a>
                <a href="/status/spotify/">Spotify</a>
                <a href="/status/steam/">Steam</a>
                <a href="/some-other-link">Not a status link</a>
            </body></html>
        '),
        '*' => Http::response('', 404),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromDowndetector();

    expect($sites->count())->toBeGreaterThanOrEqual(1);
    expect($sites->pluck('source')->unique()->toArray())->toBe(['downdetector']);
});

it('discovers sites from g2 broad categories', function () {
    Http::fake([
        'www.g2.com/categories/*' => Http::response('
            <html><body>
                <a href="/products/slack-reviews" class="product-link">Slack</a>
                <a href="/products/asana-reviews" class="product-link">Asana</a>
                <a class="website" href="https://basecamp.example.com">Visit Website</a>
            </body></html>
        '),
        '*' => Http::response('', 404),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromG2Broad();

    expect($sites->count())->toBeGreaterThanOrEqual(1);
    expect($sites->pluck('source')->unique()->toArray())->toBe(['g2-broad']);
});

it('discovers sites from tranco csv zip', function () {
    // Create a minimal CSV and zip it
    $csv = "1,example-tranco-one.com\n2,example-tranco-two.com\n3,google.com\n";
    $tmpZip = tempnam(sys_get_temp_dir(), 'test_tranco_');
    $zip = new ZipArchive;
    $zip->open($tmpZip, ZipArchive::CREATE);
    $zip->addFromString('top-1m.csv', $csv);
    $zip->close();
    $zipContent = file_get_contents($tmpZip);
    unlink($tmpZip);

    Http::fake([
        'tranco-list.eu/*' => Http::response($zipContent),
        '*' => Http::response('', 404),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromTrancoList(10);

    // google.com is excluded, so we should get 2
    expect($sites)->toHaveCount(2);
    expect($sites->pluck('domain')->toArray())->toContain('example-tranco-one.com');
    expect($sites->pluck('domain')->toArray())->toContain('example-tranco-two.com');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['tranco']);
});

it('discovers sites from awwwards html', function () {
    Http::fake([
        'www.awwwards.com/*' => Http::response('
            <html><body>
                <a href="https://cool-agency.example.com">Cool Agency</a>
                <a href="https://design-studio.example.com">Design Studio</a>
                <a href="/internal-link">Internal</a>
            </body></html>
        '),
        '*' => Http::response('', 404),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromAwwwards();

    expect($sites->count())->toBeGreaterThanOrEqual(1);
    expect($sites->pluck('source')->unique()->toArray())->toBe(['awwwards']);
});

it('discovers sites from capterra html', function () {
    Http::fake([
        'www.capterra.com/*' => Http::response('
            <html><body>
                <a class="visit-website" href="https://projecttool.example.com">Visit Website</a>
                <a href="/software/123/coolapp">CoolApp</a>
            </body></html>
        '),
        '*' => Http::response('', 404),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromCapterra();

    expect($sites->count())->toBeGreaterThanOrEqual(1);
    expect($sites->pluck('source')->unique()->toArray())->toBe(['capterra']);
});

it('discovers sites from alternativeto html', function () {
    Http::fake([
        'alternativeto.net/*' => Http::response('
            <html><body>
                <a href="https://myapp.example.com">MyApp</a>
                <a href="https://alternativeto.net/software/something/">Internal</a>
            </body></html>
        '),
        '*' => Http::response('', 404),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromAlternativeTo();

    expect($sites->count())->toBeGreaterThanOrEqual(1);
    expect($sites->pluck('source')->unique()->toArray())->toBe(['alternativeto']);
});

it('discovers sites from similarweb html', function () {
    Http::fake([
        'www.similarweb.com/*' => Http::response('
            <html><body>
                <a href="/website/coolsite.example.com/">CoolSite</a>
                <a href="/website/another.example.com/">Another</a>
            </body></html>
        '),
        '*' => Http::response('', 404),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromSimilarWeb();

    expect($sites->count())->toBeGreaterThanOrEqual(1);
    expect($sites->pluck('source')->unique()->toArray())->toBe(['similarweb']);
});

it('discovers sites from stackshare html', function () {
    Http::fake([
        'stackshare.io/*' => Http::response('
            <html><body>
                <a href="https://devtool.example.com">DevTool</a>
                <a href="https://stackshare.io/some-page">Internal</a>
            </body></html>
        '),
        '*' => Http::response('', 404),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromStackShare();

    expect($sites->count())->toBeGreaterThanOrEqual(1);
    expect($sites->pluck('source')->unique()->toArray())->toBe(['stackshare']);
});

it('discovers sites from builtwith html', function () {
    Http::fake([
        'builtwith.com/*' => Http::response('
            <html><body>
                <a href="https://bigsite.example.com">BigSite</a>
                <a href="https://builtwith.com/internal">Internal</a>
            </body></html>
        '),
        '*' => Http::response('', 404),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromBuiltWith();

    expect($sites->count())->toBeGreaterThanOrEqual(1);
    expect($sites->pluck('source')->unique()->toArray())->toBe(['builtwith']);
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
