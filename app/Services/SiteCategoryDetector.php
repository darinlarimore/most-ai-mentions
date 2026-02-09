<?php

namespace App\Services;

use App\Enums\SiteCategory;

class SiteCategoryDetector
{
    /** Minimum keyword matches required to assign a category. */
    private const MIN_THRESHOLD = 2;

    /** @var array<string, list<string>> */
    private const CATEGORY_KEYWORDS = [
        'saas' => [
            'saas', 'software as a service', 'cloud platform', 'subscription software',
            'cloud-based', 'free trial', 'pricing plans', 'per user per month',
            'sign up free', 'start your free', 'monthly plan', 'annual plan',
        ],
        'software' => [
            'developer tools', 'sdk', 'api platform', 'open source', 'devtools',
            'ide', 'code editor', 'programming', 'framework', 'developer experience',
            'documentation', 'cli', 'runtime', 'compiler',
        ],
        'tech' => [
            'artificial intelligence', 'machine learning', 'deep learning', 'data science',
            'cloud computing', 'cybersecurity', 'iot', 'neural network', 'large language model',
            'generative ai', 'foundation model', 'computer vision', 'nlp',
        ],
        'healthcare' => [
            'healthcare', 'medical', 'clinical', 'patient', 'hospital',
            'telemedicine', 'pharma', 'biotech', 'hipaa', 'health tech',
            'electronic health', 'diagnostics', 'therapeutics',
        ],
        'finance' => [
            'fintech', 'banking', 'investment', 'insurance', 'payments',
            'trading', 'financial services', 'wealth management', 'lending',
            'credit', 'neobank', 'payment processing', 'financial technology',
        ],
        'education' => [
            'edtech', 'online courses', 'e-learning', 'lms', 'learning management',
            'training platform', 'university', 'academic', 'curriculum',
            'student', 'educational', 'certification', 'online learning',
        ],
        'ecommerce' => [
            'ecommerce', 'e-commerce', 'online store', 'shopping', 'marketplace',
            'retail', 'checkout', 'buy now', 'product catalog', 'shopify',
            'add to cart', 'online shop', 'storefront',
        ],
        'marketing' => [
            'marketing platform', 'seo tool', 'content marketing', 'email marketing',
            'digital marketing', 'advertising', 'lead generation', 'marketing automation',
            'conversion rate', 'campaign', 'growth marketing', 'demand generation',
        ],
        'agency' => [
            'digital agency', 'creative agency', 'design agency', 'web agency',
            'our clients', 'case studies', 'portfolio', 'we build', 'full-service agency',
            'branding agency', 'creative studio', 'web design agency',
        ],
        'consulting' => [
            'consulting firm', 'consultancy', 'advisory', 'strategy consulting',
            'management consulting', 'business consulting', 'thought leadership',
            'digital transformation', 'implementation partner', 'professional services',
        ],
        'startup' => [
            'seed funding', 'series a', 'series b', 'venture', 'incubator',
            'accelerator', 'pitch deck', 'mvp', 'pre-seed', 'fundraising',
            'venture capital', 'backed by', 'raised',
        ],
        'enterprise' => [
            'enterprise-grade', 'fortune 500', 'enterprise solution', 'enterprise software',
            'soc 2', 'iso 27001', 'enterprise plan', 'dedicated support',
            'custom deployment', 'on-premise', 'enterprise security',
        ],
        'media' => [
            'news', 'journalism', 'publishing', 'content platform', 'podcast',
            'streaming', 'video platform', 'editorial', 'newsletter platform',
            'media company', 'broadcast', 'press', 'newsroom',
        ],
        'company' => [
            'about us', 'our team', 'our mission', 'corporate', 'headquarters',
            'founded in', 'our values', 'careers page', 'join our team',
            'company culture', 'who we are', 'meet the team',
        ],
    ];

    /**
     * Detect the most likely category for a site based on its HTML metadata.
     */
    public function detect(string $html): SiteCategory
    {
        $text = $this->extractMetadataText($html);

        if ($text === '') {
            return SiteCategory::Other;
        }

        $scores = $this->scoreCategories($text);

        $best = array_keys($scores, max($scores))[0];

        if ($scores[$best] < self::MIN_THRESHOLD) {
            return SiteCategory::Other;
        }

        return SiteCategory::from($best);
    }

    /**
     * Extract text from HTML metadata sources (title, meta tags, og tags, JSON-LD).
     */
    public function extractMetadataText(string $html): string
    {
        $parts = [];

        // <title>
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
            $parts[] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        }

        // <meta name="description|keywords" content="...">
        if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/si', $html, $m)) {
            $parts[] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        }
        if (preg_match('/<meta\s+content=["\'](.*?)["\']\s+name=["\']description["\']/si', $html, $m)) {
            $parts[] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        }
        if (preg_match('/<meta\s+name=["\']keywords["\']\s+content=["\'](.*?)["\']/si', $html, $m)) {
            $parts[] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        }
        if (preg_match('/<meta\s+content=["\'](.*?)["\']\s+name=["\']keywords["\']/si', $html, $m)) {
            $parts[] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        }

        // <meta property="og:title|og:description" content="...">
        foreach (['og:title', 'og:description'] as $prop) {
            if (preg_match('/<meta\s+property=["\']'.preg_quote($prop, '/').'["\']\s+content=["\'](.*?)["\']/si', $html, $m)) {
                $parts[] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            }
            if (preg_match('/<meta\s+content=["\'](.*?)["\']\s+property=["\']'.preg_quote($prop, '/').'["\']/si', $html, $m)) {
                $parts[] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            }
        }

        // JSON-LD
        if (preg_match_all('/<script\s+type=["\']application\/ld\+json["\']\s*>(.*?)<\/script>/si', $html, $matches)) {
            foreach ($matches[1] as $json) {
                $data = json_decode(trim($json), true);
                if (is_array($data)) {
                    $this->extractJsonLdText($data, $parts);
                }
            }
        }

        return mb_strtolower(implode(' ', $parts));
    }

    /**
     * Score each category by counting keyword matches in the text.
     *
     * @return array<string, int>
     */
    public function scoreCategories(string $text): array
    {
        $scores = [];

        foreach (self::CATEGORY_KEYWORDS as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                $score += substr_count($text, $keyword);
            }
            $scores[$category] = $score;
        }

        return $scores;
    }

    /**
     * Recursively extract text from JSON-LD data.
     *
     * @param  array<mixed>  $data
     * @param  list<string>  $parts
     */
    private function extractJsonLdText(array $data, array &$parts): void
    {
        // Handle @graph arrays
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $item) {
                if (is_array($item)) {
                    $this->extractJsonLdText($item, $parts);
                }
            }

            return;
        }

        foreach (['@type', 'name', 'description', 'about', 'headline', 'alternateName'] as $field) {
            if (isset($data[$field])) {
                if (is_string($data[$field])) {
                    $parts[] = $data[$field];
                } elseif (is_array($data[$field])) {
                    $parts[] = implode(' ', array_filter($data[$field], 'is_string'));
                }
            }
        }
    }
}
