<?php

use App\Services\HttpMetadataCollector;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->collector = app(HttpMetadataCollector::class);
});

it('returns structured metadata array', function () {
    Http::fake([
        'https://example.com' => Http::response('<html></html>', 200, [
            'Server' => 'nginx',
            'Content-Type' => 'text/html; charset=UTF-8',
        ]),
    ]);

    $result = $this->collector->collect('https://example.com');

    expect($result)
        ->toHaveKeys([
            'server_ip',
            'server_software',
            'redirect_chain',
            'final_url',
            'response_time_ms',
            'tls_issuer',
            'headers',
        ])
        ->and($result['final_url'])->toBe('https://example.com')
        ->and($result['redirect_chain'])->toBe([])
        ->and($result['response_time_ms'])->toBeInt()
        ->and($result['headers'])->toBeArray();
});

it('captures redirect chain', function () {
    Http::fake([
        'https://old.example.com' => Http::response('', 301, [
            'Location' => 'https://mid.example.com',
        ]),
        'https://mid.example.com' => Http::response('', 302, [
            'Location' => 'https://new.example.com',
        ]),
        'https://new.example.com' => Http::response('<html></html>', 200, [
            'Server' => 'apache',
        ]),
    ]);

    $result = $this->collector->collect('https://old.example.com');

    expect($result['redirect_chain'])->toBe([
        ['url' => 'https://old.example.com', 'status' => 301],
        ['url' => 'https://mid.example.com', 'status' => 302],
    ])
        ->and($result['final_url'])->toBe('https://new.example.com');
});

it('captures server software from headers', function () {
    Http::fake([
        'https://example.com' => Http::response('<html></html>', 200, [
            'Server' => 'nginx/1.24.0',
        ]),
    ]);

    $result = $this->collector->collect('https://example.com');

    expect($result['server_software'])->toBe('nginx/1.24.0')
        ->and($result['headers']['server'])->toBe('nginx/1.24.0');
});

it('throws on connection failure', function () {
    Http::fake([
        'https://unreachable.example.com' => fn () => throw new ConnectionException('Connection refused'),
    ]);

    $this->collector->collect('https://unreachable.example.com');
})->throws(ConnectionException::class);

it('resolves server ip via dns lookup', function () {
    Http::fake([
        'https://example.com' => Http::response('<html></html>', 200),
    ]);

    $result = $this->collector->collect('https://example.com');

    // example.com has a real DNS record, so server_ip should be a valid IP
    expect($result['server_ip'])->not->toBeNull()
        ->and(filter_var($result['server_ip'], FILTER_VALIDATE_IP))->not->toBeFalse();
});

it('extracts only interesting headers', function () {
    Http::fake([
        'https://example.com' => Http::response('<html></html>', 200, [
            'Server' => 'cloudflare',
            'X-Powered-By' => 'PHP/8.4',
            'Content-Type' => 'text/html',
            'CF-Ray' => 'abc123',
            'X-Vercel-Id' => 'iad1::12345',
            'X-Request-Id' => 'should-be-excluded',
            'Cache-Control' => 'should-be-excluded',
        ]),
    ]);

    $result = $this->collector->collect('https://example.com');

    expect($result['headers'])
        ->toHaveKeys(['server', 'x-powered-by', 'content-type', 'cf-ray', 'x-vercel-id'])
        ->not->toHaveKeys(['x-request-id', 'cache-control']);
});

it('handles relative redirect locations', function () {
    Http::fake([
        'https://example.com/old' => Http::response('', 301, [
            'Location' => '/new-page',
        ]),
        'https://example.com/new-page' => Http::response('<html></html>', 200),
    ]);

    $result = $this->collector->collect('https://example.com/old');

    expect($result['redirect_chain'])->toBe([
        ['url' => 'https://example.com/old', 'status' => 301],
    ])
        ->and($result['final_url'])->toBe('https://example.com/new-page');
});

it('returns null server software when header is absent', function () {
    Http::fake([
        'https://example.com' => Http::response('<html></html>', 200),
    ]);

    $result = $this->collector->collect('https://example.com');

    expect($result['server_software'])->toBeNull();
});
