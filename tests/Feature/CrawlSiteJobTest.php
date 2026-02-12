<?php

use App\Events\CrawlCompleted;
use App\Events\CrawlStarted;
use App\Jobs\CrawlSiteJob;
use App\Jobs\GenerateScreenshotJob;
use App\Models\Site;
use App\Services\HttpMetadataCollector;
use App\Services\HypeScoreCalculator;
use App\Services\IpGeolocationService;
use App\Services\ScreenshotService;
use App\Services\SiteCategoryDetector;
use App\Services\TechStackDetector;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;

it('dispatches GenerateScreenshotJob asynchronously instead of synchronously', function () {
    Queue::fake();
    Event::fake();

    $site = Site::factory()->pending()->create([
        'url' => 'https://example-screenshot.com',
        'domain' => 'example-screenshot.com',
        'category' => 'other',
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

    Queue::assertPushed(GenerateScreenshotJob::class, function ($job) use ($site) {
        return $job->site->id === $site->id;
    });
});

it('includes site_source in CrawlStarted event', function () {
    Queue::fake();
    Event::fake();

    $site = Site::factory()->pending()->create([
        'url' => 'https://example-source.com',
        'domain' => 'example-source.com',
        'category' => 'other',
        'source' => 'hackernews',
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

    Event::assertDispatched(CrawlStarted::class, function (CrawlStarted $event) {
        return $event->site_source === 'hackernews';
    });
});

it('includes latitude and longitude in CrawlCompleted event on success', function () {
    Queue::fake();
    Event::fake();

    $site = Site::factory()->pending()->create([
        'url' => 'https://example-geo.com',
        'domain' => 'example-geo.com',
        'category' => 'other',
    ]);

    $html = '<html><head><title>Test</title></head><body>Hello</body></html>';
    $mockProcess = Mockery::mock(Process::class);

    $screenshotService = Mockery::mock(ScreenshotService::class);
    $screenshotService->shouldReceive('startHtmlFetch')->once()->andReturn($mockProcess);
    $screenshotService->shouldReceive('collectHtmlResult')->once()->with($mockProcess)->andReturn($html);

    $httpMetadataCollector = Mockery::mock(HttpMetadataCollector::class);
    $httpMetadataCollector->shouldReceive('collect')->once()->andReturn([
        'server_ip' => '93.184.216.34',
        'headers' => [],
        'redirect_chain' => null,
        'final_url' => $site->url,
        'response_time_ms' => 100,
        'server_software' => null,
        'tls_issuer' => null,
    ]);

    $ipGeolocationService = Mockery::mock(IpGeolocationService::class);
    $ipGeolocationService->shouldReceive('geolocate')
        ->once()
        ->with('93.184.216.34')
        ->andReturn(['latitude' => 37.7749, 'longitude' => -122.4194]);

    $job = new CrawlSiteJob($site);
    $job->handle(
        app(HypeScoreCalculator::class),
        $screenshotService,
        app(SiteCategoryDetector::class),
        $httpMetadataCollector,
        app(TechStackDetector::class),
        $ipGeolocationService,
    );

    Event::assertDispatched(CrawlCompleted::class, function (CrawlCompleted $event) {
        return $event->latitude === 37.7749 && $event->longitude === -122.4194;
    });
});

it('sends null latitude and longitude in CrawlCompleted when crawl fails', function () {
    Queue::fake();
    Event::fake();

    $site = Site::factory()->pending()->create([
        'url' => 'https://example-fail.com',
        'domain' => 'example-fail.com',
        'category' => 'other',
    ]);

    $screenshotService = Mockery::mock(ScreenshotService::class);
    $screenshotService->shouldReceive('startHtmlFetch')->once()->andThrow(new RuntimeException('Connection refused'));

    $httpMetadataCollector = Mockery::mock(HttpMetadataCollector::class);
    $httpMetadataCollector->shouldReceive('collect')->once()->andReturn([
        'server_ip' => null,
        'headers' => [],
        'redirect_chain' => null,
        'final_url' => $site->url,
        'response_time_ms' => null,
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

    Event::assertDispatched(CrawlCompleted::class, function (CrawlCompleted $event) {
        return $event->latitude === null && $event->longitude === null && $event->has_error === true;
    });
});
