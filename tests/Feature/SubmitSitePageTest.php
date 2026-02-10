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
        'name' => 'Coupa',
    ])->assertRedirect("/sites/{$existing->slug}");
});

it('passes categories to the submit page', function () {
    $this->get('/submit')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('categories')
            ->where('categories.0.value', 'marketing')
            ->where('categories.0.label', 'Marketing')
        );
});

it('can submit a site with a category', function () {
    Queue::fake();

    $this->post('/submit', [
        'url' => 'https://healthapp.com',
        'name' => 'Health App',
        'category' => 'healthcare',
    ])->assertRedirect();

    $this->assertDatabaseHas('sites', [
        'domain' => 'healthapp.com',
        'category' => 'healthcare',
    ]);
});

it('defaults category to other when not provided', function () {
    Queue::fake();

    $this->post('/submit', [
        'url' => 'https://nocategory.com',
    ])->assertRedirect();

    $this->assertDatabaseHas('sites', [
        'domain' => 'nocategory.com',
        'category' => 'other',
    ]);
});

it('rejects invalid category values', function () {
    $this->post('/submit', [
        'url' => 'https://example.com',
        'category' => 'invalid-category',
    ])->assertSessionHasErrors('category');
});

it('rejects blocked domains', function () {
    $this->post('/submit', [
        'url' => 'https://pornhub.com',
        'name' => 'Blocked Site',
    ])->assertSessionHasErrors('url');

    $this->assertDatabaseMissing('sites', ['domain' => 'pornhub.com']);
});

it('renders the donate page', function () {
    $this->get('/donate')->assertSuccessful();
});
