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
        'name' => 'Example Site',
    ])->assertRedirect();

    $this->assertDatabaseHas('sites', [
        'domain' => 'example.com',
        'name' => 'Example Site',
        'status' => 'queued',
    ]);
});

it('validates url is required', function () {
    $this->post('/submit', [
        'url' => '',
    ])->assertSessionHasErrors('url');
});

it('rejects duplicate domains', function () {
    Site::factory()->create(['domain' => 'example.com']);

    $this->post('/submit', [
        'url' => 'https://example.com',
    ])->assertSessionHasErrors('url');
});

it('renders the donate page', function () {
    $this->get('/donate')->assertSuccessful();
});
