<?php

use App\Jobs\CheckNonHomepageRedirectJob;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('dispatches a job for each active site', function () {
    Queue::fake();

    Site::factory()->count(3)->create(['is_active' => true]);
    Site::factory()->inactive()->create();

    $this->artisan('app:check-non-homepage-redirects')
        ->assertSuccessful();

    Queue::assertPushed(CheckNonHomepageRedirectJob::class, 3);
});

it('deletes site and screenshot when redirect goes to non-homepage', function () {
    Storage::fake('public');
    Storage::disk('public')->put('screenshots/bad-site.webp', 'fake-image');

    Http::fake([
        'https://bad-redirect.com' => Http::response('', 302, ['Location' => 'https://bad-redirect.com/app/login']),
        'https://bad-redirect.com/app/login' => Http::response('', 200),
    ]);

    $site = Site::factory()->create([
        'url' => 'https://bad-redirect.com',
        'is_active' => true,
        'screenshot_path' => 'screenshots/bad-site.webp',
    ]);

    CheckNonHomepageRedirectJob::dispatchSync($site);

    expect(Site::find($site->id))->toBeNull();
    Storage::disk('public')->assertMissing('screenshots/bad-site.webp');
});

it('keeps site when redirect stays on homepage', function () {
    Http::fake([
        'https://good-site.com' => Http::response('', 301, ['Location' => 'https://www.good-site.com/']),
        'https://www.good-site.com/' => Http::response('', 200),
    ]);

    $site = Site::factory()->create([
        'url' => 'https://good-site.com',
        'is_active' => true,
    ]);

    CheckNonHomepageRedirectJob::dispatchSync($site);

    expect(Site::find($site->id))->not->toBeNull();
});
