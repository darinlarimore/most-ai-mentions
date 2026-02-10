<?php

namespace App\Jobs;

use App\Events\CrawlCompleted;
use App\Events\CrawlProgress;
use App\Events\CrawlStarted;
use App\Jobs\Middleware\CheckQueuePaused;
use App\Models\CrawlResult;
use App\Models\Site;
use App\Services\AiImageDetectionService;
use App\Services\HtmlAnnotationService;
use App\Services\HypeScoreCalculator;
use App\Services\ScreenshotService;
use App\Services\SiteCategoryDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\Crawler\Crawler;

class CrawlSiteJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Only count actual exceptions, not middleware releases. */
    public int $maxExceptions = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public int $timeout = 300;

    /** Keep the unique lock for 10 minutes to cover the full crawl duration. */
    public int $uniqueFor = 600;

    public function __construct(
        public readonly Site $site,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->site->id;
    }

    public function middleware(): array
    {
        return [new CheckQueuePaused];
    }

    public function handle(
        HypeScoreCalculator $calculator,
        HtmlAnnotationService $annotationService,
        AiImageDetectionService $imageDetectionService,
        ScreenshotService $screenshotService,
        SiteCategoryDetector $categoryDetector,
    ): void {
        // Guard: skip if this site was already crawled recently (duplicate job protection)
        $this->site->refresh();
        if ($this->site->isOnCooldown()) {
            Log::info("Skipping duplicate crawl for {$this->site->url} — site is on cooldown");
            self::dispatchNext();

            return;
        }

        // Normalize URL to homepage if it has a path
        $parsed = parse_url($this->site->url);
        $homepageUrl = ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '');
        if ($this->site->url !== $homepageUrl) {
            $this->site->update(['url' => $homepageUrl]);
        }

        Log::info("Starting crawl for site: {$this->site->url}");

        $this->site->update(['status' => 'crawling']);

        CrawlStarted::dispatch($this->site->id, $this->site->url, $this->site->name, $this->site->slug);
        CrawlProgress::dispatch($this->site->id, 'fetching', 'Fetching homepage...');

        $observer = new \App\Crawlers\AiMentionCrawlObserver($this->site);

        Crawler::create()
            ->setCrawlObserver($observer)
            ->setMaximumDepth(0)
            ->setTotalCrawlLimit(1)
            ->startCrawling($this->site->url);

        // Keep HTML as local variable only — never persisted to DB
        $html = $observer->getCrawledHtml();

        // Auto-detect category from metadata (only if currently 'other')
        if ($html && $this->site->category === 'other') {
            CrawlProgress::dispatch($this->site->id, 'detecting_category', 'Detecting site category...');
            $detectedCategory = $categoryDetector->detect($html);
            $this->site->update(['category' => $detectedCategory->value]);
            Log::info("Category detection for {$this->site->url}", ['detected' => $detectedCategory->value]);
        }

        CrawlProgress::dispatch($this->site->id, 'detecting_mentions', 'Scanning for AI mentions...', [
            'pages_crawled' => $observer->getPagesCrawled(),
            'ai_mention_count' => $observer->getAiMentionCount(),
        ]);

        Log::info("Crawl observer results for {$this->site->url}", [
            'pages_crawled' => $observer->getPagesCrawled(),
            'ai_mentions' => $observer->getAiMentionCount(),
            'animations' => $observer->getAnimationCount(),
            'glows' => $observer->getGlowEffectCount(),
            'rainbows' => $observer->getRainbowBorderCount(),
            'has_html' => $html !== null,
            'html_length' => $html ? strlen($html) : 0,
            'mention_details' => array_map(fn ($m) => $m['text'].' ('.$m['context'].')', array_slice($observer->getMentionDetails(), 0, 5)),
        ]);

        $crawlResult = CrawlResult::create([
            'site_id' => $this->site->id,
            'mention_details' => $observer->getMentionDetails(),
            'ai_mention_count' => $observer->getAiMentionCount(),
            'pages_crawled' => $observer->getPagesCrawled(),
            'computed_styles' => $observer->getComputedStyles(),
            'animation_count' => $observer->getAnimationCount(),
            'glow_effect_count' => $observer->getGlowEffectCount(),
            'rainbow_border_count' => $observer->getRainbowBorderCount(),
        ]);

        // Detect AI images inline from local HTML
        CrawlProgress::dispatch($this->site->id, 'detecting_images', 'Scanning for AI-generated images...');

        $aiImageData = ['ai_image_count' => 0, 'ai_image_score' => 0, 'ai_image_details' => []];
        if ($html) {
            $aiImageData = $imageDetectionService->analyze($html, $this->site->url);
            Log::info("AI image detection for {$this->site->url}", [
                'ai_image_count' => $aiImageData['ai_image_count'],
                'ai_image_score' => $aiImageData['ai_image_score'],
            ]);
            $crawlResult->update([
                'ai_image_count' => $aiImageData['ai_image_count'],
                'ai_image_score' => $aiImageData['ai_image_score'],
                'ai_image_details' => $aiImageData['ai_image_details'],
            ]);
        } else {
            Log::warning("No HTML captured for {$this->site->url} — crawler may have been blocked");
        }

        // Calculate scores with AI image data included
        CrawlProgress::dispatch($this->site->id, 'calculating_score', 'Calculating hype score...', [
            'ai_mention_count' => $crawlResult->ai_mention_count,
            'animation_count' => $crawlResult->animation_count,
            'ai_image_count' => $aiImageData['ai_image_count'],
        ]);

        $scores = $calculator->calculate(
            $crawlResult->mention_details ?? [],
            $crawlResult->animation_count ?? 0,
            $crawlResult->glow_effect_count ?? 0,
            $crawlResult->rainbow_border_count ?? 0,
            $crawlResult->lighthouse_performance,
            $crawlResult->lighthouse_accessibility,
            $crawlResult->ai_image_count ?? 0,
            $crawlResult->ai_image_score ?? 0,
        );

        $crawlResult->update([
            'total_score' => $scores['total_score'],
            'mention_score' => $scores['mention_score'],
            'font_size_score' => $scores['font_size_score'],
            'animation_score' => $scores['animation_score'],
            'visual_effects_score' => $scores['visual_effects_score'],
            'lighthouse_perf_bonus' => $scores['lighthouse_perf_bonus'],
            'lighthouse_a11y_bonus' => $scores['lighthouse_a11y_bonus'],
            'ai_image_hype_bonus' => $scores['ai_image_hype_bonus'] ?? 0,
        ]);

        $hypeScore = $scores['total_score'];

        // If no pages were crawled or no HTML captured, the site likely blocked us
        if ($observer->getPagesCrawled() === 0 || $html === null) {
            Log::info("Crawl blocked/failed for {$this->site->url} — marking as failed attempt", [
                'pages_crawled' => $observer->getPagesCrawled(),
                'has_html' => $html !== null,
            ]);

            $this->site->update([
                'last_attempted_at' => now(),
                'status' => 'pending',
            ]);

            self::dispatchNext();

            return;
        }

        // Generate annotated screenshot from local HTML
        CrawlProgress::dispatch($this->site->id, 'generating_screenshot', 'Generating annotated screenshot...');

        if ($html) {
            try {
                $annotatedHtml = $annotationService->annotate(
                    $html,
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

                $screenshotPath = $screenshotService->captureHtml($annotatedHtml, $this->site->domain);
                $crawlResult->update(['annotated_screenshot_path' => $screenshotPath]);
            } catch (\Throwable $e) {
                Log::warning("Failed to generate annotated screenshot for site: {$this->site->url}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Discard HTML — it's no longer needed
        unset($html);

        $this->site->update([
            'hype_score' => $hypeScore,
            'last_crawled_at' => now(),
            'last_attempted_at' => now(),
            'status' => 'completed',
        ]);

        CrawlProgress::dispatch($this->site->id, 'finishing', 'Running final checks...', [
            'hype_score' => $hypeScore,
        ]);

        GenerateScreenshotJob::dispatch($this->site);
        RunLighthouseJob::dispatch($this->site, $crawlResult);

        CrawlCompleted::dispatch($this->site->id, $hypeScore, $crawlResult->ai_mention_count);

        Log::info("Completed crawl for site: {$this->site->url}, score: {$hypeScore}");

        self::dispatchNext();
    }

    /**
     * Handle a permanently failed job.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error("CrawlSiteJob permanently failed for {$this->site->url}", [
            'error' => $exception?->getMessage(),
        ]);

        $this->site->update([
            'status' => 'pending',
            'last_attempted_at' => now(),
        ]);

        self::dispatchNext();
    }

    /**
     * Find and dispatch the next site ready to crawl.
     */
    public static function dispatchNext(): void
    {
        $next = Site::query()
            ->crawlQueue()
            ->first();

        if ($next) {
            self::dispatch($next);
        }
    }
}
