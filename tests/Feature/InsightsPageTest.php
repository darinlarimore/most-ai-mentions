<?php

use App\Models\CrawlResult;
use App\Models\Site;

it('renders the insights page', function () {
    $response = $this->get('/insights');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Insights/Index')
        ->has('pipelineStats')
        ->where('pipelineStats.total_sites', 0)
    );
});

it('includes pipeline stats', function () {
    Site::factory()->count(3)->create(['last_crawled_at' => now()]);
    Site::factory()->pending()->create();

    $response = $this->get('/insights');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('pipelineStats.total_sites', 4)
        ->where('pipelineStats.crawled_sites', 3)
        ->where('pipelineStats.queued_sites', 1)
    );
});

it('defers term frequency data', function () {
    CrawlResult::factory()->create([
        'mention_details' => [
            ['text' => 'AI', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test'],
            ['text' => 'GPT', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test'],
        ],
    ]);

    $response = $this->get('/insights');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->missing('termFrequency')
        ->loadDeferredProps(fn ($reload) => $reload
            ->has('termFrequency', 2)
            ->where('termFrequency.0.term', 'ai')
            ->where('termFrequency.0.count', 1)
        )
    );
});

it('defers metadata group props', function () {
    Site::factory()->count(2)->create([
        'category' => 'ai_ml',
        'hype_score' => 150,
        'last_crawled_at' => now(),
    ]);

    $response = $this->get('/insights');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->missing('categoryBreakdown')
        ->missing('scoreDistribution')
        ->missing('serverDistribution')
        ->loadDeferredProps(['metadata'], fn ($reload) => $reload
            ->has('categoryBreakdown')
            ->has('scoreDistribution', 6)
            ->has('serverDistribution')
        )
    );
});

it('defers scatter data', function () {
    $site = Site::factory()->create(['last_crawled_at' => now()]);
    CrawlResult::factory()->create(['site_id' => $site->id]);

    $response = $this->get('/insights');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->missing('mentionsVsScore')
        ->loadDeferredProps(['scatter'], fn ($reload) => $reload
            ->has('mentionsVsScore', 1)
            ->where('mentionsVsScore.0.domain', $site->domain)
        )
    );
});

it('defers hosting map data', function () {
    $site = Site::factory()->withCoordinates()->create([
        'last_crawled_at' => now(),
        'server_ip' => '8.8.8.8',
    ]);

    $response = $this->get('/insights');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->missing('hostingMap')
        ->loadDeferredProps(['map'], fn ($reload) => $reload
            ->has('hostingMap', 1)
            ->where('hostingMap.0.domain', $site->domain)
            ->where('hostingMap.0.latitude', fn ($value) => is_float($value))
            ->where('hostingMap.0.longitude', fn ($value) => is_float($value))
        )
    );
});

it('defers crawler speed data', function () {
    $site = Site::factory()->create(['last_crawled_at' => now()]);
    CrawlResult::factory()->count(3)->create([
        'site_id' => $site->id,
        'created_at' => now()->subDay(),
    ]);

    $response = $this->get('/insights');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->missing('crawlerSpeed')
        ->loadDeferredProps(['timeline'], fn ($reload) => $reload
            ->has('crawlerSpeed', 1)
            ->where('crawlerSpeed.0.value', 3)
        )
    );
});
