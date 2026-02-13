<?php

namespace App\Services;

use Illuminate\Http\Client\Factory;

class CompanyListSyncService
{
    private const TIMEOUT = 30;

    public function __construct(private Factory $http) {}

    /**
     * Fetch top Y Combinator companies with websites.
     *
     * @return array<int, array{company: string, domain: string, rank: int|null}>
     */
    public function fetchYCombinator(): array
    {
        $response = $this->http
            ->timeout(self::TIMEOUT)
            ->get('https://yc-oss.github.io/api/companies/all.json');

        $response->throw();

        $companies = $response->json();
        $results = [];
        $rank = 0;

        foreach ($companies as $company) {
            $website = $company['website'] ?? null;
            $name = $company['name'] ?? null;

            if (! $website || ! $name) {
                continue;
            }

            $domain = $this->extractDomain($website);

            if (! $domain) {
                continue;
            }

            $rank++;
            $results[] = [
                'company' => $name,
                'domain' => $domain,
                'rank' => $rank,
            ];
        }

        return $results;
    }

    /**
     * Fetch Forbes Global 2000 companies.
     *
     * @return array<int, array{company: string, domain: string, rank: int|null}>
     */
    public function fetchForbesGlobal2000(): array
    {
        $year = now()->year;

        $response = $this->http
            ->timeout(self::TIMEOUT)
            ->get("https://www.forbes.com/forbesapi/org/global2000/{$year}/position/true.json", [
                'limit' => 2000,
            ]);

        $response->throw();

        $organizations = $response->json('organizationList.organizationsLists') ?? [];
        $results = [];

        foreach ($organizations as $org) {
            $name = $org['organizationName'] ?? $org['name'] ?? null;
            $uri = $org['uri'] ?? null;
            $rank = $org['position'] ?? $org['rank'] ?? null;

            if (! $name || ! $uri) {
                continue;
            }

            $website = $org['webSite'] ?? null;
            $domain = $website ? $this->extractDomain($website) : null;

            if (! $domain) {
                continue;
            }

            $results[] = [
                'company' => $name,
                'domain' => $domain,
                'rank' => is_numeric($rank) ? (int) $rank : null,
            ];
        }

        return $results;
    }

    /**
     * Extract and normalize a domain from a URL or domain string.
     */
    public function extractDomain(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || $url === '#') {
            return null;
        }

        // Add scheme if missing so parse_url works
        if (! str_contains($url, '://')) {
            $url = 'https://'.$url;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return null;
        }

        // Strip www. prefix and lowercase
        $domain = strtolower(preg_replace('/^www\./', '', $host));

        // Basic domain validation
        if (! str_contains($domain, '.') || strlen($domain) < 4) {
            return null;
        }

        return $domain;
    }
}
