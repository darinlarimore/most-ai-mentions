<?php

namespace App\Jobs;

use App\Enums\CrawlErrorCategory;
use App\Events\CrawlCompleted;
use App\Events\CrawlProgress;
use App\Events\CrawlStarted;
use App\Events\QueueUpdated;
use App\Jobs\Middleware\CheckQueuePaused;
use App\Models\CrawlError;
use App\Models\CrawlResult;
use App\Models\Site;
use App\Services\AxeAuditService;
use App\Services\HttpMetadataCollector;
use App\Services\HypeScoreCalculator;
use App\Services\IpGeolocationService;
use App\Services\RobotsTxtChecker;
use App\Services\ScreenshotService;
use App\Services\SiteCategoryDetector;
use App\Services\TechStackDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CrawlSiteJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Allow enough attempts to survive queue pauses during deploys. */
    public int $tries = 25;

    /** Only count actual exceptions, not middleware releases. */
    public int $maxExceptions = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public int $timeout = 300;

    /** Keep the unique lock for 10 minutes to cover the full crawl duration. */
    public int $uniqueFor = 600;

    public function __construct(
        public readonly Site $site,
        public readonly bool $backfill = false,
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
        ScreenshotService $screenshotService,
        SiteCategoryDetector $categoryDetector,
        HttpMetadataCollector $httpMetadataCollector,
        TechStackDetector $techStackDetector,
        IpGeolocationService $ipGeolocationService,
        AxeAuditService $axeAuditService,
    ): void {
        // Guard: skip if this site was already crawled recently (duplicate job protection)
        // Backfill crawls bypass cooldown since they target sites missing data
        $this->site->refresh();
        if (! $this->backfill && $this->site->isOnCooldown()) {
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

        // Check robots.txt before starting the crawl
        $robotsChecker = app(RobotsTxtChecker::class);
        if (! $robotsChecker->isAllowed($this->site->url)) {
            Log::info("Blocked by robots.txt: {$this->site->url}");

            CrawlError::create([
                'site_id' => $this->site->id,
                'category' => CrawlErrorCategory::RobotsBlocked,
                'message' => 'Crawling disallowed by robots.txt',
                'url' => $this->site->url,
            ]);

            $failures = $this->site->consecutive_failures + 1;

            $this->site->update([
                'last_attempted_at' => now(),
                'status' => 'pending',
                'consecutive_failures' => $failures,
                'is_active' => $failures < Site::MAX_CONSECUTIVE_FAILURES,
            ]);

            if ($failures >= Site::MAX_CONSECUTIVE_FAILURES) {
                Log::info("Deactivated {$this->site->url} after {$failures} consecutive failures");
            }

            CrawlCompleted::dispatch(
                $this->site->id, 0, 0, null, null,
                $this->site->domain, $this->site->slug, $this->site->category,
                [], true, null, null,
                CrawlErrorCategory::RobotsBlocked->label(),
            );
            QueueUpdated::dispatch(Site::query()->crawlQueue()->count());
            self::dispatchNext();

            return;
        }

        Log::info("Starting crawl for site: {$this->site->url}");

        $this->site->update(['status' => 'crawling']);

        $crawlStartedAt = hrtime(true);

        CrawlStarted::dispatch($this->site->id, $this->site->url, $this->site->name, $this->site->slug, $this->site->source);

        // Pre-flight: collect HTTP metadata first to detect fatal connection errors
        // before wasting time launching Chrome/Puppeteer
        $httpMetadata = null;
        try {
            CrawlProgress::dispatch($this->site->id, 'collecting_metadata', 'Collecting server metadata...');
            $httpMetadata = $httpMetadataCollector->collect($this->site->url);
        } catch (\Throwable $e) {
            $errorCategory = CrawlErrorCategory::fromThrowable($e);
            Log::warning("Failed to collect HTTP metadata for {$this->site->url}: {$e->getMessage()}");

            // Fatal connection error (SSL, DNS, timeout, connection refused) — bail immediately
            if ($errorCategory->isFatalConnection()) {
                Log::info("Fatal connection error for {$this->site->url} — skipping Chrome fetch", [
                    'category' => $errorCategory->value,
                ]);

                $crawlError = CrawlError::create([
                    'site_id' => $this->site->id,
                    'category' => $errorCategory,
                    'message' => mb_substr($e->getMessage(), 0, 1000),
                    'url' => $this->site->url,
                ]);

                $failures = $this->site->consecutive_failures + 1;
                $durationMs = (int) round((hrtime(true) - $crawlStartedAt) / 1_000_000);

                $crawlResult = CrawlResult::create([
                    'site_id' => $this->site->id,
                    'ai_mention_count' => 0,
                    'pages_crawled' => 0,
                    'crawl_duration_ms' => $durationMs,
                ]);

                $crawlError->update(['crawl_result_id' => $crawlResult->id]);

                $this->site->update([
                    'last_attempted_at' => now(),
                    'status' => 'pending',
                    'consecutive_failures' => $failures,
                    'is_active' => $failures < Site::MAX_CONSECUTIVE_FAILURES,
                ]);

                if ($failures >= Site::MAX_CONSECUTIVE_FAILURES) {
                    Log::info("Deactivated {$this->site->url} after {$failures} consecutive failures");
                }

                CrawlCompleted::dispatch(
                    $this->site->id, 0, 0, null, $durationMs,
                    $this->site->domain, $this->site->slug, $this->site->category,
                    [], true, null, null, $errorCategory->label(),
                );
                QueueUpdated::dispatch(Site::query()->crawlQueue()->count());
                self::dispatchNext();

                return;
            }
        }

        CrawlProgress::dispatch($this->site->id, 'fetching', 'Fetching homepage...');

        $observer = new \App\Crawlers\AiMentionCrawlObserver($this->site);

        // Start Chrome HTML fetch
        $htmlProcess = null;
        $html = null;
        $fetchError = null;
        try {
            $htmlProcess = $screenshotService->startHtmlFetch($this->site->url);
        } catch (\Throwable $e) {
            Log::warning("Failed to start HTML fetch for {$this->site->url}: {$e->getMessage()}");
            $fetchError = CrawlError::create([
                'site_id' => $this->site->id,
                'category' => CrawlErrorCategory::fromThrowable($e),
                'message' => mb_substr($e->getMessage(), 0, 1000),
                'url' => $this->site->url,
            ]);
        }

        // Collect the HTML result from Chrome
        if ($htmlProcess && ! $fetchError) {
            try {
                $html = $screenshotService->collectHtmlResult($htmlProcess);
            } catch (\Throwable $e) {
                Log::warning("Failed to fetch HTML for {$this->site->url}: {$e->getMessage()}");
                $fetchError = CrawlError::create([
                    'site_id' => $this->site->id,
                    'category' => CrawlErrorCategory::fromThrowable($e),
                    'message' => mb_substr($e->getMessage(), 0, 1000),
                    'url' => $this->site->url,
                ]);
            }
        }

        // Detect Cloudflare/bot challenge pages before wasting time on analysis
        if ($html && self::isChallengePage($html)) {
            Log::info("Cloudflare challenge detected for {$this->site->url} — marking as blocked");

            $fetchError = CrawlError::create([
                'site_id' => $this->site->id,
                'category' => CrawlErrorCategory::CloudflareBlocked,
                'message' => 'Cloudflare challenge page detected — site blocks automated browsers',
                'url' => $this->site->url,
            ]);

            // Treat as no HTML — the challenge page isn't real content
            $html = null;
        }

        if ($html) {
            $observer->analyzeHtml($html);
        }

        // Geolocate server IP
        $coordinates = null;
        if ($httpMetadata['server_ip'] ?? null) {
            try {
                $coordinates = $ipGeolocationService->geolocate($httpMetadata['server_ip']);
                if (! $coordinates) {
                    Log::info("Geolocation returned no coordinates for {$this->site->url} (IP: {$httpMetadata['server_ip']})");
                }
            } catch (\Throwable $e) {
                Log::warning("Failed to geolocate IP for {$this->site->url}: {$e->getMessage()}");
            }
        } else {
            Log::info("No server IP resolved for {$this->site->url} — skipping geolocation");
        }

        // Detect category first so the internal metadata cache is warm for title/description
        if ($html && $this->site->category === 'other') {
            CrawlProgress::dispatch($this->site->id, 'detecting_category', 'Detecting site category...');
            $detectedCategory = $categoryDetector->detect($html);
            $this->site->update(['category' => $detectedCategory->value]);
            Log::info("Category detection for {$this->site->url}", ['detected' => $detectedCategory->value]);
        }

        // Extract page metadata and detect tech stack
        $techStack = [];
        $pageTitle = null;
        $metaDescription = null;
        if ($html) {
            $techStack = $techStackDetector->detect($html, $httpMetadata['headers'] ?? []);
            $pageTitle = $categoryDetector->extractTitle($html);
            $metaDescription = $categoryDetector->extractDescription($html);
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
            'total_word_count' => $observer->getTotalWordCount(),
            'pages_crawled' => $observer->getPagesCrawled(),
            'computed_styles' => $observer->getComputedStyles(),
            'animation_count' => $observer->getAnimationCount(),
            'glow_effect_count' => $observer->getGlowEffectCount(),
            'rainbow_border_count' => $observer->getRainbowBorderCount(),
            'redirect_chain' => $httpMetadata['redirect_chain'] ?? null,
            'final_url' => mb_substr($httpMetadata['final_url'] ?? '', 0, 2048) ?: null,
            'response_time_ms' => $httpMetadata['response_time_ms'] ?? null,
            'html_size_bytes' => $html ? strlen($html) : null,
            'detected_tech_stack' => $techStack ?: null,
        ]);

        // Link the HTML fetch error to this crawl result now that it exists
        if ($fetchError) {
            $fetchError->update(['crawl_result_id' => $crawlResult->id]);
        }

        // Record any page-level crawl errors collected by the observer
        foreach ($observer->getErrors() as $observerError) {
            CrawlError::create([
                'site_id' => $this->site->id,
                'crawl_result_id' => $crawlResult->id,
                'category' => CrawlErrorCategory::fromThrowable($observerError['exception']),
                'message' => mb_substr($observerError['exception']->getMessage(), 0, 1000),
                'url' => $observerError['url'],
            ]);
        }

        if (! $html) {
            Log::warning("No HTML captured for {$this->site->url} — crawler may have been blocked");
        }

        // Calculate scores
        CrawlProgress::dispatch($this->site->id, 'calculating_score', 'Calculating hype score...', [
            'ai_mention_count' => $crawlResult->ai_mention_count,
            'animation_count' => $crawlResult->animation_count,
        ]);

        $scores = $calculator->calculate(
            $crawlResult->mention_details ?? [],
            $crawlResult->animation_count ?? 0,
            $crawlResult->glow_effect_count ?? 0,
            $crawlResult->rainbow_border_count ?? 0,
            $crawlResult->total_word_count ?? 0,
        );

        $crawlResult->update([
            'total_score' => $scores['total_score'],
            'density_score' => $scores['density_score'],
            'ai_density_percent' => $scores['ai_density_percent'],
            'mention_score' => $scores['mention_score'],
            'font_size_score' => $scores['font_size_score'],
            'animation_score' => $scores['animation_score'],
            'visual_effects_score' => $scores['visual_effects_score'],
        ]);

        $hypeScore = $scores['total_score'];

        // If no pages were crawled or no HTML captured, the site likely blocked us
        if ($observer->getPagesCrawled() === 0 || $html === null) {
            Log::info("Crawl blocked/failed for {$this->site->url} — marking as failed attempt", [
                'pages_crawled' => $observer->getPagesCrawled(),
                'has_html' => $html !== null,
            ]);

            // Only record if we don't already have a fetch error explaining the failure
            if (! $fetchError) {
                CrawlError::create([
                    'site_id' => $this->site->id,
                    'crawl_result_id' => $crawlResult->id,
                    'category' => $html === null ? CrawlErrorCategory::EmptyResponse : CrawlErrorCategory::Blocked,
                    'message' => $html === null ? 'No HTML captured' : 'No pages crawled — site may be blocking the crawler',
                    'url' => $this->site->url,
                ]);
            }

            $failures = $this->site->consecutive_failures + 1;

            $this->site->update([
                'last_attempted_at' => now(),
                'status' => 'pending',
                'consecutive_failures' => $failures,
                'is_active' => $failures < Site::MAX_CONSECUTIVE_FAILURES,
            ]);

            if ($failures >= Site::MAX_CONSECUTIVE_FAILURES) {
                Log::info("Deactivated {$this->site->url} after {$failures} consecutive failures");
            }

            $failedDurationMs = (int) round((hrtime(true) - $crawlStartedAt) / 1_000_000);
            $crawlResult->update(['crawl_duration_ms' => $failedDurationMs]);

            $errorCategory = $crawlResult->crawlErrors()->latest()->first()?->category?->label();

            CrawlCompleted::dispatch(
                $this->site->id,
                0,
                0,
                null,
                $failedDurationMs,
                $this->site->domain,
                $this->site->slug,
                $this->site->category,
                [],
                true,
                null,
                null,
                $errorCategory,
            );
            QueueUpdated::dispatch(Site::query()->crawlQueue()->count());
            self::dispatchNext();

            return;
        }

        // Run axe-core accessibility audit inline (fast, ~3s)
        try {
            Log::info("Running axe-core audit for site: {$this->site->url}");
            $axeResults = $axeAuditService->audit($this->site->domain);
            if ($axeResults) {
                $crawlResult->update([
                    'axe_violations_count' => $axeResults['violations_count'],
                    'axe_passes_count' => $axeResults['passes_count'],
                    'axe_violations_summary' => $axeResults['violations_summary'],
                ]);
                Log::info("axe-core audit completed for site: {$this->site->url}", [
                    'violations' => $axeResults['violations_count'],
                    'passes' => $axeResults['passes_count'],
                ]);
            } else {
                Log::warning("axe-core audit returned no results for {$this->site->url}");
            }
        } catch (\Throwable $e) {
            Log::warning("axe-core audit failed for {$this->site->url}: {$e->getMessage()}");
        }

        // Calculate crawl duration before dispatching screenshot (async, not part of crawl time)
        $crawlDurationMs = (int) round((hrtime(true) - $crawlStartedAt) / 1_000_000);
        $crawlResult->update(['crawl_duration_ms' => $crawlDurationMs]);

        $this->site->update([
            'hype_score' => $hypeScore,
            'last_crawled_at' => now(),
            'last_attempted_at' => now(),
            'status' => 'completed',
            'consecutive_failures' => 0,
            'tech_stack' => $techStack ?: null,
            'server_ip' => $httpMetadata['server_ip'] ?? null,
            'server_software' => $httpMetadata['server_software'] ?? null,
            'tls_issuer' => $httpMetadata['tls_issuer'] ?? null,
            'page_title' => $pageTitle,
            'meta_description' => $metaDescription,
            'latitude' => $coordinates['latitude'] ?? null,
            'longitude' => $coordinates['longitude'] ?? null,
        ]);

        // Generate screenshot inline — runs on the same queue so no parallelism benefit from async
        CrawlProgress::dispatch($this->site->id, 'generating_screenshot', 'Generating screenshot...');

        $screenshotPath = null;
        try {
            $oldScreenshotPath = $this->site->getRawOriginal('screenshot_path');
            $screenshotPath = $screenshotService->capture($this->site->url);
            $this->site->update(['screenshot_path' => $screenshotPath]);

            if ($oldScreenshotPath && Storage::disk('public')->exists($oldScreenshotPath)) {
                Storage::disk('public')->delete($oldScreenshotPath);
            }
        } catch (\Throwable $e) {
            Log::warning("Screenshot failed for {$this->site->url}: {$e->getMessage()}");
        }

        CrawlProgress::dispatch($this->site->id, 'finishing', 'Running final checks...', [
            'hype_score' => $hypeScore,
        ]);

        // Dispatch Lighthouse audit asynchronously — LighthouseComplete event will notify the frontend
        RunLighthouseJob::dispatch($this->site);

        $aiTerms = collect($crawlResult->mention_details ?? [])
            ->pluck('text')
            ->map(fn ($t) => mb_strtolower(trim($t)))
            ->unique()
            ->values()
            ->all();

        $hasError = $fetchError !== null || $observer->getErrors() !== [];

        CrawlCompleted::dispatch(
            $this->site->id,
            $hypeScore,
            $crawlResult->ai_mention_count,
            $this->site->screenshot_path,
            $crawlDurationMs,
            $this->site->domain,
            $this->site->slug,
            $this->site->category,
            $aiTerms,
            $hasError,
            $this->site->latitude,
            $this->site->longitude,
            $hasError ? $fetchError?->category?->label() : null,
        );
        QueueUpdated::dispatch(Site::query()->crawlQueue()->count());

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

        if ($exception) {
            CrawlError::create([
                'site_id' => $this->site->id,
                'category' => CrawlErrorCategory::fromThrowable($exception),
                'message' => mb_substr($exception->getMessage(), 0, 1000),
                'url' => $this->site->url,
            ]);
        }

        $failures = $this->site->consecutive_failures + 1;

        $this->site->update([
            'status' => 'pending',
            'last_attempted_at' => now(),
            'consecutive_failures' => $failures,
            'is_active' => $failures < Site::MAX_CONSECUTIVE_FAILURES,
        ]);

        if ($failures >= Site::MAX_CONSECUTIVE_FAILURES) {
            Log::info("Deactivated {$this->site->url} after {$failures} consecutive failures");
        }

        try {
            QueueUpdated::dispatch(Site::query()->crawlQueue()->count());
        } catch (\Throwable $e) {
            Log::warning("Failed to broadcast QueueUpdated: {$e->getMessage()}");
        }

        self::dispatchNext();
    }

    /**
     * Detect Cloudflare Turnstile, hCaptcha, and other bot challenge pages.
     */
    private static function isChallengePage(string $html): bool
    {
        $markers = [
            'Performing security verification',
            'Verify you are human',
            'challenges.cloudflare.com',
            'cdn-cgi/challenge-platform',
            'Just a moment...',
            'cf-challenge-running',
            'cf_chl_opt',
        ];

        $htmlLower = mb_strtolower($html);

        foreach ($markers as $marker) {
            if (str_contains($htmlLower, mb_strtolower($marker))) {
                return true;
            }
        }

        return false;
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

            return;
        }

        // Backfill: re-crawl sites missing category or screenshot during downtime
        $backfillSite = Site::query()
            ->needsBackfill()
            ->first();

        if ($backfillSite) {
            Log::info("CrawlSiteJob: Backfilling {$backfillSite->url} (missing data)");
            self::dispatch($backfillSite, backfill: true);

            return;
        }

        // Nothing to crawl or backfill — discover new sites to keep the chain alive
        Log::info('CrawlSiteJob: No sites in queue — triggering discovery');
        DiscoverSitesJob::dispatch();
    }
}
