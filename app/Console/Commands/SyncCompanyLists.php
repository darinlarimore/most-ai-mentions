<?php

namespace App\Console\Commands;

use App\Models\CompanyList;
use App\Models\CompanyListEntry;
use App\Models\Site;
use App\Services\CompanyListSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCompanyLists extends Command
{
    protected $signature = 'app:sync-company-lists
        {--list= : Sync a specific list by slug (e.g. y-combinator, fortune-500)}
        {--dry-run : Show what would change without writing to the database}';

    protected $description = 'Sync company lists from external APIs';

    /**
     * Maps list slugs to their fetch method on CompanyListSyncService.
     *
     * @var array<string, string>
     */
    private const FETCH_METHODS = [
        'y-combinator' => 'fetchYCombinator',
        'forbes-global-2000' => 'fetchForbesGlobal2000',
    ];

    public function handle(CompanyListSyncService $service): int
    {
        $dryRun = $this->option('dry-run');
        $listFilter = $this->option('list');
        $hasFailure = false;
        $summary = [];

        if ($dryRun) {
            $this->components->warn('Dry run mode — no changes will be written.');
        }

        $slugs = $listFilter ? [$listFilter] : array_keys(self::FETCH_METHODS);

        foreach ($slugs as $slug) {
            if (! isset(self::FETCH_METHODS[$slug])) {
                $this->components->error("Unknown list: {$slug}");
                $hasFailure = true;

                continue;
            }

            $list = CompanyList::where('slug', $slug)->first();

            if (! $list) {
                $this->components->error("Company list '{$slug}' not found in database. Run the seeder first.");
                $hasFailure = true;

                continue;
            }

            $method = self::FETCH_METHODS[$slug];

            try {
                $this->components->task("Fetching {$list->name}", function () use ($service, $method, $list, $dryRun, &$summary) {
                    $entries = $service->{$method}();

                    if ($dryRun) {
                        $summary[] = [
                            'list' => $list->name,
                            'entries' => count($entries),
                            'new_sites' => $this->countNewSites($entries),
                            'status' => 'dry-run',
                        ];

                        return;
                    }

                    $synced = $this->syncEntries($list, $entries);
                    $newSites = $this->createNewSites($list);

                    $summary[] = [
                        'list' => $list->name,
                        'entries' => $synced,
                        'new_sites' => $newSites,
                        'status' => 'ok',
                    ];

                    Log::info("Company list synced: {$list->name}", [
                        'slug' => $list->slug,
                        'entries' => $synced,
                        'new_sites' => $newSites,
                    ]);
                });
            } catch (\Throwable $e) {
                $hasFailure = true;

                $summary[] = [
                    'list' => $list->name,
                    'entries' => 0,
                    'new_sites' => 0,
                    'status' => 'FAILED',
                ];

                Log::critical("Company list sync failed: {$slug}", [
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]);

                $this->components->error("{$list->name}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->table(
            ['List', 'Entries', 'New Sites', 'Status'],
            collect($summary)->map(fn (array $row) => [
                $row['list'],
                $row['entries'],
                $row['new_sites'],
                $row['status'],
            ])->toArray(),
        );

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Upsert entries and remove stale ones. Returns the number of synced entries.
     */
    private function syncEntries(CompanyList $list, array $entries): int
    {
        $syncedDomains = [];

        foreach ($entries as $entry) {
            CompanyListEntry::updateOrCreate(
                [
                    'company_list_id' => $list->id,
                    'domain' => $entry['domain'],
                ],
                [
                    'company_name' => $entry['company'],
                    'rank' => $entry['rank'],
                ],
            );

            $syncedDomains[] = $entry['domain'];
        }

        // Remove stale entries no longer in the API response
        $list->entries()
            ->whereNotIn('domain', $syncedDomains)
            ->delete();

        return count($entries);
    }

    /**
     * Create Site records for domains in this list that don't have one yet.
     */
    private function createNewSites(CompanyList $list): int
    {
        $newEntries = CompanyListEntry::where('company_list_id', $list->id)
            ->whereNotIn('domain', Site::select('domain'))
            ->get();

        foreach ($newEntries as $entry) {
            Site::create([
                'url' => "https://{$entry->domain}",
                'domain' => $entry->domain,
                'name' => $entry->company_name,
                'status' => 'queued',
                'source' => 'company-list',
            ]);
        }

        return $newEntries->count();
    }

    /**
     * Count how many entries would create new sites (for dry-run).
     */
    private function countNewSites(array $entries): int
    {
        $existingDomains = Site::pluck('domain')->toArray();

        return collect($entries)
            ->pluck('domain')
            ->unique()
            ->reject(fn (string $domain) => in_array($domain, $existingDomains))
            ->count();
    }
}
