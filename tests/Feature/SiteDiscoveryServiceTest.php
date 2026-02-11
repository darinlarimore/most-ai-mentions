<?php

use App\Models\Site;
use App\Services\SiteDiscoveryService;
use Illuminate\Support\Facades\Http;

it('discovers sites from hacker news api', function () {
    Http::fake([
        'hacker-news.firebaseio.com/v0/topstories.json' => Http::response([101, 102, 103]),
        'hacker-news.firebaseio.com/v0/beststories.json' => Http::response([]),
        'hacker-news.firebaseio.com/v0/newstories.json' => Http::response([]),
        'hacker-news.firebaseio.com/v0/item/101.json' => Http::response([
            'id' => 101,
            'title' => 'Cool AI Startup launches GPT tool',
            'url' => 'https://coolai.example.com/post',
        ]),
        'hacker-news.firebaseio.com/v0/item/102.json' => Http::response([
            'id' => 102,
            'title' => 'Sports News Today',
            'url' => 'https://unrelated.example.com/news',
        ]),
        'hacker-news.firebaseio.com/v0/item/103.json' => Http::response([
            'id' => 103,
            'title' => 'New Machine Learning Framework',
            'url' => 'https://another-ai.example.com',
        ]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromHackerNews();

    expect($sites)->toHaveCount(2);
    expect($sites->pluck('domain')->toArray())->toContain('coolai.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('another-ai.example.com');
    expect($sites->pluck('domain')->toArray())->not->toContain('unrelated.example.com');
});

it('skips hn stories without url field', function () {
    Http::fake([
        'hacker-news.firebaseio.com/v0/topstories.json' => Http::response([201]),
        'hacker-news.firebaseio.com/v0/beststories.json' => Http::response([]),
        'hacker-news.firebaseio.com/v0/newstories.json' => Http::response([]),
        'hacker-news.firebaseio.com/v0/item/201.json' => Http::response([
            'id' => 201,
            'title' => 'Ask HN: Best AI tools?',
            'type' => 'story',
        ]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromHackerNews();

    expect($sites)->toHaveCount(0);
});

it('skips duplicate domains', function () {
    Site::factory()->create(['domain' => 'coolai.example.com']);

    Http::fake([
        'hacker-news.firebaseio.com/v0/topstories.json' => Http::response([301, 302]),
        'hacker-news.firebaseio.com/v0/beststories.json' => Http::response([]),
        'hacker-news.firebaseio.com/v0/newstories.json' => Http::response([]),
        'hacker-news.firebaseio.com/v0/item/301.json' => Http::response([
            'id' => 301,
            'title' => 'AI Tool',
            'url' => 'https://coolai.example.com',
        ]),
        'hacker-news.firebaseio.com/v0/item/302.json' => Http::response([
            'id' => 302,
            'title' => 'New AI thing',
            'url' => 'https://newai.example.com',
        ]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromHackerNews();

    expect($sites)->toHaveCount(1);
    expect($sites->first()->domain)->toBe('newai.example.com');
});

it('skips excluded domains', function () {
    Http::fake([
        'hacker-news.firebaseio.com/v0/topstories.json' => Http::response([401, 402]),
        'hacker-news.firebaseio.com/v0/beststories.json' => Http::response([]),
        'hacker-news.firebaseio.com/v0/newstories.json' => Http::response([]),
        'hacker-news.firebaseio.com/v0/item/401.json' => Http::response([
            'id' => 401,
            'title' => 'AI project on GitHub',
            'url' => 'https://github.com/ai-project',
        ]),
        'hacker-news.firebaseio.com/v0/item/402.json' => Http::response([
            'id' => 402,
            'title' => 'AI video on YouTube',
            'url' => 'https://youtube.com/ai-video',
        ]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromHackerNews();

    expect($sites)->toHaveCount(0);
});

it('normalizes urls to homepage', function () {
    $service = new SiteDiscoveryService;

    expect($service->normalizeUrl('https://www.example.com/some/path'))->toBe('https://example.com');
    expect($service->normalizeUrl('http://example.com/page'))->toBe('https://example.com');
    expect($service->normalizeUrl('example.com'))->toBe('https://example.com');
    expect($service->normalizeUrl(''))->toBeNull();
});

it('discovers sites from tranco csv zip', function () {
    // Create a minimal CSV and zip it
    $csv = "1,example-tranco-one.com\n2,example-tranco-two.com\n3,google.com\n";
    $tmpZip = tempnam(sys_get_temp_dir(), 'test_tranco_');
    $zip = new ZipArchive;
    $zip->open($tmpZip, ZipArchive::CREATE);
    $zip->addFromString('top-1m.csv', $csv);
    $zip->close();
    $zipContent = file_get_contents($tmpZip);
    unlink($tmpZip);

    Http::fake([
        'tranco-list.eu/*' => Http::response($zipContent),
        '*' => Http::response('', 404),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromTrancoList(10);

    // google.com is excluded, so we should get 2
    expect($sites)->toHaveCount(2);
    expect($sites->pluck('domain')->toArray())->toContain('example-tranco-one.com');
    expect($sites->pluck('domain')->toArray())->toContain('example-tranco-two.com');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['tranco']);
});

it('sets source and status correctly on discovered sites', function () {
    Http::fake([
        'hacker-news.firebaseio.com/v0/topstories.json' => Http::response([501]),
        'hacker-news.firebaseio.com/v0/beststories.json' => Http::response([]),
        'hacker-news.firebaseio.com/v0/newstories.json' => Http::response([]),
        'hacker-news.firebaseio.com/v0/item/501.json' => Http::response([
            'id' => 501,
            'title' => 'New AI tool',
            'url' => 'https://newai.example.com',
        ]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromHackerNews();

    expect($sites->first()->source)->toBe('hackernews');
    expect($sites->first()->status)->toBe('queued');
});

it('discovers external links from reddit', function () {
    Http::fake([
        'www.reddit.com/r/artificial/hot.json*' => Http::response([
            'data' => [
                'children' => [
                    ['data' => ['is_self' => false, 'url' => 'https://cool-ai-tool.example.com']],
                    ['data' => ['is_self' => true, 'url' => 'https://reddit.com/r/artificial/self']],
                    ['data' => ['is_self' => false, 'url' => 'https://another-ai.example.com']],
                ],
            ],
        ]),
        'www.reddit.com/r/MachineLearning/hot.json*' => Http::response(['data' => ['children' => []]]),
        'www.reddit.com/r/LocalLLaMA/hot.json*' => Http::response(['data' => ['children' => []]]),
        'www.reddit.com/r/SideProject/hot.json*' => Http::response(['data' => ['children' => []]]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromReddit();

    expect($sites)->toHaveCount(2);
    expect($sites->pluck('domain')->toArray())->toContain('cool-ai-tool.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('another-ai.example.com');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['reddit']);
});

it('skips self-posts from reddit', function () {
    Http::fake([
        'www.reddit.com/r/artificial/hot.json*' => Http::response([
            'data' => [
                'children' => [
                    ['data' => ['is_self' => true, 'url' => 'https://reddit.com/r/artificial/comments/abc']],
                ],
            ],
        ]),
        'www.reddit.com/r/MachineLearning/hot.json*' => Http::response(['data' => ['children' => []]]),
        'www.reddit.com/r/LocalLLaMA/hot.json*' => Http::response(['data' => ['children' => []]]),
        'www.reddit.com/r/SideProject/hot.json*' => Http::response(['data' => ['children' => []]]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromReddit();

    expect($sites)->toHaveCount(0);
});

it('extracts urls from alternativeto html', function () {
    $html = '<html><body>
        <a href="https://cool-alternative.example.com">Cool Tool</a>
        <a href="https://another-tool.example.com/page">Another</a>
        <a href="https://alternativeto.net/software/test/">Internal</a>
    </body></html>';

    Http::fake([
        'alternativeto.net/*' => Http::response($html),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromAlternativeTo();

    expect($sites->pluck('domain')->toArray())->toContain('cool-alternative.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('another-tool.example.com');
    // alternativeto.net is excluded
    expect($sites->pluck('domain')->toArray())->not->toContain('alternativeto.net');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['alternativeto']);
});

it('filters ai-keyword domains from new domains zip', function () {
    $domainList = "normalsite.com\ncoolai-tools.com\nmygpt-app.io\nrandomshop.net\ndeeplearn-lab.ai\n";
    $tmpZip = tempnam(sys_get_temp_dir(), 'test_nrd_');
    $zip = new ZipArchive;
    $zip->open($tmpZip, ZipArchive::CREATE);
    $zip->addFromString('domains.txt', $domainList);
    $zip->close();
    $zipContent = file_get_contents($tmpZip);
    unlink($tmpZip);

    Http::fake([
        'whoisds.com/*' => Http::response($zipContent),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromNewDomains();

    // coolai-tools.com (ai), mygpt-app.io (gpt), deeplearn-lab.ai (deeplearn)
    expect($sites)->toHaveCount(3);
    expect($sites->pluck('domain')->toArray())->toContain('coolai-tools.com');
    expect($sites->pluck('domain')->toArray())->toContain('mygpt-app.io');
    expect($sites->pluck('domain')->toArray())->toContain('deeplearn-lab.ai');
    expect($sites->pluck('domain')->toArray())->not->toContain('normalsite.com');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['newdomains']);
});
