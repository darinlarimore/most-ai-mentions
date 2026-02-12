<?php

use App\Enums\CrawlErrorCategory;
use App\Events\CrawlCompleted;
use App\Models\CrawlError;
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

it('loads term frequency data via partial reload', function () {
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
        ->reloadOnly('termFrequency', fn ($reload) => $reload
            ->has('termFrequency', 2)
            ->where('termFrequency.0.term', 'ai')
            ->where('termFrequency.0.count', 1)
        )
    );
});

it('loads category and score data via partial reload', function () {
    Site::factory()->count(2)->create([
        'category' => 'ai_ml',
        'hype_score' => 150,
        'last_crawled_at' => now(),
    ]);

    $response = $this->get('/insights');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->missing('scoreDistribution')
        ->reloadOnly(['scoreDistribution'], fn ($reload) => $reload
            ->has('scoreDistribution', 6)
        )
    );
});

it('loads scatter data via partial reload', function () {
    $site = Site::factory()->create(['last_crawled_at' => now()]);
    CrawlResult::factory()->create(['site_id' => $site->id]);

    $response = $this->get('/insights');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->missing('mentionsVsScore')
        ->reloadOnly('mentionsVsScore', fn ($reload) => $reload
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

it('broadcasts crawl_duration_ms in CrawlCompleted event', function () {
    $event = new CrawlCompleted(
        site_id: 1,
        hype_score: 100.0,
        ai_mention_count: 5,
        screenshot_path: null,
        crawl_duration_ms: 45000,
    );

    $data = $event->broadcastWith();

    expect($data)->toHaveKey('crawl_duration_ms', 45000)
        ->and($data)->toHaveKey('site_id', 1)
        ->and($event->broadcastAs())->toBe('CrawlCompleted');
});

it('broadcasts network fields in CrawlCompleted event', function () {
    $event = new CrawlCompleted(
        site_id: 1,
        hype_score: 100.0,
        ai_mention_count: 5,
        screenshot_path: null,
        crawl_duration_ms: 45000,
        domain: 'example.com',
        slug: 'example-com',
        category: 'tech',
        ai_terms: ['gpt', 'llm'],
    );

    $data = $event->broadcastWith();

    expect($data)->toHaveKey('domain', 'example.com')
        ->and($data)->toHaveKey('slug', 'example-com')
        ->and($data)->toHaveKey('category', 'tech')
        ->and($data)->toHaveKey('ai_terms', ['gpt', 'llm']);
});

it('returns network graph data', function () {
    $siteA = Site::factory()->create(['last_crawled_at' => now(), 'hype_score' => 100]);
    $siteB = Site::factory()->create(['last_crawled_at' => now(), 'hype_score' => 200]);

    CrawlResult::factory()->create([
        'site_id' => $siteA->id,
        'mention_details' => [
            ['text' => 'GPT', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test'],
            ['text' => 'LLM', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test'],
        ],
    ]);

    CrawlResult::factory()->create([
        'site_id' => $siteB->id,
        'mention_details' => [
            ['text' => 'GPT', 'font_size' => 16, 'has_animation' => false, 'has_glow' => false, 'context' => 'test'],
        ],
    ]);

    $response = $this->getJson('/insights/network');

    $response->assertSuccessful();
    $response->assertJsonStructure(['nodes', 'links']);

    $nodes = $response->json('nodes');
    $links = $response->json('links');

    // GPT appears on 2 sites, so it qualifies. LLM only on 1 site, filtered out.
    $termNodes = collect($nodes)->where('type', 'term');
    expect($termNodes)->toHaveCount(1)
        ->and($termNodes->first()['label'])->toBe('gpt');

    $siteNodes = collect($nodes)->where('type', 'site');
    expect($siteNodes)->toHaveCount(2);

    // Both sites link to GPT
    expect($links)->toHaveCount(2);
});

it('loads crawler speed data via partial reload', function () {
    $site = Site::factory()->create(['last_crawled_at' => now()]);
    CrawlResult::factory()->count(3)->create([
        'site_id' => $site->id,
        'crawl_duration_ms' => 5000,
        'created_at' => now()->subDay(),
    ]);

    $response = $this->get('/insights');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->missing('crawlerSpeed')
        ->reloadOnly('crawlerSpeed', fn ($reload) => $reload
            ->has('crawlerSpeed', 3)
            ->where('crawlerSpeed.0.duration_ms', 5000)
            ->has('crawlerSpeed.0.timestamp')
        )
    );
});

it('loads crawl error data via partial reload', function () {
    $site = Site::factory()->create();
    CrawlError::factory()->count(3)->create([
        'site_id' => $site->id,
        'category' => CrawlErrorCategory::Timeout,
    ]);
    CrawlError::factory()->count(2)->create([
        'site_id' => $site->id,
        'category' => CrawlErrorCategory::DnsFailure,
    ]);

    $response = $this->get('/insights');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->missing('crawlErrors')
        ->reloadOnly('crawlErrors', fn ($reload) => $reload
            ->has('crawlErrors.by_category', 2)
            ->has('crawlErrors.top_domains', 1)
            ->where('crawlErrors.top_domains.0.label', $site->domain)
            ->where('crawlErrors.top_domains.0.value', 5)
        )
    );
});
