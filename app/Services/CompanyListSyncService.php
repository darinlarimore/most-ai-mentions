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
     * Fetch Fortune 500 companies.
     *
     * @return array<int, array{company: string, domain: string, rank: int|null}>
     */
    public function fetchFortune500(): array
    {
        $results = [];

        // Fortune API paginates at 100 items per request
        for ($offset = 0; $offset < 500; $offset += 100) {
            $response = $this->http
                ->timeout(self::TIMEOUT)
                ->get("https://fortune.com/api/v2/list/1141696/expand/item/ranking/asc/{$offset}/100");

            $response->throw();

            $items = $response->json('list-items') ?? $response->json('items') ?? [];

            foreach ($items as $item) {
                $fields = $item['fields'] ?? $item;
                $name = $fields['title'] ?? $fields['name'] ?? null;
                $website = $fields['website'] ?? $fields['website_url'] ?? null;
                $rank = $fields['rank'] ?? null;

                if (! $name) {
                    continue;
                }

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

            // Forbes provides URI slugs; derive domain from company website field if available
            $website = $org['website'] ?? null;
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
     * Fetch Inc. 5000 fastest-growing companies.
     *
     * @return array<int, array{company: string, domain: string, rank: int|null}>
     */
    public function fetchInc5000(): array
    {
        $year = now()->year;

        $response = $this->http
            ->timeout(self::TIMEOUT)
            ->get("https://www.inc.com/inc5000list/json/inc5000_{$year}.json");

        $response->throw();

        $companies = $response->json();
        $results = [];

        foreach ($companies as $company) {
            $name = $company['company'] ?? $company['ifc_company'] ?? null;
            $website = $company['website'] ?? $company['ifc_url'] ?? null;
            $rank = $company['rank'] ?? $company['ifc_firank'] ?? null;

            if (! $name || ! $website) {
                continue;
            }

            $domain = $this->extractDomain($website);

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
