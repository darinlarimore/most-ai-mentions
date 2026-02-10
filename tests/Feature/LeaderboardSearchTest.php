<?php

use App\Models\CrawlResult;
use App\Models\Site;

it('displays all active sites when no search query', function () {
    Site::factory()->count(3)->create(['status' => 'completed', 'is_active' => true]);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Leaderboard/Index')
            ->has('sites.data', 3)
        );
});

it('filters sites by name', function () {
    Site::factory()->create(['name' => 'OpenAI', 'is_active' => true]);
    Site::factory()->create(['name' => 'Vercel', 'is_active' => true]);

    $this->get('/?search=openai')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sites.data', 1)
            ->where('sites.data.0.name', 'OpenAI')
            ->where('search', 'openai')
        );
});

it('filters sites by domain', function () {
    Site::factory()->create(['domain' => 'anthropic.com', 'is_active' => true]);
    Site::factory()->create(['domain' => 'vercel.com', 'is_active' => true]);

    $this->get('/?search=anthropic')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sites.data', 1)
            ->where('sites.data.0.domain', 'anthropic.com')
        );
});

it('excludes uncrawled sites from leaderboard', function () {
    Site::factory()->create(['is_active' => true, 'last_crawled_at' => now()]);
    Site::factory()->create(['is_active' => true, 'last_crawled_at' => null]);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Leaderboard/Index')
            ->has('sites.data', 1)
        );
});

it('returns empty results for non-matching search', function () {
    Site::factory()->create(['name' => 'OpenAI', 'is_active' => true]);

    $this->get('/?search=nonexistent')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sites.data', 0)
        );
});

// --- Period filter tests ---

it('filters sites by today period', function () {
    Site::factory()->create(['last_crawled_at' => now(), 'is_active' => true]);
    Site::factory()->create(['last_crawled_at' => now()->subDays(2), 'is_active' => true]);

    $this->get('/?period=today')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sites.data', 1)
            ->where('period', 'today')
        );
});

it('filters sites by week period', function () {
    Site::factory()->create(['last_crawled_at' => now()->subDays(3), 'is_active' => true]);
    Site::factory()->create(['last_crawled_at' => now()->subWeeks(2), 'is_active' => true]);

    $this->get('/?period=week')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sites.data', 1)
        );
});

it('filters sites by month period', function () {
    Site::factory()->create(['last_crawled_at' => now()->subDays(10), 'is_active' => true]);
    Site::factory()->create(['last_crawled_at' => now()->subMonths(2), 'is_active' => true]);

    $this->get('/?period=month')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sites.data', 1)
        );
});

it('filters sites by year period', function () {
    Site::factory()->create(['last_crawled_at' => now()->subMonths(6), 'is_active' => true]);
    Site::factory()->create(['last_crawled_at' => now()->subYears(2), 'is_active' => true]);

    $this->get('/?period=year')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sites.data', 1)
        );
});

it('shows all sites with all time period', function () {
    Site::factory()->create(['last_crawled_at' => now(), 'is_active' => true]);
    Site::factory()->create(['last_crawled_at' => now()->subYears(2), 'is_active' => true]);

    $this->get('/?period=all')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sites.data', 2)
        );
});

// --- Sort tests ---

it('sorts sites by hype score by default', function () {
    Site::factory()->create(['hype_score' => 100, 'is_active' => true]);
    Site::factory()->create(['hype_score' => 500, 'is_active' => true]);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('sites.data.0.hype_score', 500)
            ->where('sites.data.1.hype_score', 100)
        );
});

it('sorts sites by most mentions', function () {
    $siteA = Site::factory()->create(['is_active' => true]);
    $siteB = Site::factory()->create(['is_active' => true]);
    CrawlResult::factory()->create(['site_id' => $siteA->id, 'ai_mention_count' => 5]);
    CrawlResult::factory()->create(['site_id' => $siteB->id, 'ai_mention_count' => 50]);

    $this->get('/?sort=mentions')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('sites.data.0.id', $siteB->id)
            ->where('sites.data.1.id', $siteA->id)
        );
});

it('sorts sites by user rating', function () {
    $low = Site::factory()->create(['user_rating_avg' => 2.0, 'is_active' => true]);
    $high = Site::factory()->create(['user_rating_avg' => 4.5, 'is_active' => true]);

    $this->get('/?sort=user_rating')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('sites.data.0.id', $high->id)
            ->where('sites.data.1.id', $low->id)
        );
});

it('sorts sites by newest crawl', function () {
    $old = Site::factory()->create(['last_crawled_at' => now()->subDays(10), 'is_active' => true]);
    $new = Site::factory()->create(['last_crawled_at' => now(), 'is_active' => true]);

    $this->get('/?sort=newest')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('sites.data.0.id', $new->id)
            ->where('sites.data.1.id', $old->id)
        );
});

it('sorts sites by recently added', function () {
    $old = Site::factory()->create(['is_active' => true, 'created_at' => now()->subDays(10)]);
    $new = Site::factory()->create(['is_active' => true, 'created_at' => now()]);

    $this->get('/?sort=recently_added')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('sites.data.0.id', $new->id)
            ->where('sites.data.1.id', $old->id)
        );
});

it('combines period and sort filters', function () {
    $recentHigh = Site::factory()->create(['last_crawled_at' => now(), 'user_rating_avg' => 5.0, 'is_active' => true]);
    $recentLow = Site::factory()->create(['last_crawled_at' => now(), 'user_rating_avg' => 1.0, 'is_active' => true]);
    Site::factory()->create(['last_crawled_at' => now()->subMonths(2), 'user_rating_avg' => 5.0, 'is_active' => true]);

    $this->get('/?period=month&sort=user_rating')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sites.data', 2)
            ->where('sites.data.0.id', $recentHigh->id)
        );
});

it('ignores invalid period values', function () {
    Site::factory()->create(['last_crawled_at' => now()->subYears(5), 'is_active' => true]);

    $this->get('/?period=invalid')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sites.data', 1)
        );
});

it('ignores invalid sort values', function () {
    Site::factory()->create(['hype_score' => 100, 'is_active' => true]);
    Site::factory()->create(['hype_score' => 500, 'is_active' => true]);

    $this->get('/?sort=invalid')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('sites.data.0.hype_score', 500)
        );
});
