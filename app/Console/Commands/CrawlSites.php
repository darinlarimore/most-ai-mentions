<?php

namespace App\Console\Commands;

use App\Jobs\CrawlSiteJob;
use App\Jobs\DiscoverSitesJob;
use App\Models\Site;
use Illuminate\Console\Command;

class CrawlSites extends Command
{
    protected $signature = 'app:crawl-sites {--limit=10}';

    protected $description = 'Dispatch crawl jobs for sites that are ready to be crawled';

    public function handle(): int
    {
        // Skip if a crawl is already in progress — the chain will continue on its own
        if (Site::where('status', 'crawling')->exists()) {
            $this->info('A crawl is already in progress. Skipping.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');

        $sites = Site::query()
            ->crawlQueue()
            ->limit($limit)
            ->get();

        if ($sites->isEmpty()) {
            // Backfill: re-crawl sites missing category or screenshot during downtime
            $backfillSites = Site::query()
                ->needsBackfill()
                ->limit($limit)
                ->get();

            if ($backfillSites->isNotEmpty()) {
                $this->info("Backfilling {$backfillSites->count()} site(s) missing data...");

                foreach ($backfillSites as $site) {
                    CrawlSiteJob::dispatch($site, backfill: true);
                    $this->line("  Queued (backfill): {$site->name} ({$site->url})");
                }

                return self::SUCCESS;
            }

            $this->info('No sites are ready to crawl. Dispatching discovery...');
            DiscoverSitesJob::dispatch();

            return self::SUCCESS;
        }

        $this->info("Dispatching crawl jobs for {$sites->count()} site(s)...");

        foreach ($sites as $site) {
            CrawlSiteJob::dispatch($site);
            $this->line("  Queued: {$site->name} ({$site->url})");
        }

        $this->info('All crawl jobs have been dispatched.');

        return self::SUCCESS;
    }
}
