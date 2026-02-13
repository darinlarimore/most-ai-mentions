<?php

use App\Models\CompanyList;
use App\Models\CompanyListEntry;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->ycList = CompanyList::create([
        'name' => 'Y Combinator',
        'slug' => 'y-combinator',
        'description' => 'YC startups',
        'sort_order' => 4,
    ]);

    $this->fortuneList = CompanyList::create([
        'name' => 'Fortune 500',
        'slug' => 'fortune-500',
        'description' => 'Fortune 500 companies',
        'sort_order' => 1,
    ]);

    $this->forbesList = CompanyList::create([
        'name' => 'Forbes Global 2000',
        'slug' => 'forbes-global-2000',
        'description' => 'Forbes Global 2000',
        'sort_order' => 3,
    ]);

    $this->incList = CompanyList::create([
        'name' => 'Inc. 5000',
        'slug' => 'inc-5000',
        'description' => 'Inc. 5000 companies',
        'sort_order' => 2,
    ]);
});

it('syncs entries from YC API', function () {
    Http::fake([
        'yc-oss.github.io/api/companies/all.json' => Http::response([
            ['name' => 'Stripe', 'website' => 'https://stripe.com'],
            ['name' => 'Airbnb', 'website' => 'https://www.airbnb.com'],
            ['name' => 'No Website Co', 'website' => null],
        ]),
        '*' => Http::response([], 500),
    ]);

    $this->artisan('app:sync-company-lists', ['--list' => 'y-combinator'])
        ->assertSuccessful();

    expect(CompanyListEntry::where('company_list_id', $this->ycList->id)->count())->toBe(2);
    expect(CompanyListEntry::where('domain', 'stripe.com')->exists())->toBeTrue();
    expect(CompanyListEntry::where('domain', 'airbnb.com')->exists())->toBeTrue();
});

it('creates site records for new domains', function () {
    Site::factory()->create(['domain' => 'stripe.com']);

    Http::fake([
        'yc-oss.github.io/api/companies/all.json' => Http::response([
            ['name' => 'Stripe', 'website' => 'https://stripe.com'],
            ['name' => 'New Startup', 'website' => 'https://newstartup.io'],
        ]),
        '*' => Http::response([], 500),
    ]);

    $this->artisan('app:sync-company-lists', ['--list' => 'y-combinator'])
        ->assertSuccessful();

    // stripe.com already exists, should NOT be duplicated
    expect(Site::where('domain', 'stripe.com')->count())->toBe(1);

    // newstartup.io should be created
    $newSite = Site::where('domain', 'newstartup.io')->first();
    expect($newSite)->not->toBeNull();
    expect($newSite->status)->toBe('queued');
    expect($newSite->source)->toBe('company-list');
    expect($newSite->name)->toBe('New Startup');
    expect($newSite->url)->toBe('https://newstartup.io');
});

it('logs critical error when API returns non-200', function () {
    Log::shouldReceive('critical')
        ->once()
        ->withArgs(fn (string $message, array $context) => str_contains($message, 'y-combinator')
            && isset($context['error'])
        );

    Http::fake([
        'yc-oss.github.io/api/companies/all.json' => Http::response('Service Unavailable', 503),
        '*' => Http::response([], 500),
    ]);

    $this->artisan('app:sync-company-lists', ['--list' => 'y-combinator'])
        ->assertFailed();
});

it('handles malformed API response gracefully', function () {
    Http::fake([
        'yc-oss.github.io/api/companies/all.json' => Http::response([
            ['name' => 'Valid Co', 'website' => 'https://valid.com'],
            ['name' => null, 'website' => 'https://no-name.com'],
            ['name' => 'Bad URL', 'website' => '#'],
            ['name' => 'Empty URL', 'website' => ''],
        ]),
        '*' => Http::response([], 500),
    ]);

    $this->artisan('app:sync-company-lists', ['--list' => 'y-combinator'])
        ->assertSuccessful();

    expect(CompanyListEntry::where('company_list_id', $this->ycList->id)->count())->toBe(1);
    expect(CompanyListEntry::where('domain', 'valid.com')->exists())->toBeTrue();
});

it('does not write to database in dry-run mode', function () {
    Http::fake([
        'yc-oss.github.io/api/companies/all.json' => Http::response([
            ['name' => 'Stripe', 'website' => 'https://stripe.com'],
        ]),
        '*' => Http::response([], 500),
    ]);

    $this->artisan('app:sync-company-lists', ['--list' => 'y-combinator', '--dry-run' => true])
        ->assertSuccessful();

    expect(CompanyListEntry::count())->toBe(0);
    expect(Site::where('source', 'company-list')->count())->toBe(0);
});

it('syncs a specific list with --list option', function () {
    Http::fake([
        'yc-oss.github.io/api/companies/all.json' => Http::response([
            ['name' => 'Stripe', 'website' => 'https://stripe.com'],
        ]),
        '*' => Http::response([], 500),
    ]);

    $this->artisan('app:sync-company-lists', ['--list' => 'y-combinator'])
        ->assertSuccessful();

    expect(CompanyListEntry::where('company_list_id', $this->ycList->id)->count())->toBe(1);
    // Other lists should be untouched
    expect(CompanyListEntry::where('company_list_id', $this->fortuneList->id)->count())->toBe(0);
});

it('removes stale entries no longer in API response', function () {
    CompanyListEntry::create([
        'company_list_id' => $this->ycList->id,
        'company_name' => 'Old Startup',
        'domain' => 'old-startup.com',
        'rank' => 1,
    ]);

    Http::fake([
        'yc-oss.github.io/api/companies/all.json' => Http::response([
            ['name' => 'New Startup', 'website' => 'https://newstartup.io'],
        ]),
        '*' => Http::response([], 500),
    ]);

    $this->artisan('app:sync-company-lists', ['--list' => 'y-combinator'])
        ->assertSuccessful();

    expect(CompanyListEntry::where('domain', 'old-startup.com')->exists())->toBeFalse();
    expect(CompanyListEntry::where('domain', 'newstartup.io')->exists())->toBeTrue();
});

it('syncs Fortune 500 entries with pagination', function () {
    Http::fake([
        'fortune.com/api/v2/list/1141696/expand/item/ranking/asc/0/100' => Http::response([
            'list-items' => [
                ['fields' => ['title' => 'Walmart', 'website' => 'https://walmart.com', 'rank' => 1]],
                ['fields' => ['title' => 'Amazon', 'website' => 'https://www.amazon.com', 'rank' => 2]],
            ],
        ]),
        'fortune.com/api/v2/list/1141696/expand/item/ranking/asc/100/100' => Http::response(['list-items' => []]),
        'fortune.com/api/v2/list/1141696/expand/item/ranking/asc/200/100' => Http::response(['list-items' => []]),
        'fortune.com/api/v2/list/1141696/expand/item/ranking/asc/300/100' => Http::response(['list-items' => []]),
        'fortune.com/api/v2/list/1141696/expand/item/ranking/asc/400/100' => Http::response(['list-items' => []]),
    ]);

    $this->artisan('app:sync-company-lists', ['--list' => 'fortune-500'])
        ->assertSuccessful();

    expect(CompanyListEntry::where('company_list_id', $this->fortuneList->id)->count())->toBe(2);
    expect(CompanyListEntry::where('domain', 'walmart.com')->first()->rank)->toBe(1);
    expect(CompanyListEntry::where('domain', 'amazon.com')->first()->rank)->toBe(2);
});

it('syncs Inc 5000 entries', function () {
    Http::fake([
        'www.inc.com/inc5000list/json/*' => Http::response([
            ['company' => 'Fast Co', 'website' => 'https://fastco.com', 'rank' => 1],
            ['company' => 'Growth Inc', 'website' => 'https://www.growthinc.io', 'rank' => 2],
        ]),
    ]);

    $this->artisan('app:sync-company-lists', ['--list' => 'inc-5000'])
        ->assertSuccessful();

    expect(CompanyListEntry::where('company_list_id', $this->incList->id)->count())->toBe(2);
    expect(CompanyListEntry::where('domain', 'growthinc.io')->exists())->toBeTrue();
});

it('continues syncing other lists when one fails', function () {
    Log::shouldReceive('critical')->once();
    Log::shouldReceive('info')->times(3);

    Http::fake([
        'yc-oss.github.io/api/companies/all.json' => Http::response('Error', 500),
        'fortune.com/api/v2/list/*' => Http::response(['list-items' => [
            ['fields' => ['title' => 'Walmart', 'website' => 'https://walmart.com', 'rank' => 1]],
        ]]),
        'www.forbes.com/forbesapi/*' => Http::response([
            'organizationList' => ['organizationsLists' => [
                ['organizationName' => 'ICBC', 'uri' => 'icbc', 'position' => 1, 'website' => 'https://icbc.com.cn'],
            ]],
        ]),
        'www.inc.com/inc5000list/json/*' => Http::response([
            ['company' => 'Fast Co', 'website' => 'https://fastco.com', 'rank' => 1],
        ]),
    ]);

    $this->artisan('app:sync-company-lists')
        ->assertFailed();

    // YC failed but others should have synced
    expect(CompanyListEntry::where('company_list_id', $this->ycList->id)->count())->toBe(0);
    expect(CompanyListEntry::where('company_list_id', $this->fortuneList->id)->count())->toBe(1);
    expect(CompanyListEntry::where('company_list_id', $this->forbesList->id)->count())->toBe(1);
    expect(CompanyListEntry::where('company_list_id', $this->incList->id)->count())->toBe(1);
});

it('fails for unknown list slug', function () {
    $this->artisan('app:sync-company-lists', ['--list' => 'nonexistent'])
        ->assertFailed();
});

it('normalizes domains by stripping www prefix', function () {
    Http::fake([
        'yc-oss.github.io/api/companies/all.json' => Http::response([
            ['name' => 'Example Co', 'website' => 'https://www.example.com'],
        ]),
        '*' => Http::response([], 500),
    ]);

    $this->artisan('app:sync-company-lists', ['--list' => 'y-combinator'])
        ->assertSuccessful();

    expect(CompanyListEntry::where('domain', 'example.com')->exists())->toBeTrue();
    expect(CompanyListEntry::where('domain', 'www.example.com')->exists())->toBeFalse();
});
