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

    expect($sites)->toHaveCount(3);
    expect($sites->pluck('domain')->toArray())->toContain('coolai.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('unrelated.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('another-ai.example.com');
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

it('discovers sites from hn algolia search', function () {
    Http::fake([
        'hn.algolia.com/*' => Http::response([
            'hits' => [
                ['url' => 'https://cool-ai-tool.example.com', 'title' => 'Cool AI Tool', 'points' => 50],
                ['url' => null, 'title' => 'Ask HN: no url', 'points' => 20],
                ['url' => 'https://another-ai.example.com', 'title' => 'Another AI', 'points' => 10],
            ],
        ]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromHackerNewsSearch();

    expect($sites->pluck('domain')->toArray())->toContain('cool-ai-tool.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('another-ai.example.com');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['hackernews']);
});

it('discovers sites from github repo homepages', function () {
    Http::fake([
        'api.github.com/*' => Http::response([
            'items' => [
                ['homepage' => 'https://ai-framework.example.com', 'full_name' => 'org/ai-framework'],
                ['homepage' => '', 'full_name' => 'org/no-homepage'],
                ['homepage' => 'https://ml-tool.example.com', 'full_name' => 'org/ml-tool'],
            ],
        ]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromGitHub();

    expect($sites->pluck('domain')->toArray())->toContain('ai-framework.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('ml-tool.example.com');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['github']);
});

it('discovers sites from devto articles', function () {
    Http::fake([
        'dev.to/*' => Http::response([
            ['canonical_url' => 'https://ai-blog.example.com/post', 'title' => 'AI Tools'],
            ['url' => 'https://dev.to/user/post', 'title' => 'Dev.to hosted'],
            ['canonical_url' => 'https://ml-site.example.com/article', 'title' => 'ML Guide'],
        ]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromDevTo();

    expect($sites->pluck('domain')->toArray())->toContain('ai-blog.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('ml-site.example.com');
    // dev.to is excluded
    expect($sites->pluck('domain')->toArray())->not->toContain('dev.to');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['devto']);
});

it('discovers sites from reddit link posts', function () {
    Http::fake([
        'www.reddit.com/r/*/hot.json*' => Http::response([
            'data' => [
                'children' => [
                    ['data' => ['is_self' => false, 'url' => 'https://cool-ai-startup.example.com/launch']],
                    ['data' => ['is_self' => true, 'url' => 'https://self-post.example.com']],
                    ['data' => ['is_self' => false, 'url' => 'https://reddit.com/r/something']],
                    ['data' => ['is_self' => false, 'url' => 'https://ml-tool.example.com']],
                ],
            ],
        ]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromReddit();

    expect($sites->pluck('domain')->toArray())->toContain('cool-ai-startup.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('ml-tool.example.com');
    // Self posts should be skipped
    expect($sites->pluck('domain')->toArray())->not->toContain('self-post.example.com');
    // reddit.com is excluded
    expect($sites->pluck('domain')->toArray())->not->toContain('reddit.com');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['reddit']);
});

it('discovers sites from lobsters stories filtered by ai keywords', function () {
    Http::fake([
        'lobste.rs/*' => Http::response([
            ['title' => 'New AI Framework Released', 'url' => 'https://ai-framework.example.com'],
            ['title' => 'Rust Performance Tips', 'url' => 'https://rust-tips.example.com'],
            ['title' => 'LLM Benchmarking Tool', 'url' => 'https://llm-bench.example.com'],
            ['title' => 'Cooking Recipes App', 'url' => 'https://cooking.example.com'],
        ]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromLobsters();

    expect($sites)->toHaveCount(4);
    expect($sites->pluck('domain')->toArray())->toContain('ai-framework.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('llm-bench.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('rust-tips.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('cooking.example.com');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['lobsters']);
});

it('discovers sites from wikipedia article external links', function () {
    Http::fake([
        'en.wikipedia.org/w/api.php?*list=search*' => Http::response([
            'query' => [
                'search' => [
                    ['title' => 'Artificial intelligence'],
                ],
            ],
        ]),
        'en.wikipedia.org/w/api.php?*prop=extlinks*' => Http::response([
            'query' => [
                'pages' => [
                    '12345' => [
                        'extlinks' => [
                            ['url' => 'https://ai-company.example.com'],
                            ['url' => 'https://wikipedia.org/other'],
                            ['url' => 'https://ml-platform.example.com'],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromWikipedia();

    expect($sites->pluck('domain')->toArray())->toContain('ai-company.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('ml-platform.example.com');
    // wikipedia.org is excluded
    expect($sites->pluck('domain')->toArray())->not->toContain('wikipedia.org');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['wikipedia']);
});

it('discovers sites from lemmy link posts', function () {
    Http::fake([
        'lemmy.world/api/v3/post/list*' => Http::response([
            'posts' => [
                ['post' => ['url' => 'https://ai-news.example.com/article']],
                ['post' => ['name' => 'Text only post']],
                ['post' => ['url' => 'https://lemmy.world/post/123']],
                ['post' => ['url' => 'https://new-ml-tool.example.com']],
            ],
        ]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromLemmy();

    expect($sites->pluck('domain')->toArray())->toContain('ai-news.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('new-ml-tool.example.com');
    // lemmy.world is excluded
    expect($sites->pluck('domain')->toArray())->not->toContain('lemmy.world');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['lemmy']);
});

it('discovers sites from mastodon hashtag timelines', function () {
    Http::fake([
        'mastodon.social/api/v1/timelines/tag/*' => Http::response([
            ['card' => ['url' => 'https://cool-tool.example.com']],
            ['card' => null],
            ['content' => '<p>No card here</p>'],
            ['card' => ['url' => 'https://another-app.example.com']],
            ['card' => ['url' => 'https://mastodon.social/some-post']],
        ]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromMastodon();

    expect($sites->pluck('domain')->toArray())->toContain('cool-tool.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('another-app.example.com');
    expect($sites->pluck('domain')->toArray())->not->toContain('mastodon.social');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['mastodon']);
});

it('discovers sites from show hn posts', function () {
    Http::fake([
        'hn.algolia.com/*' => Http::response([
            'hits' => [
                ['url' => 'https://my-startup.example.com', 'title' => 'Show HN: My Startup'],
                ['url' => null, 'title' => 'Show HN: text only'],
                ['url' => 'https://dev-tool.example.com', 'title' => 'Show HN: Dev Tool'],
            ],
        ]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromShowHN();

    expect($sites->pluck('domain')->toArray())->toContain('my-startup.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('dev-tool.example.com');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['show_hn']);
});

it('discovers sites from wikidata sparql queries', function () {
    Http::fake([
        'query.wikidata.org/*' => Http::response([
            'results' => [
                'bindings' => [
                    ['website' => ['type' => 'uri', 'value' => 'https://software-co.example.com']],
                    ['website' => ['type' => 'uri', 'value' => 'https://wikipedia.org/wiki/something']],
                    ['website' => ['type' => 'uri', 'value' => 'https://tech-startup.example.com']],
                ],
            ],
        ]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromWikidata();

    expect($sites->pluck('domain')->toArray())->toContain('software-co.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('tech-startup.example.com');
    expect($sites->pluck('domain')->toArray())->not->toContain('wikipedia.org');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['wikidata']);
});

it('discovers sites from commoncrawl index', function () {
    Http::fake([
        'index.commoncrawl.org/collinfo.json' => Http::response([
            ['cdx-api' => 'https://index.commoncrawl.org/CC-MAIN-2025-06-index'],
        ]),
        'index.commoncrawl.org/CC-MAIN-2025-06-index*' => Http::response(
            "{\"url\":\"https://example-one.ai/page\",\"urlkey\":\"ai,example-one)/page\"}\n".
            "{\"url\":\"https://example-two.dev/\",\"urlkey\":\"dev,example-two)/\"}\n".
            "{\"url\":\"https://reddit.com/r/test\",\"urlkey\":\"com,reddit)/r/test\"}\n"
        ),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromCommonCrawl();

    expect($sites->pluck('domain')->toArray())->toContain('example-one.ai');
    expect($sites->pluck('domain')->toArray())->toContain('example-two.dev');
    expect($sites->pluck('domain')->toArray())->not->toContain('reddit.com');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['commoncrawl']);
});

it('discovers sites from stack exchange question bodies', function () {
    Http::fake([
        'api.stackexchange.com/*' => Http::response([
            'items' => [
                ['body' => '<p>Try <a href="https://awesome-tool.example.com">this tool</a> and <a href="https://stackoverflow.com/q/123">related</a></p>'],
                ['body' => '<p>Use <a href="https://dev-platform.example.com/docs">this platform</a></p>'],
            ],
        ]),
    ]);

    $service = new SiteDiscoveryService;
    $sites = $service->discoverFromStackExchange();

    expect($sites->pluck('domain')->toArray())->toContain('awesome-tool.example.com');
    expect($sites->pluck('domain')->toArray())->toContain('dev-platform.example.com');
    expect($sites->pluck('domain')->toArray())->not->toContain('stackoverflow.com');
    expect($sites->pluck('source')->unique()->toArray())->toBe(['stackexchange']);
});
