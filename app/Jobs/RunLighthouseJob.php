<?php

namespace App\Jobs;

use App\Models\CrawlResult;
use App\Models\Site;
use App\Services\HypeScoreCalculator;
use App\Services\LighthouseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunLighthouseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Site $site,
        public readonly CrawlResult $crawlResult,
    ) {}

    public function handle(
        LighthouseService $lighthouseService,
        HypeScoreCalculator $calculator,
    ): void {
        Log::info("Running Lighthouse audit for site: {$this->site->url}");

        $lighthouseScores = $lighthouseService->run($this->site->url);

        $this->crawlResult->update([
            'lighthouse_performance' => $lighthouseScores['performance'],
            'lighthouse_accessibility' => $lighthouseScores['accessibility'],
        ]);

        // Recalculate the full hype score with Lighthouse data
        $freshResult = $this->crawlResult->fresh();
        $scores = $calculator->calculate(
            $freshResult->mention_details ?? [],
            $freshResult->animation_count,
            $freshResult->glow_effect_count,
            $freshResult->rainbow_border_count,
            $freshResult->lighthouse_performance,
            $freshResult->lighthouse_accessibility,
            $freshResult->ai_image_count,
            $freshResult->ai_image_score,
        );

        $freshResult->update([
            'total_score' => $scores['total_score'],
            'lighthouse_perf_bonus' => $scores['lighthouse_perf_bonus'],
            'lighthouse_a11y_bonus' => $scores['lighthouse_a11y_bonus'],
        ]);

        $this->site->update([
            'hype_score' => $scores['total_score'],
        ]);

        Log::info("Lighthouse audit complete for site: {$this->site->url}, updated score: {$scores['total_score']}");
    }
}
