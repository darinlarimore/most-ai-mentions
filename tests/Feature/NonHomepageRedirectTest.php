<?php

use App\Enums\CrawlErrorCategory;
use App\Jobs\CrawlSiteJob;
use App\Models\CrawlError;
use App\Models\CrawlResult;
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

it('treats non-homepage redirect as a failure and increments consecutive_failures', function () {
    Queue::fake();
    Event::fake();

    $site = Site::factory()->pending()->create([
        'category' => 'other',
        'consecutive_failures' => 0,
    ]);

    $mockProcess = Mockery::mock(Process::class);
    $mockProcess->shouldReceive('stop')->once();

    $screenshotService = Mockery::mock(ScreenshotService::class);
    $screenshotService->shouldReceive('startHtmlFetch')->once()->andReturn($mockProcess);
    $screenshotService->shouldNotReceive('collectHtmlResult');

    $httpMetadataCollector = Mockery::mock(HttpMetadataCollector::class);
    $httpMetadataCollector->shouldReceive('collect')->once()->andReturn([
        'server_ip' => null,
        'headers' => [],
        'redirect_chain' => [['url' => $site->url, 'status' => 302]],
        'final_url' => 'https://accounts.google.com/oauth/login',
        'response_time_ms' => 200,
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

    expect($site->consecutive_failures)->toBe(1)
        ->and($site->status)->toBe('pending')
        ->and($site->is_active)->toBeTrue();

    $error = CrawlError::where('site_id', $site->id)->first();
    expect($error)->not->toBeNull()
        ->and($error->category)->toBe(CrawlErrorCategory::RedirectToNonHomepage)
        ->and($error->message)->toContain('accounts.google.com');

    $crawlResult = CrawlResult::where('site_id', $site->id)->first();
    expect($crawlResult)->not->toBeNull()
        ->and($crawlResult->final_url)->toBe('https://accounts.google.com/oauth/login')
        ->and($crawlResult->ai_mention_count)->toBe(0);
});

it('does not flag same-host homepage redirect as non-homepage', function () {
    Queue::fake();
    Event::fake();

    $site = Site::factory()->pending()->create([
        'url' => 'https://example.com',
        'category' => 'other',
        'consecutive_failures' => 0,
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
        'redirect_chain' => [['url' => 'https://example.com', 'status' => 301]],
        'final_url' => 'https://www.example.com/',
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

    // Should proceed normally, not be treated as a failure
    expect($site->consecutive_failures)->toBe(0)
        ->and($site->status)->toBe('completed');

    expect(CrawlError::where('site_id', $site->id)
        ->where('category', CrawlErrorCategory::RedirectToNonHomepage)
        ->exists())->toBeFalse();
});
