<?php

namespace App\Console\Commands;

use App\Services\SiteDiscoveryService;
use Illuminate\Console\Command;

class DiscoverSites extends Command
{
    protected $signature = 'app:discover-sites';

    protected $description = 'Discover new sites from curated lists, directories, and ranking sites';

    public function handle(SiteDiscoveryService $service): int
    {
        $this->info('Discovering new sites...');

        $sources = [
            'Popular AI sites' => fn () => $service->discoverPopular()->count(),
            'Hacker News (API)' => fn () => $service->discoverFromHackerNews()->count(),
            'Tranco Top Sites' => fn () => $service->discoverFromTrancoList()->count(),
            'Reddit' => fn () => $service->discoverFromReddit()->count(),
            'AlternativeTo' => fn () => $service->discoverFromAlternativeTo()->count(),
            'New Domains' => fn () => $service->discoverFromNewDomains()->count(),
        ];

        $total = 0;

        foreach ($sources as $name => $discover) {
            $count = $discover();
            $total += $count;
            $this->line("  {$name}: {$count} new site(s)");
        }

        $this->info("Done. {$total} new site(s) added.");

        return self::SUCCESS;
    }
}
