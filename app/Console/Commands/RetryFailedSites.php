<?php

namespace App\Console\Commands;

use App\Models\Site;
use Illuminate\Console\Command;

class RetryFailedSites extends Command
{
    protected $signature = 'app:retry-failed-sites';

    protected $description = 'Reset attempt cooldown on failed sites so they re-enter the crawl queue';

    public function handle(): int
    {
        $sites = Site::active()
            ->where('status', 'pending')
            ->whereNull('last_crawled_at')
            ->whereNotNull('last_attempted_at')
            ->get();

        if ($sites->isEmpty()) {
            $this->info('No failed sites to retry.');

            return self::SUCCESS;
        }

        $this->info("Found {$sites->count()} site(s) that failed their initial crawl.");

        $sites->each(fn (Site $site) => $site->update(['last_attempted_at' => null]));

        $this->info("Reset {$sites->count()} site(s) — they will appear in the crawl queue.");

        $queueCount = Site::query()->crawlQueue()->count();
        $this->info("Crawl queue now has {$queueCount} site(s).");

        return self::SUCCESS;
    }
}
