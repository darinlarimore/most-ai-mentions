<?php

use App\Models\CompanyList;
use App\Models\CompanyListEntry;
use App\Models\Site;

beforeEach(function () {
    $this->list = CompanyList::create([
        'name' => 'Fortune 500',
        'slug' => 'fortune-500',
        'description' => 'Test description',
        'source_url' => 'https://example.com',
        'sort_order' => 1,
    ]);
});

it('displays matched sites for a company list', function () {
    $site = Site::factory()->create(['domain' => 'apple.com']);

    CompanyListEntry::create([
        'company_list_id' => $this->list->id,
        'company_name' => 'Apple',
        'domain' => 'apple.com',
        'rank' => 1,
    ]);

    $response = $this->get('/lists/fortune-500');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('CompanyLists/Show')
        ->has('sites.data', 1)
        ->where('list.name', 'Fortune 500')
        ->where('totalCompanies', 1)
        ->where('matchedCount', 1)
    );
});

it('excludes inactive sites', function () {
    $site = Site::factory()->inactive()->create(['domain' => 'inactive.com']);

    CompanyListEntry::create([
        'company_list_id' => $this->list->id,
        'company_name' => 'Inactive Co',
        'domain' => 'inactive.com',
        'rank' => 1,
    ]);

    $response = $this->get('/lists/fortune-500');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('sites.data', 0)
        ->where('matchedCount', 0)
    );
});

it('excludes uncrawled sites', function () {
    $site = Site::factory()->pending()->create(['domain' => 'pending.com']);

    CompanyListEntry::create([
        'company_list_id' => $this->list->id,
        'company_name' => 'Pending Co',
        'domain' => 'pending.com',
        'rank' => 1,
    ]);

    $response = $this->get('/lists/fortune-500');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('sites.data', 0)
        ->where('matchedCount', 0)
    );
});

it('returns 404 for non-existent list slug', function () {
    $response = $this->get('/lists/nonexistent');

    $response->assertNotFound();
});

it('orders sites by hype_score descending', function () {
    $low = Site::factory()->create(['domain' => 'low.com', 'hype_score' => 10]);
    $high = Site::factory()->create(['domain' => 'high.com', 'hype_score' => 500]);

    CompanyListEntry::create([
        'company_list_id' => $this->list->id,
        'company_name' => 'Low Co',
        'domain' => 'low.com',
        'rank' => 1,
    ]);

    CompanyListEntry::create([
        'company_list_id' => $this->list->id,
        'company_name' => 'High Co',
        'domain' => 'high.com',
        'rank' => 2,
    ]);

    $response = $this->get('/lists/fortune-500');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('sites.data', 2)
        ->where('sites.data.0.domain', 'high.com')
        ->where('sites.data.1.domain', 'low.com')
    );
});

it('reports correct totalCompanies and matchedCount', function () {
    $site = Site::factory()->create(['domain' => 'matched.com']);
    Site::factory()->inactive()->create(['domain' => 'inactive.com']);

    CompanyListEntry::create([
        'company_list_id' => $this->list->id,
        'company_name' => 'Matched Co',
        'domain' => 'matched.com',
        'rank' => 1,
    ]);

    CompanyListEntry::create([
        'company_list_id' => $this->list->id,
        'company_name' => 'Inactive Co',
        'domain' => 'inactive.com',
        'rank' => 2,
    ]);

    CompanyListEntry::create([
        'company_list_id' => $this->list->id,
        'company_name' => 'Missing Co',
        'domain' => 'notindb.com',
        'rank' => 3,
    ]);

    $response = $this->get('/lists/fortune-500');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->where('totalCompanies', 3)
        ->where('matchedCount', 1)
    );
});

it('includes company list URLs in sitemap', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertSuccessful();
    $response->assertSee('<loc>'.url('/lists/fortune-500').'</loc>', escape: false);
});
