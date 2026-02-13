<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\Robots\RobotsTxt;

class RobotsTxtChecker
{
    private const USER_AGENT = 'MostAIMentions';

    private const TIMEOUT_SECONDS = 5;

    /**
     * Check whether crawling the homepage of the given URL is allowed by robots.txt.
     */
    public function isAllowed(string $url): bool
    {
        try {
            $parsed = parse_url($url);
            $robotsUrl = ($parsed['scheme'] ?? 'https').'://'.($parsed['host'] ?? '').'/robots.txt';

            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withoutVerifying()
                ->get($robotsUrl);

            if ($response->failed()) {
                return true;
            }

            $robotsTxt = new RobotsTxt($response->body());

            return $robotsTxt->allows('/', self::USER_AGENT);
        } catch (\Throwable $e) {
            Log::debug("robots.txt check failed for {$url}: {$e->getMessage()}");

            return true;
        }
    }
}
