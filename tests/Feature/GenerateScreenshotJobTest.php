<?php

use App\Events\ScreenshotReady;
use App\Jobs\GenerateScreenshotJob;
use App\Models\Site;
use App\Services\ScreenshotService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

it('broadcasts ScreenshotReady after saving screenshot', function () {
    Event::fake([ScreenshotReady::class]);

    $site = Site::factory()->create(['screenshot_path' => null]);

    $screenshotService = Mockery::mock(ScreenshotService::class);
    $screenshotService->shouldReceive('capture')
        ->once()
        ->with($site->url)
        ->andReturn('screenshots/test-screenshot.jpg');

    app()->instance(ScreenshotService::class, $screenshotService);

    (new GenerateScreenshotJob($site))->handle($screenshotService);

    Event::assertDispatched(ScreenshotReady::class, function (ScreenshotReady $event) use ($site) {
        return $event->site_id === $site->id
            && $event->slug === $site->slug;
    });
});

it('deletes old screenshot when saving a new one', function () {
    Event::fake([ScreenshotReady::class]);
    Storage::fake('public');

    Storage::disk('public')->put('screenshots/old-screenshot.jpg', 'old-image-data');

    $site = Site::factory()->create(['screenshot_path' => 'screenshots/old-screenshot.jpg']);

    $screenshotService = Mockery::mock(ScreenshotService::class);
    $screenshotService->shouldReceive('capture')
        ->once()
        ->with($site->url)
        ->andReturn('screenshots/new-screenshot.webp');

    (new GenerateScreenshotJob($site))->handle($screenshotService);

    Storage::disk('public')->assertMissing('screenshots/old-screenshot.jpg');
});

it('handles null old screenshot path gracefully', function () {
    Event::fake([ScreenshotReady::class]);
    Storage::fake('public');

    $site = Site::factory()->create(['screenshot_path' => null]);

    $screenshotService = Mockery::mock(ScreenshotService::class);
    $screenshotService->shouldReceive('capture')
        ->once()
        ->with($site->url)
        ->andReturn('screenshots/new-screenshot.webp');

    (new GenerateScreenshotJob($site))->handle($screenshotService);

    $site->refresh();
    expect($site->getRawOriginal('screenshot_path'))->toBe('screenshots/new-screenshot.webp');
});

it('does not broadcast ScreenshotReady when screenshot fails', function () {
    Event::fake([ScreenshotReady::class]);

    $site = Site::factory()->create(['screenshot_path' => null]);

    $screenshotService = Mockery::mock(ScreenshotService::class);
    $screenshotService->shouldReceive('capture')
        ->once()
        ->andThrow(new \RuntimeException('Chrome timeout'));

    app()->instance(ScreenshotService::class, $screenshotService);

    $job = new GenerateScreenshotJob($site);

    // The job calls $this->release(30) on failure, which requires InteractsWithQueue
    // We just verify no event is dispatched
    try {
        $job->handle($screenshotService);
    } catch (\Throwable) {
        // Expected — release() throws when not on a real queue
    }

    Event::assertNotDispatched(ScreenshotReady::class);
});
