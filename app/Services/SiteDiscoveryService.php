<?php

namespace App\Services;

use App\Models\Site;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SiteDiscoveryService
{
    /** @var list<string> */
    private const G2_CATEGORY_URLS = [
        'https://www.g2.com/categories/ai-writing-assistant',
        'https://www.g2.com/categories/artificial-intelligence',
        'https://www.g2.com/categories/ai-chatbots',
        'https://www.g2.com/categories/ai-code-generation',
        'https://www.g2.com/categories/ai-image-generator',
    ];

    private const PRODUCTHUNT_URLS = [
        'https://www.producthunt.com/topics/artificial-intelligence',
    ];

    private const HN_URLS = [
        'https://news.ycombinator.com/best',
        'https://news.ycombinator.com/',
    ];

    /** @var list<string> */
    private const AI_KEYWORDS = [
        'ai', 'artificial intelligence', 'machine learning', 'llm', 'gpt',
        'chatbot', 'generative', 'neural', 'deep learning', 'copilot',
        'diffusion', 'transformer', 'large language model',
    ];

    /** @var array<string, string> Curated popular sites likely to mention AI heavily (url => name) */
    private const POPULAR_SITES = [
        // AI Companies & Products
        'https://openai.com' => 'OpenAI',
        'https://anthropic.com' => 'Anthropic',
        'https://deepmind.google' => 'Google DeepMind',
        'https://ai.meta.com' => 'Meta AI',
        'https://ai.google' => 'Google AI',
        'https://microsoft.com/ai' => 'Microsoft AI',
        'https://nvidia.com/ai' => 'NVIDIA AI',
        'https://stability.ai' => 'Stability AI',
        'https://midjourney.com' => 'Midjourney',
        'https://huggingface.co' => 'Hugging Face',
        'https://replicate.com' => 'Replicate',
        'https://cohere.com' => 'Cohere',
        'https://mistral.ai' => 'Mistral AI',
        'https://perplexity.ai' => 'Perplexity',
        'https://inflection.ai' => 'Inflection AI',
        'https://together.ai' => 'Together AI',
        'https://groq.com' => 'Groq',
        'https://fireworks.ai' => 'Fireworks AI',
        'https://anyscale.com' => 'Anyscale',
        'https://langchain.com' => 'LangChain',
        'https://llamaindex.ai' => 'LlamaIndex',
        'https://pinecone.io' => 'Pinecone',
        'https://weaviate.io' => 'Weaviate',
        'https://chromadb.dev' => 'Chroma',

        // AI-Powered Tools & Apps
        'https://jasper.ai' => 'Jasper',
        'https://copy.ai' => 'Copy.ai',
        'https://writesonic.com' => 'Writesonic',
        'https://grammarly.com' => 'Grammarly',
        'https://notion.so' => 'Notion',
        'https://canva.com' => 'Canva',
        'https://figma.com' => 'Figma',
        'https://adobe.com/sensei' => 'Adobe Sensei',
        'https://runway.ml' => 'Runway',
        'https://descript.com' => 'Descript',
        'https://synthesia.io' => 'Synthesia',
        'https://elevenlabs.io' => 'ElevenLabs',
        'https://cursor.com' => 'Cursor',
        'https://replit.com' => 'Replit',
        'https://vercel.com' => 'Vercel',
        'https://supabase.com' => 'Supabase',

        // AI Code & Dev Tools
        'https://github.com/features/copilot' => 'GitHub Copilot',
        'https://tabnine.com' => 'Tabnine',
        'https://codeium.com' => 'Codeium',
        'https://sourcegraph.com' => 'Sourcegraph',

        // AI News, Research & Community
        'https://techcrunch.com/category/artificial-intelligence' => 'TechCrunch AI',
        'https://theverge.com/ai-artificial-intelligence' => 'The Verge AI',
        'https://wired.com/tag/artificial-intelligence' => 'WIRED AI',
        'https://arstechnica.com/ai' => 'Ars Technica AI',
        'https://venturebeat.com/ai' => 'VentureBeat AI',
        'https://thenextweb.com/topic/artificial-intelligence' => 'TNW AI',
        'https://aiweirdness.com' => 'AI Weirdness',
        'https://bensbites.com' => "Ben's Bites",
        'https://therundown.ai' => 'The Rundown AI',

        // Enterprise AI Platforms
        'https://databricks.com' => 'Databricks',
        'https://snowflake.com' => 'Snowflake',
        'https://datadog.com' => 'Datadog',
        'https://scale.com' => 'Scale AI',
        'https://labelbox.com' => 'Labelbox',
        'https://weights-biases.com' => 'Weights & Biases',
        'https://neptune.ai' => 'Neptune.ai',
        'https://mlflow.org' => 'MLflow',

        // AI Chip & Hardware
        'https://cerebras.net' => 'Cerebras',
        'https://sambanova.ai' => 'SambaNova',
        'https://graphcore.ai' => 'Graphcore',

        // Honorable mentions — sites that love AI buzzwords
        'https://salesforce.com/artificial-intelligence' => 'Salesforce AI',
        'https://ibm.com/watson' => 'IBM Watson',
        'https://oracle.com/artificial-intelligence' => 'Oracle AI',
        'https://sap.com/products/artificial-intelligence.html' => 'SAP AI',
    ];

    /** @var list<string> Domains to skip (social media, generic, etc.) */
    private const EXCLUDED_DOMAINS = [
        'google.com', 'youtube.com', 'twitter.com', 'x.com', 'facebook.com',
        'linkedin.com', 'reddit.com', 'github.com', 'wikipedia.org',
        'news.ycombinator.com', 'g2.com', 'producthunt.com', 'amazonaws.com',
        'cloudfront.net', 'archive.org', 'web.archive.org',
    ];

    /**
     * Run all discovery sources and return total new sites added.
     */
    public function discoverAll(): int
    {
        $total = 0;

        $total += $this->discoverPopular()->count();
        $total += $this->discoverFromG2()->count();
        $total += $this->discoverFromProductHunt()->count();
        $total += $this->discoverFromHackerNews()->count();

        return $total;
    }

    /**
     * Add curated popular AI sites that are known to mention AI heavily.
     *
     * @return Collection<int, Site>
     */
    public function discoverPopular(): Collection
    {
        $created = collect();

        $existingDomains = Site::pluck('domain')->toArray();

        foreach (self::POPULAR_SITES as $url => $name) {
            $domain = parse_url($url, PHP_URL_HOST);

            if (! $domain || in_array($domain, $existingDomains)) {
                continue;
            }

            try {
                $site = Site::create([
                    'url' => $url,
                    'domain' => $domain,
                    'name' => $name,
                    'status' => 'queued',
                    'source' => 'curated',
                ]);

                $created->push($site);
                $existingDomains[] = $domain;
            } catch (\Throwable $e) {
                Log::debug("SiteDiscovery: Skipped {$url}", ['error' => $e->getMessage()]);
            }
        }

        return $created;
    }

    /**
     * Discover AI software sites from G2 category pages.
     *
     * @return Collection<int, Site>
     */
    public function discoverFromG2(): Collection
    {
        $urls = [];

        foreach (self::G2_CATEGORY_URLS as $categoryUrl) {
            try {
                $response = Http::withHeaders($this->browserHeaders())
                    ->timeout(15)
                    ->get($categoryUrl);

                if (! $response->successful()) {
                    continue;
                }

                $urls = array_merge($urls, $this->extractG2ProductUrls($response->body()));
            } catch (\Throwable $e) {
                Log::warning("SiteDiscovery: Failed to fetch G2 page {$categoryUrl}", ['error' => $e->getMessage()]);
            }
        }

        return $this->createSitesFromUrls($urls, 'g2');
    }

    /**
     * Discover AI sites from ProductHunt topics.
     *
     * @return Collection<int, Site>
     */
    public function discoverFromProductHunt(): Collection
    {
        $urls = [];

        foreach (self::PRODUCTHUNT_URLS as $topicUrl) {
            try {
                $response = Http::withHeaders($this->browserHeaders())
                    ->timeout(15)
                    ->get($topicUrl);

                if (! $response->successful()) {
                    continue;
                }

                $urls = array_merge($urls, $this->extractProductHuntUrls($response->body()));
            } catch (\Throwable $e) {
                Log::warning("SiteDiscovery: Failed to fetch ProductHunt page {$topicUrl}", ['error' => $e->getMessage()]);
            }
        }

        return $this->createSitesFromUrls($urls, 'producthunt');
    }

    /**
     * Discover AI-related sites from Hacker News.
     *
     * @return Collection<int, Site>
     */
    public function discoverFromHackerNews(): Collection
    {
        $urls = [];

        foreach (self::HN_URLS as $hnUrl) {
            try {
                $response = Http::withHeaders($this->browserHeaders())
                    ->timeout(15)
                    ->get($hnUrl);

                if (! $response->successful()) {
                    continue;
                }

                $urls = array_merge($urls, $this->extractHackerNewsUrls($response->body()));
            } catch (\Throwable $e) {
                Log::warning("SiteDiscovery: Failed to fetch HN page {$hnUrl}", ['error' => $e->getMessage()]);
            }
        }

        return $this->createSitesFromUrls($urls, 'hackernews');
    }

    /**
     * Extract product website URLs from G2 category HTML.
     *
     * @return list<string>
     */
    private function extractG2ProductUrls(string $html): array
    {
        $urls = [];
        $doc = $this->parseHtml($html);

        if (! $doc) {
            return $urls;
        }

        $xpath = new DOMXPath($doc);

        // G2 product cards link to product pages with data-href or href attributes
        $links = $xpath->query('//a[contains(@href, "/products/")]');

        if ($links) {
            foreach ($links as $link) {
                $href = $link->getAttribute('href');

                if (str_contains($href, '/products/') && ! str_contains($href, '/reviews')) {
                    // G2 product pages often contain the website URL; for now collect the product name from the URL
                    // and we'll try to extract the actual website from the product page
                    $productName = $this->extractG2ProductName($href);

                    if ($productName) {
                        $urls[] = "https://{$productName}.com";
                    }
                }
            }
        }

        // Also look for direct outbound links to product websites
        $outboundLinks = $xpath->query('//a[contains(@class, "website") or contains(@data-action, "visit")]');

        if ($outboundLinks) {
            foreach ($outboundLinks as $link) {
                $href = $link->getAttribute('href');

                if ($this->isValidExternalUrl($href)) {
                    $urls[] = $href;
                }
            }
        }

        return $urls;
    }

    /**
     * Extract product URLs from ProductHunt HTML.
     *
     * @return list<string>
     */
    private function extractProductHuntUrls(string $html): array
    {
        $urls = [];
        $doc = $this->parseHtml($html);

        if (! $doc) {
            return $urls;
        }

        $xpath = new DOMXPath($doc);

        // ProductHunt posts have links to external product websites
        $links = $xpath->query('//a[contains(@href, "http")]');

        if ($links) {
            foreach ($links as $link) {
                $href = $link->getAttribute('href');

                if ($this->isValidExternalUrl($href)) {
                    $urls[] = $href;
                }
            }
        }

        return $urls;
    }

    /**
     * Extract AI-related URLs from Hacker News HTML.
     *
     * @return list<string>
     */
    private function extractHackerNewsUrls(string $html): array
    {
        $urls = [];
        $doc = $this->parseHtml($html);

        if (! $doc) {
            return $urls;
        }

        $xpath = new DOMXPath($doc);

        // HN story links are in <span class="titleline"><a href="...">Title</a></span>
        $storyLinks = $xpath->query('//span[contains(@class, "titleline")]/a');

        if ($storyLinks) {
            foreach ($storyLinks as $link) {
                $href = $link->getAttribute('href');
                $title = strtolower($link->textContent ?? '');

                // Only include links whose titles contain AI-related keywords
                $isAiRelated = false;

                foreach (self::AI_KEYWORDS as $keyword) {
                    if (str_contains($title, $keyword)) {
                        $isAiRelated = true;

                        break;
                    }
                }

                if ($isAiRelated && $this->isValidExternalUrl($href)) {
                    $urls[] = $href;
                }
            }
        }

        return $urls;
    }

    /**
     * Create Site records from a list of discovered URLs, skipping duplicates.
     *
     * @param  list<string>  $urls
     * @return Collection<int, Site>
     */
    private function createSitesFromUrls(array $urls, string $source): Collection
    {
        $created = collect();

        $normalizedUrls = collect($urls)
            ->map(fn (string $url) => $this->normalizeUrl($url))
            ->filter()
            ->unique();

        // Get existing domains to skip
        $existingDomains = Site::whereIn('domain', $normalizedUrls->map(
            fn (string $url) => parse_url($url, PHP_URL_HOST)
        )->filter())->pluck('domain')->toArray();

        foreach ($normalizedUrls as $url) {
            $domain = parse_url($url, PHP_URL_HOST);

            if (! $domain || in_array($domain, $existingDomains)) {
                continue;
            }

            if ($this->isExcludedDomain($domain)) {
                continue;
            }

            try {
                $site = Site::create([
                    'url' => $url,
                    'domain' => $domain,
                    'name' => $this->domainToName($domain),
                    'status' => 'queued',
                    'source' => $source,
                ]);

                $created->push($site);
                $existingDomains[] = $domain;
            } catch (\Throwable $e) {
                // Likely a duplicate URL constraint violation, skip
                Log::debug("SiteDiscovery: Skipped {$url}", ['error' => $e->getMessage()]);
            }
        }

        return $created;
    }

    /**
     * Normalize a URL to its homepage.
     */
    public function normalizeUrl(string $url): ?string
    {
        // Ensure URL has a scheme
        if (! str_starts_with($url, 'http')) {
            $url = "https://{$url}";
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return null;
        }

        // Strip www prefix for normalization
        $host = preg_replace('/^www\./', '', $host);

        return "https://{$host}";
    }

    /**
     * Check if a URL is a valid external URL worth crawling.
     */
    private function isValidExternalUrl(string $url): bool
    {
        if (! str_starts_with($url, 'http')) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return false;
        }

        return ! $this->isExcludedDomain($host);
    }

    /**
     * Check if a domain should be excluded.
     */
    private function isExcludedDomain(string $domain): bool
    {
        $domain = preg_replace('/^www\./', '', $domain);

        foreach (self::EXCLUDED_DOMAINS as $excluded) {
            if ($domain === $excluded || str_ends_with($domain, ".{$excluded}")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract product name from a G2 product URL.
     */
    private function extractG2ProductName(string $href): ?string
    {
        if (preg_match('#/products/([a-z0-9-]+)#i', $href, $matches)) {
            return str_replace('-', '', $matches[1]);
        }

        return null;
    }

    /**
     * Convert a domain to a human-readable name.
     */
    private function domainToName(string $domain): string
    {
        $name = preg_replace('/^www\./', '', $domain);
        // Remove TLD
        $name = preg_replace('/\.[a-z]{2,}$/i', '', $name);
        // Remove secondary TLD (e.g. .co from .co.uk)
        $name = preg_replace('/\.[a-z]{2,}$/i', '', $name);
        // Convert hyphens/dots to spaces and title-case
        $name = str_replace(['-', '.'], ' ', $name);

        return ucwords($name);
    }

    /**
     * Parse HTML string into a DOMDocument.
     */
    private function parseHtml(string $html): ?DOMDocument
    {
        if (empty($html)) {
            return null;
        }

        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        return $doc;
    }

    /**
     * @return array<string, string>
     */
    private function browserHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (compatible; MostAIMentions/1.0; +https://mostai.mentions)',
            'Accept' => 'text/html,application/xhtml+xml',
        ];
    }
}
