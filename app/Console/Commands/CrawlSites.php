<?php

namespace App\Console\Commands;

use App\Jobs\CrawlSiteJob;
use App\Models\Site;
use Illuminate\Console\Command;

class CrawlSites extends Command
{
    protected $signature = 'app:crawl-sites {--limit=10}';

    protected $description = 'Dispatch crawl jobs for sites that are ready to be crawled';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $sites = Site::query()
            ->active()
            ->readyToCrawl()
            ->where('status', '!=', 'crawling')
            ->orderByRaw('submitted_by IS NOT NULL DESC')
            ->orderByRaw('last_crawled_at IS NULL DESC')
            ->orderBy('last_crawled_at')
            ->limit($limit)
            ->get();

        if ($sites->isEmpty()) {
            $this->info('No sites are ready to crawl at this time.');

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
