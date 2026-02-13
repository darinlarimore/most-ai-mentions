<?php

namespace App\Jobs;

use App\Events\LighthouseComplete;
use App\Jobs\Middleware\CheckQueuePaused;
use App\Models\ScoreHistory;
use App\Models\Site;
use App\Services\LighthouseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunLighthouseJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    public int $timeout = 120;

    public int $maxExceptions = 3;

    public function __construct(
        public readonly Site $site,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->site->id;
    }

    public function middleware(): array
    {
        return [
            new CheckQueuePaused,
            (new WithoutOverlapping('lighthouse'))->shared()->releaseAfter(30)->expireAfter(180),
        ];
    }

    public function handle(LighthouseService $lighthouseService): void
    {
        Log::info("Running Lighthouse audit for site: {$this->site->url}");

        try {
            $scores = $lighthouseService->audit($this->site->domain);

            if ($scores === null) {
                Log::warning("Lighthouse audit returned no scores for {$this->site->url}");
                $this->release(30);

                return;
            }

            $crawlResult = $this->site->crawlResults()->latest()->first();

            if ($crawlResult) {
                $crawlResult->update([
                    'lighthouse_performance' => $scores['performance'],
                    'lighthouse_accessibility' => $scores['accessibility'],
                    'lighthouse_best_practices' => $scores['best_practices'],
                    'lighthouse_seo' => $scores['seo'],
                ]);

                ScoreHistory::query()
                    ->where('crawl_result_id', $crawlResult->id)
                    ->update([
                        'lighthouse_performance' => $scores['performance'],
                        'lighthouse_accessibility' => $scores['accessibility'],
                    ]);
            }

            LighthouseComplete::dispatch(
                $this->site->id,
                $this->site->slug,
                $scores['performance'],
                $scores['accessibility'],
                $scores['best_practices'],
                $scores['seo'],
            );

            Log::info("Lighthouse audit completed for site: {$this->site->url}", $scores);
        } catch (\Throwable $e) {
            Log::warning("Lighthouse audit failed for site: {$this->site->url}", [
                'error' => $e->getMessage(),
            ]);

            $this->release(30);
        }
    }
}
