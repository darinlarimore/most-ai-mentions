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
    /** @var list<array{text: string, font_size: int|float, has_animation: bool, has_glow: bool, context: string, source: string}> */
    private array $mentionDetails = [];

    /** @var array<string, true> Track seen context hashes to deduplicate nav/footer repeats across pages */
    private array $seenContexts = [];

    private int $pagesCrawled = 0;

    private int $animationCount = 0;

    private int $glowEffectCount = 0;

    private int $rainbowBorderCount = 0;

    private int $totalWordCount = 0;

    private ?string $crawledHtml = null;

    /** @var array<string, int> */
    private array $styleSummary = [];

    /** @var list<array{url: string, exception: \Throwable}> */
    private array $errors = [];

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

        $this->errors[] = [
            'url' => (string) $url,
            'exception' => $requestException,
        ];
    }

    /**
     * @return list<array{url: string, exception: \Throwable}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<array{text: string, font_size: int|float, has_animation: bool, has_glow: bool, context: string, source: string}>
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

    /**
     * Analyze pre-fetched HTML directly (bypasses Spatie Crawler).
     */
    public function analyzeHtml(string $html): void
    {
        $this->pagesCrawled++;
        $this->crawledHtml = $html;
        $this->extractMentions($html);
        $this->extractVisualEffects($html);
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
    public function getTotalWordCount(): int
    {
        return $this->totalWordCount;
    }

    private function extractMentions(string $html): void
    {
        $visibleText = $this->extractVisibleBodyText($html);

        $this->totalWordCount += str_word_count($visibleText);

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
                    'source' => 'body',
                ];
            }
        }

        // Extract title/meta mentions only on the first page to avoid duplicates
        if ($this->pagesCrawled === 1) {
            $this->extractMetaMentions($html);
        }
    }

    /**
     * Extract AI keyword mentions from the title tag and meta description.
     */
    private function extractMetaMentions(string $html): void
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR | LIBXML_NOWARNING);

        $sources = [];

        $titleElements = $dom->getElementsByTagName('title');
        if ($titleElements->length > 0) {
            $titleText = trim($titleElements->item(0)->textContent);
            if ($titleText !== '') {
                $sources['title'] = $titleText;
            }
        }

        $metaTags = $dom->getElementsByTagName('meta');
        for ($i = 0; $i < $metaTags->length; $i++) {
            $meta = $metaTags->item($i);
            if (mb_strtolower($meta->getAttribute('name')) === 'description') {
                $content = trim($meta->getAttribute('content'));
                if ($content !== '') {
                    $sources['meta_description'] = $content;
                }
                break;
            }
        }

        foreach ($sources as $source => $text) {
            foreach (HypeScoreCalculator::AI_KEYWORDS as $keyword) {
                $pattern = '/\b'.preg_quote($keyword, '/').'\b/iu';
                $matchCount = preg_match_all($pattern, $text, $matches);

                if ($matchCount === false || $matchCount === 0) {
                    continue;
                }

                foreach ($matches[0] as $matchText) {
                    $this->mentionDetails[] = [
                        'text' => $matchText,
                        'font_size' => 0,
                        'has_animation' => false,
                        'has_glow' => false,
                        'context' => $text,
                        'source' => $source,
                    ];
                }
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
     * Extract visible body text using DOMDocument.
     *
     * Removes non-visible elements: head, script, style, noscript, template,
     * svg, iframe, and elements with hidden/aria-hidden attributes or
     * inline display:none/visibility:hidden styles.
     */
    private function extractVisibleBodyText(string $html): string
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR | LIBXML_NOWARNING);

        $this->removeInvisibleNodes($dom);

        $body = $dom->getElementsByTagName('body')->item(0);
        if (! $body) {
            return '';
        }

        $text = $body->textContent;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Recursively remove invisible DOM nodes.
     */
    private function removeInvisibleNodes(\DOMDocument $dom): void
    {
        $tagsToRemove = ['head', 'script', 'style', 'noscript', 'template', 'svg', 'iframe'];

        foreach ($tagsToRemove as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            $toRemove = [];
            for ($i = 0; $i < $elements->length; $i++) {
                $toRemove[] = $elements->item($i);
            }
            foreach ($toRemove as $el) {
                $el->parentNode?->removeChild($el);
            }
        }

        $xpath = new \DOMXPath($dom);

        $hiddenNodes = $xpath->query('//*[@hidden]|//*[@aria-hidden="true"]');
        if ($hiddenNodes) {
            $toRemove = [];
            foreach ($hiddenNodes as $node) {
                $toRemove[] = $node;
            }
            foreach ($toRemove as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $allElements = $xpath->query('//*[@style]');
        if ($allElements) {
            $toRemove = [];
            foreach ($allElements as $el) {
                $style = $el->getAttribute('style');
                if (preg_match('/display\s*:\s*none/i', $style) || preg_match('/visibility\s*:\s*hidden/i', $style)) {
                    $toRemove[] = $el;
                }
            }
            foreach ($toRemove as $el) {
                $el->parentNode?->removeChild($el);
            }
        }
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
