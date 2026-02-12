<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class HttpMetadataCollector
{
    private const MAX_REDIRECTS = 10;

    private const TIMEOUT = 15;

    /** @var list<string> Headers worth preserving from the response */
    private const INTERESTING_HEADERS = [
        'server',
        'x-powered-by',
        'content-type',
        'x-vercel-id',
        'x-nf-request-id',
        'cf-ray',
        'x-generator',
        'via',
    ];

    /**
     * Collect HTTP metadata from a URL including DNS, redirects, headers, and TLS info.
     *
     * @return array{
     *     server_ip: string|null,
     *     server_software: string|null,
     *     redirect_chain: list<array{url: string, status: int}>,
     *     final_url: string,
     *     response_time_ms: int,
     *     tls_issuer: string|null,
     *     headers: array<string, string>,
     * }
     */
    public function collect(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        $serverIp = $this->resolveServerIp($host);

        $currentUrl = $url;
        $redirectChain = [];
        $startTime = microtime(true);
        $response = null;

        for ($i = 0; $i < self::MAX_REDIRECTS; $i++) {
            $response = Http::withOptions([
                'allow_redirects' => false,
                'timeout' => self::TIMEOUT,
                'connect_timeout' => 5,
                'verify' => true,
            ])->get($currentUrl);

            $status = $response->status();

            if ($status >= 300 && $status < 400) {
                $redirectChain[] = ['url' => $currentUrl, 'status' => $status];
                $location = $response->header('Location');

                if (! $location) {
                    break;
                }

                if (! str_starts_with($location, 'http')) {
                    $parsed = parse_url($currentUrl);
                    $location = $parsed['scheme'].'://'.$parsed['host'].$location;
                }

                $currentUrl = $location;

                continue;
            }

            break;
        }

        $responseTimeMs = (int) round((microtime(true) - $startTime) * 1000);

        $serverSoftware = $response?->header('Server');
        $headers = $this->extractInterestingHeaders($response);
        $tlsIssuer = $this->extractTlsIssuer($host);

        return [
            'server_ip' => $serverIp,
            'server_software' => $serverSoftware ?: null,
            'redirect_chain' => $redirectChain,
            'final_url' => $currentUrl,
            'response_time_ms' => $responseTimeMs,
            'tls_issuer' => $tlsIssuer,
            'headers' => $headers,
        ];
    }

    /**
     * Determine if the final URL after redirects is a non-homepage URL.
     *
     * Returns true if the final URL has a meaningful path (anything beyond "/"
     * or common homepage variants like "/index.html"). Redirects to a different
     * host's homepage (e.g. domain rebrand) are allowed.
     */
    public static function isNonHomepageRedirect(string $originalUrl, string $finalUrl): bool
    {
        $finalPath = parse_url($finalUrl, PHP_URL_PATH) ?? '/';
        $finalPath = rtrim($finalPath, '/');

        // Empty path or just "/" is a homepage (even on a different host)
        if ($finalPath === '' || $finalPath === '/') {
            return false;
        }

        // Common homepage paths that are acceptable
        $homepagePaths = ['/index.html', '/index.htm', '/index.php'];
        if (in_array(mb_strtolower($finalPath), $homepagePaths, true)) {
            return false;
        }

        return true;
    }

    /**
     * Resolve the server IP address for a hostname via DNS lookup.
     */
    private function resolveServerIp(?string $host): ?string
    {
        if (! $host) {
            return null;
        }

        $ip = gethostbyname($host);

        return $ip !== $host ? $ip : null;
    }

    /**
     * Extract only the interesting headers from the HTTP response.
     *
     * @return array<string, string>
     */
    private function extractInterestingHeaders(?\Illuminate\Http\Client\Response $response): array
    {
        if (! $response) {
            return [];
        }

        $headers = [];

        foreach (self::INTERESTING_HEADERS as $name) {
            $value = $response->header($name);

            if ($value !== null && $value !== '') {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * Extract the TLS certificate issuer organization from the host's SSL certificate.
     */
    private function extractTlsIssuer(?string $host): ?string
    {
        if (! $host) {
            return null;
        }

        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
        ]]);

        $client = @stream_socket_client(
            "ssl://{$host}:443",
            $errno,
            $errstr,
            5,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if (! $client) {
            return null;
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $cert = $params['options']['ssl']['peer_certificate'] ?? null;

        if (! $cert) {
            return null;
        }

        $parsed = openssl_x509_parse($cert);

        return $parsed['issuer']['O'] ?? $parsed['issuer']['CN'] ?? null;
    }
}
