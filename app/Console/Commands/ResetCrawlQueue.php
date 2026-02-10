<?php

namespace App\Console\Commands;

use App\Models\Site;
use Illuminate\Console\Command;

class ResetCrawlQueue extends Command
{
    protected $signature = 'app:reset-crawl-queue';

    protected $description = 'Reset all sites so they are eligible to be recrawled';

    public function handle(): int
    {
        if (! $this->confirm('This will reset all sites to be recrawlable. Continue?')) {
            return self::SUCCESS;
        }

        $count = Site::query()->active()->update([
            'last_crawled_at' => null,
            'last_attempted_at' => null,
            'status' => 'pending',
        ]);

        $this->info("Reset {$count} site(s). All are now eligible for crawling.");

        return self::SUCCESS;
    }
}
