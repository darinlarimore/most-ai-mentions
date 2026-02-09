<?php

namespace App\Jobs;

use App\Models\CrawlResult;
use App\Models\Site;
use App\Services\AiImageDetectionService;
use App\Services\HypeScoreCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DetectAiImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Site $site,
        public readonly CrawlResult $crawlResult,
    ) {}

    public function handle(
        AiImageDetectionService $detectionService,
        HypeScoreCalculator $calculator,
    ): void {
        Log::info("Detecting AI images for site: {$this->site->url}");

        if (! $this->crawlResult->crawled_html) {
            Log::info("No crawled HTML available for site: {$this->site->url}, skipping AI image detection");

            return;
        }

        $result = $detectionService->analyze($this->crawlResult->crawled_html, $this->site->url);

        $this->crawlResult->update([
            'ai_image_count' => $result['ai_image_count'],
            'ai_image_score' => $result['ai_image_score'],
            'ai_image_details' => $result['ai_image_details'],
        ]);

        // Recalculate the full hype score with AI image data
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
            'ai_image_hype_bonus' => $scores['ai_image_hype_bonus'],
        ]);

        $this->site->update([
            'hype_score' => $scores['total_score'],
        ]);

        Log::info("AI image detection complete for site: {$this->site->url}, found {$result['ai_image_count']} AI images, updated score: {$scores['total_score']}");
    }
}
