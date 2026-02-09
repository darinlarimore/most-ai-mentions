<?php

namespace App\Crawlers;

use App\Models\Site;
use App\Services\HypeScoreCalculator;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Spatie\Crawler\CrawlObservers\CrawlObserver;

class AiMentionCrawlObserver extends CrawlObserver
{
    /** @var list<array{text: string, font_size: int|float, has_animation: bool, has_glow: bool, context: string}> */
    private array $mentionDetails = [];

    /** @var array<string, true> Track seen context hashes to deduplicate nav/footer repeats across pages */
    private array $seenContexts = [];

    private int $pagesCrawled = 0;

    private int $animationCount = 0;

    private int $glowEffectCount = 0;

    private int $rainbowBorderCount = 0;

    private ?string $crawledHtml = null;

    /** @var array<string, int> */
    private array $styleSummary = [];

    /**
     * Estimated font sizes for heading tags when no inline style is present.
     *
     * @var array<string, int>
     */
    private const HEADING_FONT_SIZES = [
        'h1' => 36,
        'h2' => 30,
        'h3' => 24,
        'h4' => 20,
        'h5' => 16,
        'h6' => 14,
    ];

    public function __construct(
        private readonly Site $site,
    ) {}

    public function crawled(
        UriInterface $url,
        ResponseInterface $response,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null,
    ): void {
        $statusCode = $response->getStatusCode();
        $contentType = $response->getHeaderLine('Content-Type');

        Log::info("Crawled page: {$url}", [
            'site' => $this->site->domain,
            'status' => $statusCode,
            'content_type' => $contentType,
        ]);

        if (! str_contains($contentType, 'text/html')) {
            return;
        }

        $html = (string) $response->getBody();
        if ($html === '') {
            Log::warning("Empty HTML body for {$url} on site {$this->site->domain}");

            return;
        }

        $this->pagesCrawled++;

        // Store only the first page's HTML for annotation
        if ($this->crawledHtml === null) {
            $this->crawledHtml = $html;
        }

        $this->extractMentions($html);
        $this->extractVisualEffects($html);
    }

    public function crawlFailed(
        UriInterface $url,
        RequestException $requestException,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null,
    ): void {
        Log::warning("Crawl failed for {$url} on site {$this->site->url}: cURL error {$requestException->getCode()}: {$requestException->getMessage()}");
    }

    /**
     * @return list<array{text: string, font_size: int|float, has_animation: bool, has_glow: bool, context: string}>
     */
    public function getMentionDetails(): array
    {
        return $this->mentionDetails;
    }

    public function getAiMentionCount(): int
    {
        return count($this->mentionDetails);
    }

    public function getPagesCrawled(): int
    {
        return $this->pagesCrawled;
    }

    /**
     * @return array{animation_count: int, glow_effect_count: int, rainbow_border_count: int, pages_crawled: int}
     */
    public function getComputedStyles(): array
    {
        return [
            'animation_count' => $this->animationCount,
            'glow_effect_count' => $this->glowEffectCount,
            'rainbow_border_count' => $this->rainbowBorderCount,
            'pages_crawled' => $this->pagesCrawled,
        ];
    }

    public function getCrawledHtml(): ?string
    {
        return $this->crawledHtml;
    }

    public function getAnimationCount(): int
    {
        return $this->animationCount;
    }

    public function getGlowEffectCount(): int
    {
        return $this->glowEffectCount;
    }

    public function getRainbowBorderCount(): int
    {
        return $this->rainbowBorderCount;
    }

    /**
     * Extract AI keyword mentions from the HTML body text.
     *
     * Uses regex-based matching against HypeScoreCalculator::AI_KEYWORDS
     * to avoid loading a full DOM tree into memory.
     */
    private function extractMentions(string $html): void
    {
        // Strip HTML tags to get visible text, but keep tag structure for font-size detection
        $visibleText = $this->stripToVisibleText($html);

        foreach (HypeScoreCalculator::AI_KEYWORDS as $keyword) {
            $pattern = '/\b'.preg_quote($keyword, '/').'\b/iu';
            $matchCount = preg_match_all($pattern, $visibleText, $matches, PREG_OFFSET_CAPTURE);

            if ($matchCount === false || $matchCount === 0) {
                continue;
            }

            foreach ($matches[0] as $match) {
                $matchText = $match[0];
                $offset = $match[1];

                $context = $this->extractContext($visibleText, $offset, mb_strlen($matchText));

                // Deduplicate: skip if we've seen this exact keyword+context before (e.g. repeated nav menus)
                $contextHash = md5(mb_strtolower($matchText).'|'.$context);
                if (isset($this->seenContexts[$contextHash])) {
                    continue;
                }
                $this->seenContexts[$contextHash] = true;

                $fontSize = $this->estimateFontSize($html, $matchText);
                $hasAnimation = $this->mentionHasAnimation($html, $matchText);
                $hasGlow = $this->mentionHasGlow($html, $matchText);

                $this->mentionDetails[] = [
                    'text' => $matchText,
                    'font_size' => $fontSize,
                    'has_animation' => $hasAnimation,
                    'has_glow' => $hasGlow,
                    'context' => $context,
                ];
            }
        }
    }

    /**
     * Detect page-wide visual effects from CSS in the HTML.
     */
    private function extractVisualEffects(string $html): void
    {
        $this->detectAnimations($html);
        $this->detectGlowEffects($html);
        $this->detectRainbowBorders($html);
    }

    /**
     * Count CSS animations and transitions on the page.
     */
    private function detectAnimations(string $html): void
    {
        // Count @keyframes declarations
        $this->animationCount += preg_match_all('/@keyframes\s+/i', $html);

        // Count animation property usages (but not animation-delay etc. that aren't new animations)
        $this->animationCount += preg_match_all('/animation\s*:\s*(?!none)/i', $html);
        $this->animationCount += preg_match_all('/animation-name\s*:\s*(?!none)/i', $html);
    }

    /**
     * Count glow effects (box-shadow and text-shadow with visible blur).
     */
    private function detectGlowEffects(string $html): void
    {
        // box-shadow with blur radius > 0: box-shadow: X Y BLUR ...
        $this->glowEffectCount += preg_match_all(
            '/box-shadow\s*:[^;]*?\d+px\s+\d+px\s+([1-9]\d*)px/i',
            $html
        );

        // text-shadow with blur radius > 0
        $this->glowEffectCount += preg_match_all(
            '/text-shadow\s*:[^;]*?\d+px\s+\d+px\s+([1-9]\d*)px/i',
            $html
        );
    }

    /**
     * Count rainbow/gradient borders on the page.
     */
    private function detectRainbowBorders(string $html): void
    {
        // border-image with gradient
        $this->rainbowBorderCount += preg_match_all(
            '/border-image\s*:[^;]*gradient/i',
            $html
        );

        // background with gradient used as border (common pattern with border-radius + padding)
        $this->rainbowBorderCount += preg_match_all(
            '/conic-gradient\s*\(/i',
            $html
        );
    }

    /**
     * Strip HTML to visible text content without loading DOM.
     */
    private function stripToVisibleText(string $html): string
    {
        // Remove script and style blocks
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html);
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $text);

        // Remove HTML tags
        $text = strip_tags($text);

        // Decode entities and normalize whitespace
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Extract a context snippet around a match.
     */
    private function extractContext(string $text, int $offset, int $matchLength): string
    {
        $contextRadius = 50;
        $start = max(0, $offset - $contextRadius);
        $length = $matchLength + ($contextRadius * 2);

        $snippet = mb_substr($text, $start, $length);

        if ($start > 0) {
            $snippet = '...'.$snippet;
        }
        if ($start + $length < mb_strlen($text)) {
            $snippet .= '...';
        }

        return $snippet;
    }

    /**
     * Estimate the font size of an element containing the keyword.
     *
     * Checks for inline styles and heading tags. Falls back to 16px default.
     */
    private function estimateFontSize(string $html, string $keyword): int|float
    {
        $escapedKeyword = preg_quote($keyword, '/');

        // Check if keyword appears inside a tag with inline font-size
        $pattern = '/<([a-z][a-z0-9]*)\b[^>]*style="[^"]*font-size\s*:\s*(\d+(?:\.\d+)?)\s*px[^"]*"[^>]*>[^<]*'.$escapedKeyword.'/iu';
        if (preg_match($pattern, $html, $match)) {
            return (float) $match[2];
        }

        // Check if keyword appears inside heading tags
        foreach (self::HEADING_FONT_SIZES as $tag => $size) {
            $headingPattern = '/<'.$tag.'\b[^>]*>.*?'.$escapedKeyword.'.*?<\/'.$tag.'>/ius';
            if (preg_match($headingPattern, $html)) {
                return $size;
            }
        }

        return 16;
    }

    /**
     * Check if the element containing this keyword has animation styles.
     */
    private function mentionHasAnimation(string $html, string $keyword): bool
    {
        $escapedKeyword = preg_quote($keyword, '/');

        $pattern = '/<[^>]*style="[^"]*animation[^"]*"[^>]*>[^<]*'.$escapedKeyword.'/iu';

        return (bool) preg_match($pattern, $html);
    }

    /**
     * Check if the element containing this keyword has glow styles.
     */
    private function mentionHasGlow(string $html, string $keyword): bool
    {
        $escapedKeyword = preg_quote($keyword, '/');

        // Check for text-shadow or box-shadow on the element containing the keyword
        $pattern = '/<[^>]*style="[^"]*(?:text-shadow|box-shadow)[^"]*"[^>]*>[^<]*'.$escapedKeyword.'/iu';

        return (bool) preg_match($pattern, $html);
    }
}
