<?php

namespace App\Jobs;

use App\Events\CrawlCompleted;
use App\Events\CrawlStarted;
use App\Models\CrawlResult;
use App\Models\Site;
use App\Services\HtmlAnnotationService;
use App\Services\HypeScoreCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\Crawler\Crawler;

class CrawlSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public int $timeout = 300;

    public function __construct(
        public readonly Site $site,
    ) {}

    public function handle(HypeScoreCalculator $calculator, HtmlAnnotationService $annotationService): void
    {
        Log::info("Starting crawl for site: {$this->site->url}");

        CrawlStarted::dispatch($this->site->id, $this->site->url, $this->site->name);

        $observer = new \App\Crawlers\AiMentionCrawlObserver($this->site);

        Crawler::create()
            ->setCrawlObserver($observer)
            ->setMaximumDepth(3)
            ->setTotalCrawlLimit(50)
            ->startCrawling($this->site->url);

        $crawlResult = CrawlResult::create([
            'site_id' => $this->site->id,
            'mention_details' => $observer->getMentionDetails(),
            'ai_mention_count' => $observer->getAiMentionCount(),
            'pages_crawled' => $observer->getPagesCrawled(),
            'computed_styles' => $observer->getComputedStyles(),
            'crawled_html' => $observer->getCrawledHtml(),
            'animation_count' => $observer->getAnimationCount(),
            'glow_effect_count' => $observer->getGlowEffectCount(),
            'rainbow_border_count' => $observer->getRainbowBorderCount(),
        ]);

        $scores = $calculator->calculate(
            $crawlResult->mention_details ?? [],
            $crawlResult->animation_count,
            $crawlResult->glow_effect_count,
            $crawlResult->rainbow_border_count,
            $crawlResult->lighthouse_performance,
            $crawlResult->lighthouse_accessibility,
        );

        $crawlResult->update([
            'total_score' => $scores['total_score'],
            'mention_score' => $scores['mention_score'],
            'font_size_score' => $scores['font_size_score'],
            'animation_score' => $scores['animation_score'],
            'visual_effects_score' => $scores['visual_effects_score'],
            'lighthouse_perf_bonus' => $scores['lighthouse_perf_bonus'],
            'lighthouse_a11y_bonus' => $scores['lighthouse_a11y_bonus'],
        ]);

        $hypeScore = $scores['total_score'];

        // Generate annotated HTML if raw HTML was captured
        if ($crawlResult->crawled_html) {
            $annotatedHtml = $annotationService->annotate(
                $crawlResult->crawled_html,
                $crawlResult->mention_details ?? [],
                [
                    'total_score' => $crawlResult->total_score,
                    'mention_score' => $crawlResult->mention_score,
                    'font_size_score' => $crawlResult->font_size_score,
                    'animation_score' => $crawlResult->animation_score,
                    'visual_effects_score' => $crawlResult->visual_effects_score,
                    'lighthouse_perf_bonus' => $crawlResult->lighthouse_perf_bonus,
                    'lighthouse_a11y_bonus' => $crawlResult->lighthouse_a11y_bonus,
                    'ai_mention_count' => $crawlResult->ai_mention_count,
                    'animation_count' => $crawlResult->animation_count,
                    'glow_effect_count' => $crawlResult->glow_effect_count,
                    'rainbow_border_count' => $crawlResult->rainbow_border_count,
                    'ai_image_count' => $crawlResult->ai_image_count,
                    'ai_image_hype_bonus' => $crawlResult->ai_image_hype_bonus,
                ],
                $crawlResult->ai_image_details ?? [],
            );
            $crawlResult->update(['annotated_html' => $annotatedHtml]);
        }

        $this->site->update([
            'hype_score' => $hypeScore,
            'last_crawled_at' => now(),
        ]);

        GenerateScreenshotJob::dispatch($this->site);
        RunLighthouseJob::dispatch($this->site, $crawlResult);
        DetectAiImagesJob::dispatch($this->site, $crawlResult);

        CrawlCompleted::dispatch($this->site->id, $hypeScore, $crawlResult->ai_mention_count);

        Log::info("Completed crawl for site: {$this->site->url}, score: {$hypeScore}");
    }
}
