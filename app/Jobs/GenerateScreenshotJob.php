<?php

namespace App\Jobs;

use App\Jobs\Middleware\CheckQueuePaused;
use App\Models\Site;
use App\Services\ScreenshotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateScreenshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 90;

    /** Only count actual exceptions, not middleware releases. */
    public int $maxExceptions = 2;

    public function __construct(
        public readonly Site $site,
    ) {}

    public function middleware(): array
    {
        return [new CheckQueuePaused];
    }

    public function handle(ScreenshotService $screenshotService): void
    {
        Log::info("Generating screenshot for site: {$this->site->url}");

        try {
            $screenshotPath = $screenshotService->capture($this->site->url);

            $this->site->update([
                'screenshot_path' => $screenshotPath,
            ]);

            Log::info("Screenshot saved for site: {$this->site->url} at {$screenshotPath}");
        } catch (\Throwable $e) {
            Log::warning("Screenshot failed for site: {$this->site->url}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
