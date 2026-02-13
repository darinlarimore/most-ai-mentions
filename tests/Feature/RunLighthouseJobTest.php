<?php

use App\Events\LighthouseComplete;
use App\Jobs\RunLighthouseJob;
use App\Models\CrawlResult;
use App\Models\ScoreHistory;
use App\Models\Site;
use App\Services\LighthouseService;
use Illuminate\Support\Facades\Event;

it('updates crawl result and score history with lighthouse scores', function () {
    Event::fake([LighthouseComplete::class]);

    $site = Site::factory()->create();
    $crawlResult = CrawlResult::factory()->create([
        'site_id' => $site->id,
        'lighthouse_performance' => null,
        'lighthouse_accessibility' => null,
        'lighthouse_best_practices' => null,
        'lighthouse_seo' => null,
    ]);

    $scoreHistory = ScoreHistory::create([
        'site_id' => $site->id,
        'crawl_result_id' => $crawlResult->id,
        'hype_score' => $crawlResult->total_score,
        'ai_mention_count' => $crawlResult->ai_mention_count,
        'recorded_at' => now(),
    ]);

    $lighthouseService = Mockery::mock(LighthouseService::class);
    $lighthouseService->shouldReceive('audit')
        ->once()
        ->with($site->domain)
        ->andReturn([
            'performance' => 85,
            'accessibility' => 92,
            'best_practices' => 100,
            'seo' => 78,
        ]);

    app()->instance(LighthouseService::class, $lighthouseService);

    (new RunLighthouseJob($site))->handle($lighthouseService);

    $crawlResult->refresh();
    expect($crawlResult->lighthouse_performance)->toBe(85)
        ->and($crawlResult->lighthouse_accessibility)->toBe(92)
        ->and($crawlResult->lighthouse_best_practices)->toBe(100)
        ->and($crawlResult->lighthouse_seo)->toBe(78);

    $scoreHistory->refresh();
    expect($scoreHistory->lighthouse_performance)->toBe(85)
        ->and($scoreHistory->lighthouse_accessibility)->toBe(92);
});

it('broadcasts LighthouseComplete event on success', function () {
    Event::fake([LighthouseComplete::class]);

    $site = Site::factory()->create();
    CrawlResult::factory()->create(['site_id' => $site->id]);

    $lighthouseService = Mockery::mock(LighthouseService::class);
    $lighthouseService->shouldReceive('audit')
        ->once()
        ->andReturn([
            'performance' => 70,
            'accessibility' => 80,
            'best_practices' => 90,
            'seo' => 95,
        ]);

    (new RunLighthouseJob($site))->handle($lighthouseService);

    Event::assertDispatched(LighthouseComplete::class, function (LighthouseComplete $event) use ($site) {
        return $event->site_id === $site->id
            && $event->slug === $site->slug
            && $event->performance === 70
            && $event->accessibility === 80
            && $event->best_practices === 90
            && $event->seo === 95;
    });
});

it('does not broadcast event when audit returns null', function () {
    Event::fake([LighthouseComplete::class]);

    $site = Site::factory()->create();
    CrawlResult::factory()->create(['site_id' => $site->id]);

    $lighthouseService = Mockery::mock(LighthouseService::class);
    $lighthouseService->shouldReceive('audit')
        ->once()
        ->andReturn(null);

    $job = new RunLighthouseJob($site);

    try {
        $job->handle($lighthouseService);
    } catch (\Throwable) {
        // release() throws when not on a real queue
    }

    Event::assertNotDispatched(LighthouseComplete::class);
});
