<?php

use App\Services\HttpMetadataCollector;

it('detects redirect to a different host with a path as non-homepage', function () {
    expect(HttpMetadataCollector::isNonHomepageRedirect(
        'https://example.com',
        'https://login.example.org/oauth/authorize',
    ))->toBeTrue();
});

it('allows redirect to a different host homepage (domain rebrand)', function () {
    expect(HttpMetadataCollector::isNonHomepageRedirect(
        'https://obviously.ai',
        'https://www.zams.com/',
    ))->toBeFalse();
});

it('detects redirect to a path on the same host as non-homepage', function () {
    expect(HttpMetadataCollector::isNonHomepageRedirect(
        'https://example.com',
        'https://example.com/en/home',
    ))->toBeTrue();
});

it('allows redirect to same host homepage', function () {
    expect(HttpMetadataCollector::isNonHomepageRedirect(
        'https://example.com',
        'https://example.com/',
    ))->toBeFalse();
});

it('allows redirect to same host without trailing slash', function () {
    expect(HttpMetadataCollector::isNonHomepageRedirect(
        'https://example.com',
        'https://example.com',
    ))->toBeFalse();
});

it('allows www to non-www redirect on same host', function () {
    expect(HttpMetadataCollector::isNonHomepageRedirect(
        'https://www.example.com',
        'https://example.com/',
    ))->toBeFalse();
});

it('allows non-www to www redirect on same host', function () {
    expect(HttpMetadataCollector::isNonHomepageRedirect(
        'https://example.com',
        'https://www.example.com/',
    ))->toBeFalse();
});

it('allows redirect to index.html', function () {
    expect(HttpMetadataCollector::isNonHomepageRedirect(
        'https://example.com',
        'https://example.com/index.html',
    ))->toBeFalse();
});

it('detects redirect to dashboard path as non-homepage', function () {
    expect(HttpMetadataCollector::isNonHomepageRedirect(
        'https://example.com',
        'https://example.com/dashboard',
    ))->toBeTrue();
});

it('detects redirect to login path as non-homepage', function () {
    expect(HttpMetadataCollector::isNonHomepageRedirect(
        'https://example.com',
        'https://example.com/login',
    ))->toBeTrue();
});
