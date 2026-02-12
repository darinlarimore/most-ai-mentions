<?php

namespace App\Console\Commands;

use App\Jobs\CheckNonHomepageRedirectJob;
use App\Models\Site;
use Illuminate\Console\Command;

class CheckNonHomepageRedirects extends Command
{
    protected $signature = 'app:check-non-homepage-redirects';

    protected $description = 'Dispatch jobs to check all active sites for non-homepage redirects';

    public function handle(): int
    {
        $count = Site::query()->where('is_active', true)->count();

        if ($count === 0) {
            $this->info('No active sites to check.');

            return self::SUCCESS;
        }

        Site::query()
            ->where('is_active', true)
            ->chunkById(100, function ($sites) {
                foreach ($sites as $site) {
                    CheckNonHomepageRedirectJob::dispatch($site);
                }
            });

        $this->info("Dispatched {$count} redirect check job(s) to the queue.");

        return self::SUCCESS;
    }
}
