<?php

use App\Events\CrawlCompleted;
use App\Events\CrawlStarted;
use App\Jobs\CrawlSiteJob;
use App\Models\Site;
use App\Services\AxeAuditService;
use App\Services\HttpMetadataCollector;
use App\Services\HypeScoreCalculator;
use App\Services\IpGeolocationService;
use App\Services\ScreenshotService;
use App\Services\SiteCategoryDetector;
use App\Services\TechStackDetector;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;

it('generates screenshot inline during crawl', function () {
    Queue::fake();
    Event::fake();

    $site = Site::factory()->pending()->create([
        'category' => 'other',
    ]);

    $html = '<html><head><title>Test</title></head><body>Hello</body></html>';
    $mockProcess = Mockery::mock(Process::class);

    $screenshotService = Mockery::mock(ScreenshotService::class);
    $screenshotService->shouldReceive('startHtmlFetch')->once()->andReturn($mockProcess);
    $screenshotService->shouldReceive('collectHtmlResult')->once()->with($mockProcess)->andReturn($html);
    $screenshotService->shouldReceive('capture')->once()->andReturn('screenshots/test.png');

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
        app(AxeAuditService::class),
    );

    $site->refresh();
    expect($site->getRawOriginal('screenshot_path'))->toBe('screenshots/test.png');
});

it('includes site_source in CrawlStarted event', function () {
    Queue::fake();
    Event::fake();

    $site = Site::factory()->pending()->create([
        'category' => 'other',
        'source' => 'hackernews',
    ]);

    $html = '<html><head><title>Test</title></head><body>Hello</body></html>';
    $mockProcess = Mockery::mock(Process::class);

    $screenshotService = Mockery::mock(ScreenshotService::class);
    $screenshotService->shouldReceive('startHtmlFetch')->once()->andReturn($mockProcess);
    $screenshotService->shouldReceive('collectHtmlResult')->once()->with($mockProcess)->andReturn($html);
    $screenshotService->shouldReceive('capture')->once()->andReturn('screenshots/test.png');

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
        app(AxeAuditService::class),
    );

    Event::assertDispatched(CrawlStarted::class, function (CrawlStarted $event) {
        return $event->site_source === 'hackernews';
    });
});

it('includes latitude and longitude in CrawlCompleted event on success', function () {
    Queue::fake();
    Event::fake();

    $site = Site::factory()->pending()->create([
        'category' => 'other',
    ]);

    $html = '<html><head><title>Test</title></head><body>Hello</body></html>';
    $mockProcess = Mockery::mock(Process::class);

    $screenshotService = Mockery::mock(ScreenshotService::class);
    $screenshotService->shouldReceive('startHtmlFetch')->once()->andReturn($mockProcess);
    $screenshotService->shouldReceive('collectHtmlResult')->once()->with($mockProcess)->andReturn($html);
    $screenshotService->shouldReceive('capture')->once()->andReturn('screenshots/test.png');

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
        app(AxeAuditService::class),
    );

    Event::assertDispatched(CrawlCompleted::class, function (CrawlCompleted $event) {
        return $event->latitude === 37.7749 && $event->longitude === -122.4194;
    });
});

it('sends null latitude and longitude in CrawlCompleted when crawl fails', function () {
    Queue::fake();
    Event::fake();

    $site = Site::factory()->pending()->create([
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
        app(AxeAuditService::class),
    );

    Event::assertDispatched(CrawlCompleted::class, function (CrawlCompleted $event) {
        return $event->latitude === null && $event->longitude === null && $event->has_error === true;
    });
});

it('bails early on fatal HTTP metadata errors without launching Chrome', function () {
    Queue::fake();
    Event::fake();

    $site = Site::factory()->pending()->create([
        'category' => 'other',
        'consecutive_failures' => 0,
    ]);

    $screenshotService = Mockery::mock(ScreenshotService::class);
    // Chrome should NEVER be called for a fatal connection error
    $screenshotService->shouldNotReceive('startHtmlFetch');
    $screenshotService->shouldNotReceive('collectHtmlResult');

    $httpMetadataCollector = Mockery::mock(HttpMetadataCollector::class);
    $httpMetadataCollector->shouldReceive('collect')->once()
        ->andThrow(new RuntimeException('cURL error 60: SSL: no alternative certificate subject name matches target host name', 60));

    $job = new CrawlSiteJob($site);
    $job->handle(
        app(HypeScoreCalculator::class),
        $screenshotService,
        app(SiteCategoryDetector::class),
        $httpMetadataCollector,
        app(TechStackDetector::class),
        app(IpGeolocationService::class),
        app(AxeAuditService::class),
    );

    // Should record the error and bail
    Event::assertDispatched(CrawlCompleted::class, function (CrawlCompleted $event) {
        return $event->has_error === true && $event->error_category === 'SSL Error';
    });

    $site->refresh();
    expect($site->consecutive_failures)->toBe(1);
    expect($site->status)->toBe('pending');

    // Should have created a crawl error
    expect($site->crawlErrors()->count())->toBe(1);
    expect($site->crawlErrors()->first()->category)->toBe(\App\Enums\CrawlErrorCategory::SslError);
});

it('continues crawl when HTTP metadata fails with non-fatal error', function () {
    Queue::fake();
    Event::fake();

    $site = Site::factory()->pending()->create([
        'category' => 'other',
    ]);

    $html = '<html><head><title>Test</title></head><body>Hello</body></html>';
    $mockProcess = Mockery::mock(Process::class);

    $screenshotService = Mockery::mock(ScreenshotService::class);
    // Chrome SHOULD still be called for non-fatal errors (e.g. HTTP 403)
    $screenshotService->shouldReceive('startHtmlFetch')->once()->andReturn($mockProcess);
    $screenshotService->shouldReceive('collectHtmlResult')->once()->with($mockProcess)->andReturn($html);
    $screenshotService->shouldReceive('capture')->once()->andReturn('screenshots/test.png');

    $httpMetadataCollector = Mockery::mock(HttpMetadataCollector::class);
    $httpMetadataCollector->shouldReceive('collect')->once()
        ->andThrow(new RuntimeException('403 Forbidden'));

    $job = new CrawlSiteJob($site);
    $job->handle(
        app(HypeScoreCalculator::class),
        $screenshotService,
        app(SiteCategoryDetector::class),
        $httpMetadataCollector,
        app(TechStackDetector::class),
        app(IpGeolocationService::class),
        app(AxeAuditService::class),
    );

    // Should still complete successfully since Chrome got HTML
    Event::assertDispatched(CrawlCompleted::class, function (CrawlCompleted $event) {
        return $event->hype_score >= 0;
    });
});
