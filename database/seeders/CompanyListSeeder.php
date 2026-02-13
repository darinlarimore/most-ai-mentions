<?php

namespace Database\Seeders;

use App\Models\CompanyList;
use App\Models\CompanyListEntry;
use Illuminate\Database\Seeder;

class CompanyListSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, description: string, source_url: string|null, sort_order: int, file: string}>
     */
    private const LISTS = [
        'fortune-500' => [
            'name' => 'Fortune 500',
            'description' => 'The Fortune 500 ranks the largest U.S. companies by total revenue. See which of these corporate giants mention AI on their websites.',
            'source_url' => 'https://fortune.com/fortune500/',
            'sort_order' => 1,
            'file' => 'fortune-500.json',
        ],
        'inc-5000' => [
            'name' => 'Inc. 5000',
            'description' => 'The Inc. 5000 lists the fastest-growing private companies in America. Discover which high-growth companies are embracing AI.',
            'source_url' => 'https://www.inc.com/inc5000',
            'sort_order' => 2,
            'file' => 'inc-5000.json',
        ],
        'forbes-global-2000' => [
            'name' => 'Forbes Global 2000',
            'description' => 'The Forbes Global 2000 ranks the world\'s largest public companies by revenue, profit, assets, and market value. See which global leaders mention AI.',
            'source_url' => 'https://www.forbes.com/lists/global2000/',
            'sort_order' => 3,
            'file' => 'forbes-global-2000.json',
        ],
        'y-combinator' => [
            'name' => 'Y Combinator',
            'description' => 'Y Combinator has funded thousands of startups since 2005. See which YC alumni are riding the AI hype wave on their websites.',
            'source_url' => 'https://www.ycombinator.com/companies',
            'sort_order' => 4,
            'file' => 'y-combinator.json',
        ],
    ];

    public function run(): void
    {
        foreach (self::LISTS as $slug => $meta) {
            $list = CompanyList::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $meta['name'],
                    'description' => $meta['description'],
                    'source_url' => $meta['source_url'],
                    'sort_order' => $meta['sort_order'],
                ],
            );

            $entries = json_decode(
                file_get_contents(database_path("data/{$meta['file']}")),
                true,
            );

            foreach ($entries as $entry) {
                CompanyListEntry::updateOrCreate(
                    [
                        'company_list_id' => $list->id,
                        'domain' => $entry['domain'],
                    ],
                    [
                        'company_name' => $entry['company'],
                        'rank' => $entry['rank'] ?? null,
                    ],
                );
            }

            $this->command?->info("Seeded {$list->name} with ".count($entries).' entries.');
        }
    }
}
