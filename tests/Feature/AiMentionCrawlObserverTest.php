<?php

use App\Crawlers\AiMentionCrawlObserver;
use App\Models\Site;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;

function makeHtmlResponse(string $html): Response
{
    return new Response(200, ['Content-Type' => 'text/html'], $html);
}

function createObserverAndCrawl(string $html): AiMentionCrawlObserver
{
    $site = Site::factory()->create();
    $observer = new AiMentionCrawlObserver($site);
    $observer->crawled(new Uri('https://example.com'), makeHtmlResponse($html));

    return $observer;
}

it('detects AI keyword mentions in page text', function () {
    $html = '<html><body><p>Our AI-powered platform uses machine learning to transform your workflow.</p></body></html>';

    $observer = createObserverAndCrawl($html);

    expect($observer->getAiMentionCount())->toBe(2)
        ->and($observer->getMentionDetails())->toHaveCount(2);

    $keywords = array_column($observer->getMentionDetails(), 'text');
    expect($keywords)->toContain('AI-powered')
        ->and($keywords)->toContain('machine learning');
});

it('detects keywords case-insensitively', function () {
    $html = '<html><body><p>ARTIFICIAL INTELLIGENCE is great. We love deep learning.</p></body></html>';

    $observer = createObserverAndCrawl($html);

    expect($observer->getAiMentionCount())->toBe(2);

    $keywords = array_map('mb_strtolower', array_column($observer->getMentionDetails(), 'text'));
    expect($keywords)->toContain('artificial intelligence')
        ->and($keywords)->toContain('deep learning');
});

it('returns correct mention detail structure', function () {
    $html = '<html><body><h1>GPT is everywhere</h1></body></html>';

    $observer = createObserverAndCrawl($html);

    expect($observer->getMentionDetails())->toHaveCount(1);

    $mention = $observer->getMentionDetails()[0];
    expect($mention)->toHaveKeys(['text', 'font_size', 'has_animation', 'has_glow', 'context', 'source'])
        ->and($mention['text'])->toBe('GPT')
        ->and($mention['source'])->toBe('body');
});

it('estimates heading font sizes', function () {
    $html = '<html><body><h1>GPT technology</h1></body></html>';

    $observer = createObserverAndCrawl($html);
    $mention = $observer->getMentionDetails()[0];

    expect($mention['font_size'])->toBe(36);
});

it('extracts inline font-size styles', function () {
    $html = '<html><body><span style="font-size: 48px">ChatGPT rocks</span></body></html>';

    $observer = createObserverAndCrawl($html);
    $mention = $observer->getMentionDetails()[0];

    expect($mention['font_size'])->toBe(48.0);
});

it('defaults to 16px when no font size info', function () {
    $html = '<html><body><p>We use generative AI daily.</p></body></html>';

    $observer = createObserverAndCrawl($html);
    $mention = $observer->getMentionDetails()[0];

    expect($mention['font_size'])->toBe(16);
});

it('counts pages crawled', function () {
    $site = Site::factory()->create();
    $observer = new AiMentionCrawlObserver($site);

    $observer->crawled(new Uri('https://example.com'), makeHtmlResponse('<html><body>Page 1</body></html>'));
    $observer->crawled(new Uri('https://example.com/about'), makeHtmlResponse('<html><body>Page 2</body></html>'));

    expect($observer->getPagesCrawled())->toBe(2);
});

it('skips non-html responses', function () {
    $site = Site::factory()->create();
    $observer = new AiMentionCrawlObserver($site);

    $jsonResponse = new Response(200, ['Content-Type' => 'application/json'], '{"key": "value"}');
    $observer->crawled(new Uri('https://example.com/api'), $jsonResponse);

    expect($observer->getPagesCrawled())->toBe(0);
});

it('detects CSS animations', function () {
    $html = '<html><head><style>@keyframes spin { from { transform: rotate(0); } to { transform: rotate(360deg); } } .hero { animation: spin 2s infinite; }</style></head><body><p>Hello</p></body></html>';

    $observer = createObserverAndCrawl($html);

    expect($observer->getAnimationCount())->toBeGreaterThan(0);
});

it('detects glow effects from box-shadow', function () {
    $html = '<html><head><style>.card { box-shadow: 0px 0px 20px cyan; }</style></head><body><p>Hello</p></body></html>';

    $observer = createObserverAndCrawl($html);

    expect($observer->getGlowEffectCount())->toBeGreaterThan(0);
});

it('detects rainbow gradient borders', function () {
    $html = '<html><head><style>.card { border-image: conic-gradient(red, yellow, green, blue) 1; }</style></head><body><p>Hello</p></body></html>';

    $observer = createObserverAndCrawl($html);

    expect($observer->getRainbowBorderCount())->toBeGreaterThan(0);
});

it('detects inline animation on mention', function () {
    $html = '<html><body><span style="animation: pulse 1s infinite">AI-powered</span></body></html>';

    $observer = createObserverAndCrawl($html);
    $mention = $observer->getMentionDetails()[0];

    expect($mention['has_animation'])->toBeTrue();
});

it('detects inline glow on mention', function () {
    $html = '<html><body><span style="text-shadow: 0 0 10px cyan">ChatGPT</span></body></html>';

    $observer = createObserverAndCrawl($html);
    $mention = $observer->getMentionDetails()[0];

    expect($mention['has_glow'])->toBeTrue();
});

it('stores only the first page html', function () {
    $site = Site::factory()->create();
    $observer = new AiMentionCrawlObserver($site);

    $observer->crawled(new Uri('https://example.com'), makeHtmlResponse('<html><body>First page</body></html>'));
    $observer->crawled(new Uri('https://example.com/about'), makeHtmlResponse('<html><body>Second page</body></html>'));

    expect($observer->getCrawledHtml())->toContain('First page')
        ->and($observer->getCrawledHtml())->not->toContain('Second page');
});

it('provides context snippet around mentions', function () {
    $html = '<html><body><p>Welcome to our amazing AI-powered platform for everyone.</p></body></html>';

    $observer = createObserverAndCrawl($html);
    $mention = $observer->getMentionDetails()[0];

    expect($mention['context'])->toContain('AI-powered')
        ->and(strlen($mention['context']))->toBeGreaterThan(strlen('AI-powered'));
});

it('returns computed styles summary', function () {
    $html = '<html><head><style>@keyframes glow { } .x { animation: glow 1s; box-shadow: 0px 0px 15px red; }</style></head><body><p>Hello</p></body></html>';

    $observer = createObserverAndCrawl($html);
    $styles = $observer->getComputedStyles();

    expect($styles)->toHaveKeys(['animation_count', 'glow_effect_count', 'rainbow_border_count', 'pages_crawled']);
});

it('accumulates mentions across multiple pages', function () {
    $site = Site::factory()->create();
    $observer = new AiMentionCrawlObserver($site);

    $observer->crawled(new Uri('https://example.com'), makeHtmlResponse('<html><body><p>We use GPT models.</p></body></html>'));
    $observer->crawled(new Uri('https://example.com/about'), makeHtmlResponse('<html><body><p>Our ChatGPT integration.</p></body></html>'));

    expect($observer->getAiMentionCount())->toBe(2)
        ->and($observer->getPagesCrawled())->toBe(2);
});

it('ignores keywords inside script and style tags', function () {
    $html = '<html><head><style>.ai-powered { color: red; }</style><script>var model = "machine learning";</script></head><body><p>No AI here.</p></body></html>';

    $observer = createObserverAndCrawl($html);

    // Only body mentions; no title/meta mentions either since no relevant tags
    expect(collect($observer->getMentionDetails())->where('source', 'body'))->toHaveCount(0);
});

it('strips head, noscript, and template tags from visible text', function () {
    $html = '<html><head><title>AI-powered site</title></head><body><noscript>machine learning fallback</noscript><template><p>deep learning template</p></template><p>Hello world.</p></body></html>';

    $observer = createObserverAndCrawl($html);

    $bodyMentions = collect($observer->getMentionDetails())->where('source', 'body');
    expect($bodyMentions)->toHaveCount(0);
    expect($observer->getTotalWordCount())->toBe(2); // "Hello world"
});

it('strips hidden and aria-hidden elements from visible text', function () {
    $html = '<html><body><div hidden>AI-powered hidden div</div><div aria-hidden="true">machine learning screen reader hidden</div><p>Visible content only.</p></body></html>';

    $observer = createObserverAndCrawl($html);

    $bodyMentions = collect($observer->getMentionDetails())->where('source', 'body');
    expect($bodyMentions)->toHaveCount(0);
});

it('strips elements with inline display:none and visibility:hidden', function () {
    $html = '<html><body><span style="display:none">GPT invisible</span><span style="visibility: hidden">ChatGPT hidden</span><p>Visible text here.</p></body></html>';

    $observer = createObserverAndCrawl($html);

    $bodyMentions = collect($observer->getMentionDetails())->where('source', 'body');
    expect($bodyMentions)->toHaveCount(0);
});

it('detects AI keywords in title tag with source title', function () {
    $html = '<html><head><title>AI-powered Platform - Machine Learning Solutions</title></head><body><p>Welcome to our site.</p></body></html>';

    $observer = createObserverAndCrawl($html);

    $titleMentions = collect($observer->getMentionDetails())->where('source', 'title');
    expect($titleMentions)->toHaveCount(2);

    $keywords = $titleMentions->pluck('text')->map(fn ($t) => mb_strtolower($t))->all();
    expect($keywords)->toContain('ai-powered')
        ->and($keywords)->toContain('machine learning');
});

it('detects AI keywords in meta description with source meta_description', function () {
    $html = '<html><head><meta name="description" content="Build with generative AI and deep learning models."></head><body><p>Welcome.</p></body></html>';

    $observer = createObserverAndCrawl($html);

    $metaMentions = collect($observer->getMentionDetails())->where('source', 'meta_description');
    expect($metaMentions)->toHaveCount(2);

    $keywords = $metaMentions->pluck('text')->map(fn ($t) => mb_strtolower($t))->all();
    expect($keywords)->toContain('generative ai')
        ->and($keywords)->toContain('deep learning');
});

it('sets font_size to 0 for title and meta mentions', function () {
    $html = '<html><head><title>GPT Revolution</title><meta name="description" content="ChatGPT is the future."></head><body><p>Hello.</p></body></html>';

    $observer = createObserverAndCrawl($html);

    $nonBodyMentions = collect($observer->getMentionDetails())->whereIn('source', ['title', 'meta_description']);
    expect($nonBodyMentions)->not->toBeEmpty();

    foreach ($nonBodyMentions as $mention) {
        expect($mention['font_size'])->toBe(0)
            ->and($mention['has_animation'])->toBeFalse()
            ->and($mention['has_glow'])->toBeFalse();
    }
});

it('only extracts meta mentions from the first page', function () {
    $site = Site::factory()->create();
    $observer = new AiMentionCrawlObserver($site);

    $html1 = '<html><head><title>GPT Platform</title></head><body><p>Page one.</p></body></html>';
    $html2 = '<html><head><title>GPT Platform</title></head><body><p>Page two.</p></body></html>';

    $observer->crawled(new Uri('https://example.com'), makeHtmlResponse($html1));
    $observer->crawled(new Uri('https://example.com/about'), makeHtmlResponse($html2));

    $titleMentions = collect($observer->getMentionDetails())->where('source', 'title');
    expect($titleMentions)->toHaveCount(1);
});

it('does not count hidden element text toward word count', function () {
    $html = '<html><body><div hidden>This is some hidden text that should not count</div><p>Two words.</p></body></html>';

    $observer = createObserverAndCrawl($html);

    expect($observer->getTotalWordCount())->toBe(2); // "Two words"
});
