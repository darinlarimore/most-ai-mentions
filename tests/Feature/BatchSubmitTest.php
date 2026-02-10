<?php

use App\Jobs\CrawlSiteJob;
use App\Models\Site;
use Illuminate\Support\Facades\Queue;

it('creates sites from valid batch URLs', function () {
    Queue::fake();

    $this->post('/submit/batch', [
        'urls' => "https://alpha.com\nhttps://beta.com\nhttps://gamma.com",
    ])->assertRedirect();

    $this->assertDatabaseHas('sites', ['domain' => 'alpha.com', 'status' => 'queued']);
    $this->assertDatabaseHas('sites', ['domain' => 'beta.com', 'status' => 'queued']);
    $this->assertDatabaseHas('sites', ['domain' => 'gamma.com', 'status' => 'queued']);

    Queue::assertPushed(CrawlSiteJob::class, 3);
});

it('accepts comma-separated URLs', function () {
    Queue::fake();

    $this->post('/submit/batch', [
        'urls' => 'alpha.com, beta.com, gamma.com',
    ])->assertRedirect();

    $this->assertDatabaseHas('sites', ['domain' => 'alpha.com']);
    $this->assertDatabaseHas('sites', ['domain' => 'beta.com']);
    $this->assertDatabaseHas('sites', ['domain' => 'gamma.com']);
});

it('adds https scheme when missing', function () {
    Queue::fake();

    $this->post('/submit/batch', [
        'urls' => 'noscheme.com',
    ])->assertRedirect();

    $this->assertDatabaseHas('sites', [
        'domain' => 'noscheme.com',
        'url' => 'https://noscheme.com',
    ]);
});

it('skips duplicate domains', function () {
    Queue::fake();

    Site::factory()->create(['domain' => 'existing.com']);

    $this->post('/submit/batch', [
        'urls' => "https://existing.com\nhttps://newsite.com",
    ])->assertRedirect();

    $this->assertDatabaseCount('sites', 2);
    $this->assertDatabaseHas('sites', ['domain' => 'newsite.com']);
});

it('skips invalid URLs', function () {
    Queue::fake();

    $this->post('/submit/batch', [
        'urls' => "https://valid.com\nnot a url\nhttps://also-valid.com",
    ])->assertRedirect();

    $this->assertDatabaseHas('sites', ['domain' => 'valid.com']);
    $this->assertDatabaseHas('sites', ['domain' => 'also-valid.com']);
    $this->assertDatabaseCount('sites', 2);
});

it('skips blocked domains', function () {
    Queue::fake();

    $this->post('/submit/batch', [
        'urls' => "https://pornhub.com\nhttps://cleansite.com",
    ])->assertRedirect();

    $this->assertDatabaseMissing('sites', ['domain' => 'pornhub.com']);
    $this->assertDatabaseHas('sites', ['domain' => 'cleansite.com']);
});

it('validates urls field is required', function () {
    $this->post('/submit/batch', [
        'urls' => '',
    ])->assertSessionHasErrors('urls');
});

it('returns error when no new sites are added', function () {
    Queue::fake();

    Site::factory()->create(['domain' => 'existing.com']);

    $this->post('/submit/batch', [
        'urls' => 'https://existing.com',
    ])->assertSessionHasErrors('urls');
});

it('flashes success message with counts', function () {
    Queue::fake();

    Site::factory()->create(['domain' => 'dupe.com']);

    $this->post('/submit/batch', [
        'urls' => "https://newone.com\nhttps://dupe.com\nnot-valid",
    ])->assertSessionHas('success');
});
