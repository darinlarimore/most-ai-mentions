<?php

use App\Events\CrawlCompleted;
use App\Events\CrawlProgress;
use App\Events\CrawlStarted;
use App\Events\QueueUpdated;
use App\Models\Site;
use Illuminate\Support\Facades\Event;

it('renders the live crawl page', function () {
    $this->get('/crawl/live')->assertSuccessful();
});

it('shows the currently crawling site', function () {
    $site = Site::factory()->create(['status' => 'crawling']);

    $this->get('/crawl/live')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Crawl/Live')
            ->has('currentSite')
            ->where('currentSite.id', $site->id)
        );
});

it('redirects /crawl/queue to /crawl/live', function () {
    $this->get('/crawl/queue')->assertRedirect('/crawl/live');
});

it('broadcasts CrawlProgress event on crawl-activity channel', function () {
    Event::fake([CrawlProgress::class]);

    CrawlProgress::dispatch(1, 'fetching', 'Fetching homepage...');

    Event::assertDispatched(CrawlProgress::class, function ($event) {
        return $event->site_id === 1
            && $event->step === 'fetching'
            && $event->message === 'Fetching homepage...';
    });
});

it('broadcasts CrawlStarted as ShouldBroadcastNow', function () {
    $event = new CrawlStarted(1, 'https://example.com', 'Example', 'example-com');

    expect($event)->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class);
    expect($event->broadcastOn())->toHaveCount(1);
});

it('broadcasts CrawlCompleted as ShouldBroadcastNow', function () {
    $event = new CrawlCompleted(1, 42.5, 5);

    expect($event)->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class);
    expect($event->broadcastWith())->toMatchArray([
        'site_id' => 1,
        'hype_score' => 42.5,
        'ai_mention_count' => 5,
    ]);
});

it('broadcasts CrawlProgress with data payload', function () {
    $event = new CrawlProgress(1, 'detecting_mentions', 'Found 5 AI mentions', [
        'ai_mention_count' => 5,
    ]);

    expect($event->broadcastWith())->toMatchArray([
        'site_id' => 1,
        'step' => 'detecting_mentions',
        'message' => 'Found 5 AI mentions',
        'data' => ['ai_mention_count' => 5],
    ]);
});

it('uses correct broadcastAs names matching frontend Echo listeners', function () {
    $started = new CrawlStarted(1, 'https://example.com', 'Example', 'example-com');
    $progress = new CrawlProgress(1, 'fetching', 'Fetching...');
    $completed = new CrawlCompleted(1, 42.5, 5);

    expect($started->broadcastAs())->toBe('CrawlStarted');
    expect($progress->broadcastAs())->toBe('CrawlProgress');
    expect($completed->broadcastAs())->toBe('CrawlCompleted');
});

it('shows last crawled site when no active crawl', function () {
    $site = Site::factory()->create([
        'status' => 'completed',
        'last_crawled_at' => now()->subHour(),
        'hype_score' => 75,
    ]);

    $this->get('/crawl/live')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Crawl/Live')
            ->where('currentSite', null)
            ->has('lastCrawledSite')
            ->where('lastCrawledSite.id', $site->id)
        );
});

it('does not include lastCrawledSite when a crawl is active', function () {
    $crawling = Site::factory()->create(['status' => 'crawling']);
    Site::factory()->create(['status' => 'completed', 'last_crawled_at' => now()->subHour()]);

    $this->get('/crawl/live')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Crawl/Live')
            ->where('currentSite.id', $crawling->id)
            ->where('lastCrawledSite', null)
        );
});

it('allows null site_name in CrawlStarted', function () {
    $event = new CrawlStarted(1, 'https://example.com', null, 'example-com');

    expect($event->site_name)->toBeNull();
    expect($event->broadcastWith()['site_name'])->toBeNull();
});

it('broadcasts QueueUpdated as ShouldBroadcastNow', function () {
    $event = new QueueUpdated(15);

    expect($event)->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class);
    expect($event->broadcastAs())->toBe('QueueUpdated');
    expect($event->broadcastOn())->toHaveCount(1);
});

it('broadcasts QueueUpdated with queue count', function () {
    $event = new QueueUpdated(42, ['id' => 1, 'url' => 'https://example.com']);

    expect($event->broadcastWith())->toMatchArray([
        'queued_count' => 42,
        'currently_crawling' => ['id' => 1, 'url' => 'https://example.com'],
    ]);
});
