<?php

use App\Enums\CrawlErrorCategory;
use App\Models\CrawlError;
use App\Models\Site;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the site show page with crawl error', function () {
    $site = Site::factory()->create(['status' => 'completed']);

    CrawlError::create([
        'site_id' => $site->id,
        'category' => CrawlErrorCategory::CloudflareBlocked,
        'message' => 'Cloudflare challenge page detected',
        'url' => $site->url,
    ]);

    $this->get(route('sites.show', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sites/Show')
            ->has('site.latest_crawl_error', fn (Assert $error) => $error
                ->where('category', 'cloudflare_blocked')
                ->where('category_label', 'Cloudflare Challenge')
                ->where('message', 'Cloudflare challenge page detected')
                ->etc()
            )
        );
});

it('renders the site show page without crawl error when none exists', function () {
    $site = Site::factory()->create(['status' => 'completed']);

    $this->get(route('sites.show', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sites/Show')
            ->where('site.latest_crawl_error', null)
        );
});
