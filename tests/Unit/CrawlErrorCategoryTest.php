<?php

use App\Enums\CrawlErrorCategory;

it('classifies timeout errors', function () {
    $e = new \RuntimeException('cURL error 28: Operation timed out after 30000 milliseconds', 28);
    expect(CrawlErrorCategory::fromThrowable($e))->toBe(CrawlErrorCategory::Timeout);
});

it('classifies timeout from message', function () {
    $e = new \RuntimeException('Connection timed out');
    expect(CrawlErrorCategory::fromThrowable($e))->toBe(CrawlErrorCategory::Timeout);
});

it('classifies DNS failures', function () {
    $e = new \RuntimeException('cURL error 6: Could not resolve host: example.com', 6);
    expect(CrawlErrorCategory::fromThrowable($e))->toBe(CrawlErrorCategory::DnsFailure);
});

it('classifies connection errors', function () {
    $e = new \RuntimeException('cURL error 7: Failed to connect to example.com port 443', 7);
    expect(CrawlErrorCategory::fromThrowable($e))->toBe(CrawlErrorCategory::ConnectionError);
});

it('classifies SSL errors', function () {
    $e = new \RuntimeException('cURL error 60: SSL certificate problem: unable to get local issuer certificate', 60);
    expect(CrawlErrorCategory::fromThrowable($e))->toBe(CrawlErrorCategory::SslError);
});

it('classifies SSL errors from message', function () {
    $e = new \RuntimeException('SSL handshake failed');
    expect(CrawlErrorCategory::fromThrowable($e))->toBe(CrawlErrorCategory::SslError);
});

it('classifies blocked responses', function () {
    $e = new \RuntimeException('Client error: 403 Forbidden');
    expect(CrawlErrorCategory::fromThrowable($e))->toBe(CrawlErrorCategory::Blocked);
});

it('classifies 429 as blocked', function () {
    $e = new \RuntimeException('Client error: 429 Too Many Requests');
    expect(CrawlErrorCategory::fromThrowable($e))->toBe(CrawlErrorCategory::Blocked);
});

it('classifies HTTP client errors', function () {
    $e = new \RuntimeException('Client error: 404 Not Found');
    expect(CrawlErrorCategory::fromThrowable($e))->toBe(CrawlErrorCategory::HttpClientError);
});

it('classifies HTTP server errors', function () {
    $e = new \RuntimeException('Server error: 502 Bad Gateway');
    expect(CrawlErrorCategory::fromThrowable($e))->toBe(CrawlErrorCategory::HttpServerError);
});

it('classifies empty responses', function () {
    $e = new \RuntimeException('cURL error 52: Empty reply from server', 52);
    expect(CrawlErrorCategory::fromThrowable($e))->toBe(CrawlErrorCategory::EmptyResponse);
});

it('classifies parse errors', function () {
    $e = new \DOMException('Parse error in document');
    expect(CrawlErrorCategory::fromThrowable($e))->toBe(CrawlErrorCategory::ParseError);
});

it('falls back to unknown', function () {
    $e = new \RuntimeException('Something completely unexpected happened');
    expect(CrawlErrorCategory::fromThrowable($e))->toBe(CrawlErrorCategory::Unknown);
});

it('has labels for all cases', function () {
    foreach (CrawlErrorCategory::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
});
