<?php

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
