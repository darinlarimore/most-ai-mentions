<?php

use App\Models\Site;

it('returns valid XML with correct content type', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'application/xml');
    $response->assertSee('<?xml version="1.0" encoding="UTF-8"?>', escape: false);
    $response->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', escape: false);
});

it('includes static pages', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertSuccessful();
    $response->assertSee('<loc>'.url('/').'</loc>', escape: false);
    $response->assertSee('<loc>'.url('/user-rated').'</loc>', escape: false);
    $response->assertSee('<loc>'.url('/algorithm').'</loc>', escape: false);
    $response->assertSee('<loc>'.url('/crawl/live').'</loc>', escape: false);
    $response->assertSee('<loc>'.url('/insights').'</loc>', escape: false);
    $response->assertSee('<loc>'.url('/submit').'</loc>', escape: false);
    $response->assertSee('<loc>'.url('/donate').'</loc>', escape: false);
});

it('includes crawled site pages', function () {
    $site = Site::factory()->create([
        'last_crawled_at' => now()->subDay(),
        'status' => 'completed',
    ]);

    $response = $this->get('/sitemap.xml');

    $response->assertSuccessful();
    $response->assertSee('<loc>'.url('/sites/'.$site->slug).'</loc>', escape: false);
});

it('excludes sites that have never been crawled', function () {
    $site = Site::factory()->create([
        'last_crawled_at' => null,
        'status' => 'pending',
    ]);

    $response = $this->get('/sitemap.xml');

    $response->assertSuccessful();
    $response->assertDontSee('/sites/'.$site->slug, escape: false);
});

it('excludes inactive sites', function () {
    $site = Site::factory()->inactive()->create([
        'last_crawled_at' => now()->subDay(),
        'status' => 'completed',
    ]);

    $response = $this->get('/sitemap.xml');

    $response->assertSuccessful();
    $response->assertDontSee('/sites/'.$site->slug, escape: false);
});
