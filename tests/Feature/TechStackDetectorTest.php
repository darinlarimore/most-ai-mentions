<?php

use App\Services\TechStackDetector;

beforeEach(function () {
    $this->detector = app(TechStackDetector::class);
});

it('detects WordPress from meta generator', function () {
    $html = '<html><head><meta name="generator" content="WordPress 6.5"></head><body></body></html>';

    expect($this->detector->detect($html))->toContain('WordPress');
});

it('detects React and Next.js from HTML attributes', function () {
    $html = '<html><body><div id="__next"><div data-reactroot>Hello</div></div></body></html>';

    $result = $this->detector->detect($html);

    expect($result)
        ->toContain('Next.js')
        ->toContain('React');
});

it('detects Vue from script source', function () {
    $html = '<html><head><script src="/js/vue.min.js"></script></head><body></body></html>';

    expect($this->detector->detect($html))->toContain('Vue');
});

it('detects Tailwind CSS from utility classes', function () {
    $html = <<<'HTML'
    <html><body>
        <div class="flex items-center justify-center px-4 py-2">
            <span class="text-sm font-bold bg-blue-500 rounded-lg mt-2 mb-4">Hello</span>
        </div>
    </body></html>
    HTML;

    expect($this->detector->detect($html))->toContain('Tailwind CSS');
});

it('detects Cloudflare from server header', function () {
    $html = '<html><body>Hello</body></html>';

    $result = $this->detector->detect($html, ['server' => 'cloudflare']);

    expect($result)->toContain('Cloudflare');
});

it('detects PHP from x-powered-by header', function () {
    $html = '<html><body>Hello</body></html>';

    $result = $this->detector->detect($html, ['x-powered-by' => 'PHP/8.3']);

    expect($result)->toContain('PHP');
});

it('detects multiple technologies simultaneously', function () {
    $html = <<<'HTML'
    <html>
    <head>
        <meta name="generator" content="WordPress 6.5">
        <script src="/wp-content/themes/starter/js/app.js"></script>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    </head>
    <body>
        <div class="flex items-center px-4 py-2 bg-white rounded-lg mt-2 mb-4 font-bold text-sm">
            Content
        </div>
    </body>
    </html>
    HTML;

    $result = $this->detector->detect($html, [
        'server' => 'cloudflare',
        'x-powered-by' => 'PHP/8.3',
    ]);

    expect($result)
        ->toContain('Cloudflare')
        ->toContain('jQuery')
        ->toContain('PHP')
        ->toContain('Tailwind CSS')
        ->toContain('WordPress');
});

it('returns empty array for minimal HTML', function () {
    $html = '<html><body>Hello</body></html>';

    expect($this->detector->detect($html))->toBe([]);
});

it('detects jQuery from inline pattern', function () {
    $html = '<html><body><script>$(document).ready(function(){ alert("hi"); })</script></body></html>';

    expect($this->detector->detect($html))->toContain('jQuery');
});

it('detects Alpine.js from x-data attribute', function () {
    $html = '<html><body><div x-data="{ open: false }"><button x-bind:class="open">Toggle</button></div></body></html>';

    expect($this->detector->detect($html))->toContain('Alpine.js');
});

it('returns results sorted alphabetically', function () {
    $html = '<html><head><script src="https://cdn.jsdelivr.net/npm/vue.min.js"></script></head><body><div id="__next" data-reactroot><div x-data="{}"></div></div></body></html>';

    $result = $this->detector->detect($html);

    expect($result)->toBe(collect($result)->sort()->values()->all());
});

it('deduplicates technologies detected by multiple methods', function () {
    // WordPress detected from both meta generator AND script src
    $html = '<html><head><meta name="generator" content="WordPress 6.5"><script src="/wp-content/themes/starter/js/app.js"></script></head><body></body></html>';

    $result = $this->detector->detect($html);
    $wordpressCount = count(array_filter($result, fn (string $tech) => $tech === 'WordPress'));

    expect($wordpressCount)->toBe(1);
});

it('detects Vercel from x-vercel-id header', function () {
    $html = '<html><body>Hello</body></html>';

    $result = $this->detector->detect($html, ['x-vercel-id' => 'iad1::abcdef-1234']);

    expect($result)->toContain('Vercel');
});

it('detects Netlify from x-nf-request-id header', function () {
    $html = '<html><body>Hello</body></html>';

    $result = $this->detector->detect($html, ['x-nf-request-id' => '01ABC-123']);

    expect($result)->toContain('Netlify');
});

it('detects HTMX from hx attributes', function () {
    $html = '<html><body><button hx-get="/api/data" hx-post="/api/submit">Load</button></body></html>';

    expect($this->detector->detect($html))->toContain('HTMX');
});

it('detects Nuxt from inline pattern', function () {
    $html = '<html><body><div id="__nuxt"></div><script>window.__NUXT__={}</script></body></html>';

    expect($this->detector->detect($html))->toContain('Nuxt');
});
