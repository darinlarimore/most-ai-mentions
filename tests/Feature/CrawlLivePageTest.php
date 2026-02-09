<?php

use App\Events\CrawlCompleted;
use App\Events\CrawlProgress;
use App\Events\CrawlStarted;
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
    $event = new CrawlStarted(1, 'https://example.com', 'Example');

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
