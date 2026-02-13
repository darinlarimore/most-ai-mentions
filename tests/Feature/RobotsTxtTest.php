<?php

use App\Enums\CrawlErrorCategory;
use App\Jobs\CrawlSiteJob;
use App\Models\CrawlError;
use App\Models\Site;
use App\Services\HttpMetadataCollector;
use App\Services\HypeScoreCalculator;
use App\Services\IpGeolocationService;
use App\Services\RobotsTxtChecker;
use App\Services\ScreenshotService;
use App\Services\SiteCategoryDetector;
use App\Services\TechStackDetector;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;

it('blocks crawl when robots.txt disallows root', function () {
    Queue::fake();
    Event::fake();

    $site = Site::factory()->pending()->create([
        'url' => 'https://example.com',
        'category' => 'other',
    ]);

    Http::fake([
        'example.com/robots.txt' => Http::response("User-agent: *\nDisallow: /", 200),
    ]);

    $screenshotService = Mockery::mock(ScreenshotService::class);
    $screenshotService->shouldNotReceive('startHtmlFetch');

    $httpMetadataCollector = Mockery::mock(HttpMetadataCollector::class);
    $httpMetadataCollector->shouldNotReceive('collect');

    $job = new CrawlSiteJob($site);
    $job->handle(
        app(HypeScoreCalculator::class),
        $screenshotService,
        app(SiteCategoryDetector::class),
        $httpMetadataCollector,
        app(TechStackDetector::class),
        app(IpGeolocationService::class),
    );

    $site->refresh();
    expect($site->consecutive_failures)->toBe(1);
    expect($site->status)->toBe('pending');

    expect(CrawlError::where('site_id', $site->id)->where('category', CrawlErrorCategory::RobotsBlocked)->exists())->toBeTrue();
});

it('allows crawl when robots.txt permits root', function () {
    Queue::fake();
    Event::fake();

    $site = Site::factory()->pending()->create([
        'url' => 'https://example.com',
        'category' => 'other',
    ]);

    Http::fake([
        'example.com/robots.txt' => Http::response("User-agent: *\nAllow: /", 200),
    ]);

    $html = '<html><head><title>Test</title></head><body>Hello</body></html>';
    $mockProcess = Mockery::mock(Process::class);

    $screenshotService = Mockery::mock(ScreenshotService::class);
    $screenshotService->shouldReceive('startHtmlFetch')->once()->andReturn($mockProcess);
    $screenshotService->shouldReceive('collectHtmlResult')->once()->with($mockProcess)->andReturn($html);

    $httpMetadataCollector = Mockery::mock(HttpMetadataCollector::class);
    $httpMetadataCollector->shouldReceive('collect')->once()->andReturn([
        'server_ip' => null,
        'headers' => [],
        'redirect_chain' => null,
        'final_url' => $site->url,
        'response_time_ms' => 100,
        'server_software' => null,
        'tls_issuer' => null,
    ]);

    $job = new CrawlSiteJob($site);
    $job->handle(
        app(HypeScoreCalculator::class),
        $screenshotService,
        app(SiteCategoryDetector::class),
        $httpMetadataCollector,
        app(TechStackDetector::class),
        app(IpGeolocationService::class),
    );

    $site->refresh();
    expect($site->consecutive_failures)->toBe(0);
    expect(CrawlError::where('site_id', $site->id)->where('category', CrawlErrorCategory::RobotsBlocked)->exists())->toBeFalse();
});

it('allows crawl when robots.txt fetch fails', function () {
    Queue::fake();
    Event::fake();

    $site = Site::factory()->pending()->create([
        'url' => 'https://example.com',
        'category' => 'other',
    ]);

    Http::fake([
        'example.com/robots.txt' => Http::response('Not Found', 404),
    ]);

    $html = '<html><head><title>Test</title></head><body>Hello</body></html>';
    $mockProcess = Mockery::mock(Process::class);

    $screenshotService = Mockery::mock(ScreenshotService::class);
    $screenshotService->shouldReceive('startHtmlFetch')->once()->andReturn($mockProcess);
    $screenshotService->shouldReceive('collectHtmlResult')->once()->with($mockProcess)->andReturn($html);

    $httpMetadataCollector = Mockery::mock(HttpMetadataCollector::class);
    $httpMetadataCollector->shouldReceive('collect')->once()->andReturn([
        'server_ip' => null,
        'headers' => [],
        'redirect_chain' => null,
        'final_url' => $site->url,
        'response_time_ms' => 100,
        'server_software' => null,
        'tls_issuer' => null,
    ]);

    $job = new CrawlSiteJob($site);
    $job->handle(
        app(HypeScoreCalculator::class),
        $screenshotService,
        app(SiteCategoryDetector::class),
        $httpMetadataCollector,
        app(TechStackDetector::class),
        app(IpGeolocationService::class),
    );

    $site->refresh();
    expect($site->consecutive_failures)->toBe(0);
});

it('allows crawl when specific user-agent is allowed despite wildcard disallow', function () {
    Queue::fake();
    Event::fake();

    $site = Site::factory()->pending()->create([
        'url' => 'https://example.com',
        'category' => 'other',
    ]);

    Http::fake([
        'example.com/robots.txt' => Http::response("User-agent: MostAIMentions\nAllow: /\n\nUser-agent: *\nDisallow: /", 200),
    ]);

    $html = '<html><head><title>Test</title></head><body>Hello</body></html>';
    $mockProcess = Mockery::mock(Process::class);

    $screenshotService = Mockery::mock(ScreenshotService::class);
    $screenshotService->shouldReceive('startHtmlFetch')->once()->andReturn($mockProcess);
    $screenshotService->shouldReceive('collectHtmlResult')->once()->with($mockProcess)->andReturn($html);

    $httpMetadataCollector = Mockery::mock(HttpMetadataCollector::class);
    $httpMetadataCollector->shouldReceive('collect')->once()->andReturn([
        'server_ip' => null,
        'headers' => [],
        'redirect_chain' => null,
        'final_url' => $site->url,
        'response_time_ms' => 100,
        'server_software' => null,
        'tls_issuer' => null,
    ]);

    $job = new CrawlSiteJob($site);
    $job->handle(
        app(HypeScoreCalculator::class),
        $screenshotService,
        app(SiteCategoryDetector::class),
        $httpMetadataCollector,
        app(TechStackDetector::class),
        app(IpGeolocationService::class),
    );

    $site->refresh();
    expect($site->consecutive_failures)->toBe(0);
    expect(CrawlError::where('site_id', $site->id)->where('category', CrawlErrorCategory::RobotsBlocked)->exists())->toBeFalse();
});

it('returns true from RobotsTxtChecker when robots.txt times out', function () {
    Http::fake([
        'example.com/robots.txt' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection timed out'),
    ]);

    $checker = new RobotsTxtChecker;
    expect($checker->isAllowed('https://example.com'))->toBeTrue();
});
