<?php

use App\Jobs\CrawlSiteJob;
use App\Jobs\DiscoverSitesJob;
use App\Models\Site;
use App\Services\SiteDiscoveryService;
use Illuminate\Support\Facades\Bus;

it('dispatches crawl job when queue has sites', function () {
    Bus::fake([CrawlSiteJob::class, DiscoverSitesJob::class]);

    Site::factory()->create([
        'status' => 'queued',
        'is_active' => true,
        'last_crawled_at' => null,
        'last_attempted_at' => null,
    ]);

    CrawlSiteJob::dispatchNext();

    Bus::assertDispatched(CrawlSiteJob::class);
    Bus::assertNotDispatched(DiscoverSitesJob::class);
});

it('does not dispatch discovery when crawl queue is empty', function () {
    Bus::fake([CrawlSiteJob::class, DiscoverSitesJob::class]);

    CrawlSiteJob::dispatchNext();

    Bus::assertNotDispatched(CrawlSiteJob::class);
    Bus::assertNotDispatched(DiscoverSitesJob::class);
});

it('calls discoverAll and always dispatches next crawl', function () {
    $mock = $this->mock(SiteDiscoveryService::class);
    $mock->shouldReceive('discoverAll')->once()->andReturn(5);

    Bus::fake([CrawlSiteJob::class]);

    Site::factory()->create([
        'status' => 'queued',
        'is_active' => true,
        'last_crawled_at' => null,
        'last_attempted_at' => null,
    ]);

    (new DiscoverSitesJob)->handle($mock);

    Bus::assertDispatched(CrawlSiteJob::class);
});

it('dispatches next crawl even when no new sites discovered', function () {
    $mock = $this->mock(SiteDiscoveryService::class);
    $mock->shouldReceive('discoverAll')->once()->andReturn(0);

    Bus::fake([CrawlSiteJob::class]);

    // Create a site past cooldown to be re-crawled
    Site::factory()->create([
        'status' => 'completed',
        'is_active' => true,
        'last_crawled_at' => now()->subDays(8),
        'last_attempted_at' => now()->subDays(8),
    ]);

    (new DiscoverSitesJob)->handle($mock);

    Bus::assertDispatched(CrawlSiteJob::class);
});
