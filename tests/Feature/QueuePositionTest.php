<?php

use App\Models\Site;

it('returns queue positions for requested site ids', function () {
    // Create 3 sites that will appear in the crawl queue (never crawled, active, not currently crawling)
    $siteA = Site::factory()->create([
        'status' => 'pending',
        'is_active' => true,
        'last_crawled_at' => null,
        'last_attempted_at' => null,
        'consecutive_failures' => 0,
        'hype_score' => 0,
        'crawl_count' => 0,
        'source' => 'discovered',
    ]);

    $siteB = Site::factory()->create([
        'status' => 'pending',
        'is_active' => true,
        'last_crawled_at' => null,
        'last_attempted_at' => null,
        'consecutive_failures' => 0,
        'hype_score' => 0,
        'crawl_count' => 0,
        'source' => 'submitted',
    ]);

    // siteB should be first (submitted + never crawled), then siteA (never crawled)
    $response = $this->getJson('/api/queue-positions?ids[]='.$siteA->id.'&ids[]='.$siteB->id);

    $response->assertSuccessful()
        ->assertJsonStructure(['positions', 'total', 'statuses']);

    $positions = $response->json('positions');
    $total = $response->json('total');

    expect($total)->toBeGreaterThanOrEqual(2);
    expect($positions)->toHaveKey((string) $siteA->id);
    expect($positions)->toHaveKey((string) $siteB->id);
    // Submitted+never-crawled site should be ahead of discovered+never-crawled
    expect($positions[(string) $siteB->id])->toBeLessThan($positions[(string) $siteA->id]);
});

it('excludes site ids not in the queue', function () {
    $site = Site::factory()->create([
        'status' => 'completed',
        'is_active' => true,
        'last_crawled_at' => now(),
        'last_attempted_at' => now(),
        'cooldown_hours' => 24,
    ]);

    $response = $this->getJson('/api/queue-positions?ids[]='.$site->id);

    $response->assertSuccessful();

    $positions = $response->json('positions');

    expect($positions)->not->toHaveKey((string) $site->id);
});

it('returns empty positions when no ids provided', function () {
    $response = $this->getJson('/api/queue-positions');

    $response->assertSuccessful()
        ->assertJson(['positions' => [], 'total' => 0, 'statuses' => []]);
});

it('returns statuses for completed sites not in the queue', function () {
    $site = Site::factory()->create([
        'status' => 'completed',
        'is_active' => true,
        'last_crawled_at' => now(),
        'last_attempted_at' => now(),
        'hype_score' => 350,
        'cooldown_hours' => 24,
    ]);

    $response = $this->getJson('/api/queue-positions?ids[]='.$site->id);

    $response->assertSuccessful();

    $positions = $response->json('positions');
    $statuses = $response->json('statuses');

    expect($positions)->not->toHaveKey((string) $site->id);
    expect($statuses)->toHaveKey((string) $site->id);
    expect($statuses[(string) $site->id])->toMatchArray([
        'status' => 'completed',
        'hype_score' => 350,
    ]);
});
