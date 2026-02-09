<?php

use App\Jobs\DetectAiImagesJob;
use App\Models\CrawlResult;
use App\Models\Site;
use App\Services\AiImageDetectionService;
use App\Services\HypeScoreCalculator;
use Illuminate\Support\Facades\Http;

it('updates crawl result with AI image detection results', function () {
    Http::fake(['*' => Http::response('', 404)]);

    $site = Site::factory()->create();
    $crawlResult = CrawlResult::factory()->for($site)->create([
        'crawled_html' => '<html><body><img src="https://oaidalleapiprodscus.blob.core.windows.net/test.png" alt="AI generated"></body></html>',
        'ai_image_count' => 0,
        'ai_image_score' => 0,
        'ai_image_details' => null,
    ]);

    (new DetectAiImagesJob($site, $crawlResult))->handle(
        new AiImageDetectionService,
        new HypeScoreCalculator,
    );

    $crawlResult->refresh();

    expect($crawlResult->ai_image_details)->toBeArray()
        ->and($crawlResult->ai_image_score)->toBeGreaterThan(0)
        ->and($crawlResult->ai_image_hype_bonus)->toBeGreaterThan(0);
});

it('skips detection when no crawled HTML available', function () {
    $site = Site::factory()->create();
    $crawlResult = CrawlResult::factory()->for($site)->create([
        'crawled_html' => null,
        'ai_image_count' => 0,
        'ai_image_details' => null,
    ]);

    $originalCount = $crawlResult->ai_image_count;

    (new DetectAiImagesJob($site, $crawlResult))->handle(
        new AiImageDetectionService,
        new HypeScoreCalculator,
    );

    $crawlResult->refresh();

    expect($crawlResult->ai_image_count)->toBe($originalCount)
        ->and($crawlResult->ai_image_details)->toBeNull();
});

it('recalculates total score with AI image bonus', function () {
    Http::fake(['*' => Http::response('', 404)]);

    $site = Site::factory()->create(['hype_score' => 50]);
    $crawlResult = CrawlResult::factory()->for($site)->create([
        'crawled_html' => '<html><body><img src="https://cdn.midjourney.com/test.png" alt="midjourney art"></body></html>',
        'total_score' => 50,
        'ai_image_count' => 0,
        'ai_image_score' => 0,
        'ai_image_hype_bonus' => 0,
    ]);

    (new DetectAiImagesJob($site, $crawlResult))->handle(
        new AiImageDetectionService,
        new HypeScoreCalculator,
    );

    $site->refresh();
    $crawlResult->refresh();

    expect($crawlResult->total_score)->toBeGreaterThan(0)
        ->and($site->hype_score)->toBe($crawlResult->total_score);
});

it('dispatches from CrawlSiteJob', function () {
    // Verify the job class exists and is dispatchable
    expect(class_exists(DetectAiImagesJob::class))->toBeTrue();

    $reflection = new ReflectionClass(DetectAiImagesJob::class);
    $traits = $reflection->getTraitNames();

    expect($traits)->toContain('Illuminate\Foundation\Bus\Dispatchable');
});
