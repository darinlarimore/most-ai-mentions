<?php

namespace App\Console\Commands;

use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeSites extends Command
{
    protected $signature = 'app:purge-sites
        {--force : Skip confirmation prompt}';

    protected $description = 'Delete all sites and their related data (crawl results, score histories, ratings, screenshots)';

    public function handle(): int
    {
        $siteCount = Site::count();

        if ($siteCount === 0) {
            $this->info('No sites to purge.');

            return self::SUCCESS;
        }

        $this->warn("This will delete {$siteCount} site(s) and all related crawl results, score histories, and ratings.");

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to continue?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        // Clean up screenshot files from disk
        $screenshotFiles = Storage::disk('public')->files('screenshots');
        if (count($screenshotFiles) > 0) {
            Storage::disk('public')->delete($screenshotFiles);
            $this->info('Deleted '.count($screenshotFiles).' screenshot file(s).');
        }

        // Delete all sites — cascades to crawl_results, score_histories, ratings
        Site::query()->delete();

        $this->info("Purged {$siteCount} site(s) and all related data.");

        return self::SUCCESS;
    }
}
