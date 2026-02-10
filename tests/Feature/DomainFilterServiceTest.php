<?php

use App\Services\DomainFilterService;

beforeEach(function () {
    $this->filter = new DomainFilterService;
});

it('blocks domains with adult TLDs', function (string $domain) {
    expect($this->filter->isBlocked($domain))->toBeTrue();
})->with([
    'example.xxx',
    'example.porn',
    'example.sex',
    'example.adult',
    'example.sexy',
    'example.cam',
]);

it('blocks known adult domains', function (string $domain) {
    expect($this->filter->isBlocked($domain))->toBeTrue();
})->with([
    'pornhub.com',
    'xvideos.com',
    'xhamster.com',
    'onlyfans.com',
    'chaturbate.com',
]);

it('blocks subdomains of known adult domains', function () {
    expect($this->filter->isBlocked('www.pornhub.com'))->toBeTrue();
    expect($this->filter->isBlocked('m.xvideos.com'))->toBeTrue();
});

it('blocks domains containing adult keywords', function (string $domain) {
    expect($this->filter->isBlocked($domain))->toBeTrue();
})->with([
    'free-porn-site.com',
    'xxxvideos.net',
    'myhentai.org',
]);

it('allows legitimate tech domains', function (string $domain) {
    expect($this->filter->isBlocked($domain))->toBeFalse();
})->with([
    'openai.com',
    'anthropic.com',
    'google.com',
    'stripe.com',
    'laravel.com',
    'github.com',
    'vercel.com',
    'remix.run',
]);

it('does not false positive on domains containing partial matches', function () {
    expect($this->filter->isBlocked('essexcounty.gov'))->toBeFalse();
    expect($this->filter->isBlocked('camebridge.org'))->toBeFalse();
});
