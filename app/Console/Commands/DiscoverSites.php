<?php

namespace App\Console\Commands;

use App\Services\SiteDiscoveryService;
use Illuminate\Console\Command;

class DiscoverSites extends Command
{
    protected $signature = 'app:discover-sites';

    protected $description = 'Discover new AI-related sites from curated list, G2, ProductHunt, and Hacker News';

    public function handle(SiteDiscoveryService $service): int
    {
        $this->info('Discovering new sites...');

        $popularCount = $service->discoverPopular()->count();
        $this->line("  Popular AI sites: {$popularCount} new site(s)");

        $g2Count = $service->discoverFromG2()->count();
        $this->line("  G2: {$g2Count} new site(s)");

        $phCount = $service->discoverFromProductHunt()->count();
        $this->line("  ProductHunt: {$phCount} new site(s)");

        $hnCount = $service->discoverFromHackerNews()->count();
        $this->line("  Hacker News: {$hnCount} new site(s)");

        $total = $popularCount + $g2Count + $phCount + $hnCount;
        $this->info("Done. {$total} new site(s) added.");

        return self::SUCCESS;
    }
}
