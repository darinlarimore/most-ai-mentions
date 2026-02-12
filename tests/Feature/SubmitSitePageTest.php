<?php

use App\Models\Site;
use Illuminate\Support\Facades\Queue;

it('renders the submit page', function () {
    $this->get('/submit')->assertSuccessful();
});

it('can submit a site for crawling', function () {
    Queue::fake();

    $this->post('/submit', [
        'url' => 'https://example.com',
    ])->assertRedirect();

    $this->assertDatabaseHas('sites', [
        'domain' => 'example.com',
        'status' => 'queued',
    ]);
});

it('validates url is required', function () {
    $this->post('/submit', [
        'url' => '',
    ])->assertSessionHasErrors('url');
});

it('redirects to existing site when submitting a duplicate domain', function () {
    $existing = Site::factory()->create(['domain' => 'example.com']);

    $this->post('/submit', [
        'url' => 'https://example.com',
    ])->assertRedirect("/sites/{$existing->slug}");
});

it('redirects to existing www site when submitting without www', function () {
    $existing = Site::factory()->create(['domain' => 'www.coupa.com', 'slug' => 'coupa-com']);

    $this->post('/submit', [
        'url' => 'https://coupa.com',
    ])->assertRedirect("/sites/{$existing->slug}");
});

it('always sets category to other for auto-detection', function () {
    Queue::fake();

    $this->post('/submit', [
        'url' => 'https://nocategory.com',
    ])->assertRedirect();

    $this->assertDatabaseHas('sites', [
        'domain' => 'nocategory.com',
        'category' => 'other',
    ]);
});

it('rejects blocked domains', function () {
    $this->post('/submit', [
        'url' => 'https://pornhub.com',
    ])->assertSessionHasErrors('url');

    $this->assertDatabaseMissing('sites', ['domain' => 'pornhub.com']);
});

it('renders the donate page', function () {
    $this->get('/donate')->assertSuccessful();
});
