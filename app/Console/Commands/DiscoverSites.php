<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\SiteDiscoveryService;
use Illuminate\Console\Command;

class DiscoverSites extends Command
{
    protected $signature = 'app:discover-sites';

    protected $description = 'Discover new sites from curated lists, directories, and ranking sites';

    public function handle(SiteDiscoveryService $service): int
    {
        $this->info('Discovering new sites...');
        $this->newLine();

        $sources = [
            'Popular AI sites' => fn () => $service->discoverPopular(),
            'Hacker News (API)' => fn () => $service->discoverFromHackerNews(),
            'Tranco Top Sites' => fn () => $service->discoverFromTrancoList(),
            'HN Algolia Search' => fn () => $service->discoverFromHackerNewsSearch(),
            'GitHub Repos' => fn () => $service->discoverFromGitHub(),
            'Dev.to Articles' => fn () => $service->discoverFromDevTo(),
            'Reddit' => fn () => $service->discoverFromReddit(),
            'Lobste.rs' => fn () => $service->discoverFromLobsters(),
            'Wikipedia Links' => fn () => $service->discoverFromWikipedia(),
            'Lemmy' => fn () => $service->discoverFromLemmy(),
            'Mastodon' => fn () => $service->discoverFromMastodon(),
            'Show HN' => fn () => $service->discoverFromShowHN(),
            'Wikidata' => fn () => $service->discoverFromWikidata(),
            'CommonCrawl' => fn () => $service->discoverFromCommonCrawl(),
            'Stack Exchange' => fn () => $service->discoverFromStackExchange(),
            'Reverse IP' => fn () => $service->discoverFromReverseIp(),
        ];

        $total = 0;
        $allDiscovered = collect();

        foreach ($sources as $name => $discover) {
            $this->components->task($name, function () use ($discover, &$total, &$allDiscovered, $name) {
                $sites = $discover();
                $count = $sites->count();
                $total += $count;

                $sites->each(fn (Site $site) => $allDiscovered->push([
                    'source' => $name,
                    'domain' => $site->domain,
                    'name' => $site->name,
                ]));

                return $count > 0 ? "{$count} new" : 'no new sites';
            });
        }

        $this->newLine();

        $existingCount = Site::count();
        $this->components->bulletList([
            "Total sites in database: {$existingCount}",
            "New sites added: {$total}",
        ]);

        if ($allDiscovered->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['Source', 'Domain', 'Name'],
                $allDiscovered->toArray(),
            );
        }

        return self::SUCCESS;
    }
}
