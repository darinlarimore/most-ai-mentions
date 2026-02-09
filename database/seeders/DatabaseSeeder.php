<?php

namespace Database\Seeders;

use App\Models\CrawlResult;
use App\Models\Donation;
use App\Models\NewsletterSubscriber;
use App\Models\Rating;
use App\Models\ScoreHistory;
use App\Models\Site;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create a known test user plus 4 additional users
        $testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $users = User::factory(4)->create();
        $allUsers = $users->push($testUser);

        // Create 30 sites assigned to random users
        $sites = Site::factory(30)->recycle($allUsers)->create([
            'submitted_by' => fn () => $allUsers->random()->id,
        ]);

        // Create 2-5 crawl results per site and score histories
        $sites->each(function (Site $site) {
            $crawlCount = fake()->numberBetween(2, 5);
            $crawlResults = CrawlResult::factory($crawlCount)->create([
                'site_id' => $site->id,
            ]);

            // Create a score history entry for each crawl result
            $crawlResults->each(function (CrawlResult $crawlResult) use ($site) {
                ScoreHistory::create([
                    'site_id' => $site->id,
                    'crawl_result_id' => $crawlResult->id,
                    'hype_score' => $crawlResult->total_score,
                    'ai_mention_count' => $crawlResult->ai_mention_count,
                    'lighthouse_performance' => $crawlResult->lighthouse_performance,
                    'lighthouse_accessibility' => $crawlResult->lighthouse_accessibility,
                    'recorded_at' => $crawlResult->created_at,
                ]);
            });

            // Update the site's hype_score to match its latest crawl result
            $latestCrawl = $crawlResults->last();
            $site->update([
                'hype_score' => $latestCrawl->total_score,
                'crawl_count' => $crawlCount,
            ]);
        });

        // Each user rates ~10 random sites
        $allUsers->each(function (User $user) use ($sites) {
            $sitesToRate = $sites->random(min(10, $sites->count()));

            $sitesToRate->each(function (Site $site) use ($user) {
                Rating::factory()->create([
                    'user_id' => $user->id,
                    'site_id' => $site->id,
                ]);
            });
        });

        // Update user_rating_avg and user_rating_count on each site
        $sites->each(function (Site $site) {
            $ratings = $site->ratings;
            if ($ratings->isNotEmpty()) {
                $site->update([
                    'user_rating_avg' => round($ratings->avg('score'), 1),
                    'user_rating_count' => $ratings->count(),
                ]);
            }
        });

        // Create 3 donations from random users
        Donation::factory(3)->recycle($allUsers)->create([
            'user_id' => fn () => $allUsers->random()->id,
        ]);

        // Create 10 newsletter subscribers
        NewsletterSubscriber::factory(10)->create();
    }
}
