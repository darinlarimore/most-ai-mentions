<?php

namespace App\Jobs;

use App\Jobs\Middleware\CheckQueuePaused;
use App\Services\SiteDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DiscoverSitesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Throttle discovery to once per hour via unique lock. */
    public int $uniqueFor = 3600;

    public int $timeout = 120;

    public int $maxExceptions = 2;

    public function middleware(): array
    {
        return [new CheckQueuePaused];
    }

    public function handle(SiteDiscoveryService $service): void
    {
        Log::info('DiscoverSitesJob: Starting auto-discovery (crawl queue was empty)');

        $count = $service->discoverAll();

        Log::info("DiscoverSitesJob: Discovered {$count} new site(s)");

        // Always try to crawl next — even if no new sites were discovered,
        // existing sites past their cooldown should be re-crawled.
        CrawlSiteJob::dispatchNext();
    }
}
