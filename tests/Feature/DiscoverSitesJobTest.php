<?php

use App\Jobs\CrawlSiteJob;
use App\Jobs\DiscoverSitesJob;
use App\Models\Site;
use App\Services\SiteDiscoveryService;
use Illuminate\Support\Facades\Bus;

it('dispatches discovery job when crawl queue is empty', function () {
    Bus::fake([DiscoverSitesJob::class]);

    CrawlSiteJob::dispatchNext();

    Bus::assertDispatched(DiscoverSitesJob::class);
});

it('dispatches crawl job instead of discovery when queue has sites', function () {
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

it('calls discoverAll and resumes crawl chain when sites found', function () {
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

it('does not resume crawl chain when no new sites discovered', function () {
    $mock = $this->mock(SiteDiscoveryService::class);
    $mock->shouldReceive('discoverAll')->once()->andReturn(0);

    Bus::fake([CrawlSiteJob::class]);

    (new DiscoverSitesJob)->handle($mock);

    Bus::assertNotDispatched(CrawlSiteJob::class);
});
