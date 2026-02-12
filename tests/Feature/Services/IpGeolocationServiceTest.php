<?php

use App\Services\IpGeolocationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = new IpGeolocationService;
});

it('returns coordinates for a valid public IP', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'lat' => 37.7749,
            'lon' => -122.4194,
        ]),
    ]);

    $result = $this->service->geolocate('8.8.8.8');

    expect($result)->toBe([
        'latitude' => 37.7749,
        'longitude' => -122.4194,
    ]);

    Http::assertSentCount(1);
});

it('returns null for private IPs', function (string $ip) {
    Http::fake();

    $result = $this->service->geolocate($ip);

    expect($result)->toBeNull();
    Http::assertNothingSent();
})->with([
    'loopback' => '127.0.0.1',
    'class A private' => '10.0.0.1',
    'class C private' => '192.168.1.1',
]);

it('returns null when API returns fail status', function () {
    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'fail',
        ]),
    ]);

    $result = $this->service->geolocate('8.8.8.8');

    expect($result)->toBeNull();
});

it('returns null when API returns server error', function () {
    Http::fake([
        'ip-api.com/*' => Http::response('Server Error', 500),
    ]);

    $result = $this->service->geolocate('8.8.8.8');

    expect($result)->toBeNull();
});

it('returns cached result on second call without making HTTP request', function () {
    Cache::flush();

    Http::fake([
        'ip-api.com/*' => Http::response([
            'status' => 'success',
            'lat' => 37.7749,
            'lon' => -122.4194,
        ]),
    ]);

    $first = $this->service->geolocate('8.8.8.8');
    $second = $this->service->geolocate('8.8.8.8');

    expect($first)->toBe($second);
    Http::assertSentCount(1);
});

it('caches null results so failed lookups are not retried', function () {
    Cache::flush();

    Http::fake([
        'ip-api.com/*' => Http::response(['status' => 'fail']),
    ]);

    $first = $this->service->geolocate('8.8.8.8');
    $second = $this->service->geolocate('8.8.8.8');

    expect($first)->toBeNull();
    expect($second)->toBeNull();
    Http::assertSentCount(1);
});
