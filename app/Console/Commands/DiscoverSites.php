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
            'G2 (AI)' => fn () => $service->discoverFromG2()->count(),
            'G2 (broad)' => fn () => $service->discoverFromG2Broad()->count(),
            'ProductHunt' => fn () => $service->discoverFromProductHunt()->count(),
            'Hacker News' => fn () => $service->discoverFromHackerNews()->count(),
            'Downdetector' => fn () => $service->discoverFromDowndetector()->count(),
            'Tranco Top Sites' => fn () => $service->discoverFromTrancoList()->count(),
            'Awwwards' => fn () => $service->discoverFromAwwwards()->count(),
            'Capterra' => fn () => $service->discoverFromCapterra()->count(),
            'AlternativeTo' => fn () => $service->discoverFromAlternativeTo()->count(),
            'BuiltWith' => fn () => $service->discoverFromBuiltWith()->count(),
            'SimilarWeb' => fn () => $service->discoverFromSimilarWeb()->count(),
            'StackShare' => fn () => $service->discoverFromStackShare()->count(),
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
