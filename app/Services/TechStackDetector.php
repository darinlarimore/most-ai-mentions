<?php

namespace App\Services;

class TechStackDetector
{
    /** @var list<string> Patterns for meta generator detection: pattern => technology name */
    private const GENERATOR_PATTERNS = [
        'WordPress' => 'wordpress',
        'Drupal' => 'drupal',
        'Joomla' => 'joomla',
        'Ghost' => 'ghost',
        'Hugo' => 'hugo',
        'Jekyll' => 'jekyll',
        'Wix' => 'wix',
        'Squarespace' => 'squarespace',
        'Webflow' => 'webflow',
    ];

    /** @var array<string, string> Script source patterns: regex fragment => technology name */
    private const SCRIPT_SRC_PATTERNS = [
        '/wp-content/|/wp-includes/' => 'WordPress',
        'react-dom|react\.production|react\.development' => 'React',
        '/_next/' => 'Next.js',
        'vue\.js|vue\.min\.js|vue\.global' => 'Vue',
        'angular' => 'Angular',
        'svelte' => 'Svelte',
        'jquery' => 'jQuery',
        'alpinejs|alpine\.js' => 'Alpine.js',
        'htmx' => 'HTMX',
        'cdn\.shopify\.com' => 'Shopify',
        'gatsby' => 'Gatsby',
        'astro' => 'Astro',
        'bootstrap' => 'Bootstrap',
    ];

    /** @var array<string, string> HTML attribute patterns: regex => technology name */
    private const HTML_ATTRIBUTE_PATTERNS = [
        'data-reactroot' => 'React',
        'id=["\']__next["\']' => 'Next.js',
        'id=["\']__nuxt["\']' => 'Nuxt',
        'data-v-[a-f0-9]' => 'Vue',
        'ng-version|ng-app' => 'Angular',
        'data-svelte' => 'Svelte',
        'x-data|x-bind' => 'Alpine.js',
        'hx-get|hx-post' => 'HTMX',
    ];

    /** @var array<string, string> Inline JS patterns: regex => technology name */
    private const INLINE_PATTERNS = [
        '__NEXT_DATA__' => 'Next.js',
        '__NUXT__' => 'Nuxt',
        '\$\(document\)\.ready|\bjQuery\(' => 'jQuery',
        '_wq|wix\.com' => 'Wix',
        'squarespace' => 'Squarespace',
    ];

    /** @var list<string> Tailwind utility class patterns for heuristic detection */
    private const TAILWIND_CLASS_PATTERNS = [
        'flex', 'grid', 'text-sm', 'text-lg', 'text-xl', 'text-xs',
        'bg-', 'px-', 'py-', 'pt-', 'pb-', 'pl-', 'pr-',
        'rounded-', 'mt-', 'mb-', 'ml-', 'mr-', 'mx-', 'my-',
        'font-bold', 'font-medium', 'font-semibold',
        'items-center', 'justify-center', 'space-x-', 'space-y-',
        'w-full', 'h-full', 'max-w-', 'min-h-',
    ];

    /** Minimum number of Tailwind utility class matches to confirm Tailwind CSS */
    private const TAILWIND_MIN_MATCHES = 5;

    /** @var list<string> Bootstrap CSS class patterns */
    private const BOOTSTRAP_CLASS_PATTERNS = [
        'container-fluid',
        'col-md-',
        'col-lg-',
        'col-sm-',
        'btn btn-',
        'row',
    ];

    /** @var array<string, string> Server header patterns: regex fragment => technology name */
    private const SERVER_HEADER_PATTERNS = [
        'cloudflare' => 'Cloudflare',
        'vercel' => 'Vercel',
        'netlify' => 'Netlify',
        'nginx' => 'Nginx',
        'apache' => 'Apache',
    ];

    /** @var array<string, string> X-Powered-By header patterns: regex fragment => technology name */
    private const POWERED_BY_PATTERNS = [
        'PHP' => 'PHP',
        'ASP\.NET' => 'ASP.NET',
        'Express' => 'Node.js',
    ];

    /**
     * Detect technologies used by a website from its HTML content and HTTP headers.
     *
     * @param  array<string, string>  $headers  HTTP response headers (keys should be lowercase)
     * @return list<string> Sorted, deduplicated list of detected technology names
     */
    public function detect(string $html, array $headers = []): array
    {
        $technologies = array_merge(
            $this->detectFromMetaGenerator($html),
            $this->detectFromScriptSources($html),
            $this->detectFromHtmlAttributes($html),
            $this->detectFromInlinePatterns($html),
            $this->detectFromCssPatterns($html),
            $this->detectFromHeaders($headers),
        );

        $technologies = array_unique($technologies);
        sort($technologies);

        return array_values($technologies);
    }

    /**
     * Detect technologies from <meta name="generator"> tags.
     *
     * @return list<string>
     */
    private function detectFromMetaGenerator(string $html): array
    {
        $technologies = [];

        if (preg_match_all('/<meta\s[^>]*name=["\']generator["\']\s[^>]*content=["\']([^"\']+)["\']/si', $html, $matches)) {
            foreach ($matches[1] as $content) {
                foreach (self::GENERATOR_PATTERNS as $name => $keyword) {
                    if (preg_match('/'.preg_quote($keyword, '/').'/i', $content)) {
                        $technologies[] = $name;
                    }
                }
            }
        }

        // Also handle reversed attribute order: content before name
        if (preg_match_all('/<meta\s[^>]*content=["\']([^"\']+)["\']\s[^>]*name=["\']generator["\']/si', $html, $matches)) {
            foreach ($matches[1] as $content) {
                foreach (self::GENERATOR_PATTERNS as $name => $keyword) {
                    if (preg_match('/'.preg_quote($keyword, '/').'/i', $content)) {
                        $technologies[] = $name;
                    }
                }
            }
        }

        return $technologies;
    }

    /**
     * Detect technologies from <script src="..."> URLs.
     *
     * @return list<string>
     */
    private function detectFromScriptSources(string $html): array
    {
        $technologies = [];

        if (! preg_match_all('/<script\s[^>]*src=["\']([^"\']+)["\']/si', $html, $matches)) {
            return $technologies;
        }

        // Also check <link href="..."> for CSS frameworks
        $linkHrefs = [];
        if (preg_match_all('/<link\s[^>]*href=["\']([^"\']+)["\']/si', $html, $linkMatches)) {
            $linkHrefs = $linkMatches[1];
        }

        foreach ($matches[1] as $src) {
            foreach (self::SCRIPT_SRC_PATTERNS as $pattern => $name) {
                if (preg_match('#'.$pattern.'#i', $src)) {
                    $technologies[] = $name;
                }
            }
        }

        // Check link hrefs for Bootstrap CSS
        foreach ($linkHrefs as $href) {
            if (preg_match('/bootstrap/i', $href)) {
                $technologies[] = 'Bootstrap';
            }
        }

        return $technologies;
    }

    /**
     * Detect technologies from HTML element attributes.
     *
     * @return list<string>
     */
    private function detectFromHtmlAttributes(string $html): array
    {
        $technologies = [];

        foreach (self::HTML_ATTRIBUTE_PATTERNS as $pattern => $name) {
            if (preg_match('/'.$pattern.'/i', $html)) {
                $technologies[] = $name;
            }
        }

        return $technologies;
    }

    /**
     * Detect technologies from inline JavaScript patterns.
     *
     * @return list<string>
     */
    private function detectFromInlinePatterns(string $html): array
    {
        $technologies = [];

        // Extract inline script content only (scripts without src attribute)
        if (! preg_match_all('/<script(?![^>]*\bsrc=)[^>]*>(.*?)<\/script>/si', $html, $matches)) {
            return $technologies;
        }

        $inlineContent = implode(' ', $matches[1]);

        foreach (self::INLINE_PATTERNS as $pattern => $name) {
            if (preg_match('/'.$pattern.'/i', $inlineContent)) {
                $technologies[] = $name;
            }
        }

        return $technologies;
    }

    /**
     * Detect CSS frameworks from class usage patterns in HTML.
     *
     * @return list<string>
     */
    private function detectFromCssPatterns(string $html): array
    {
        $technologies = [];

        // Detect Tailwind CSS by counting utility class matches
        $tailwindMatches = 0;
        foreach (self::TAILWIND_CLASS_PATTERNS as $pattern) {
            if (preg_match('/class=["\'][^"\']*'.preg_quote($pattern, '/').'[^"\']*["\']/i', $html)) {
                $tailwindMatches++;
            }
        }

        if ($tailwindMatches >= self::TAILWIND_MIN_MATCHES) {
            $technologies[] = 'Tailwind CSS';
        }

        // Detect Bootstrap from link hrefs
        if (preg_match('/<link\s[^>]*href=["\'][^"\']*bootstrap[^"\']*["\']/si', $html)) {
            $technologies[] = 'Bootstrap';
        }

        // Detect Bootstrap from class patterns
        $bootstrapMatches = 0;
        foreach (self::BOOTSTRAP_CLASS_PATTERNS as $pattern) {
            if (preg_match('/class=["\'][^"\']*'.preg_quote($pattern, '/').'[^"\']*["\']/i', $html)) {
                $bootstrapMatches++;
            }
        }

        if ($bootstrapMatches >= 3) {
            $technologies[] = 'Bootstrap';
        }

        return $technologies;
    }

    /**
     * Detect technologies from HTTP response headers.
     *
     * @param  array<string, string>  $headers  HTTP response headers (keys should be lowercase)
     * @return list<string>
     */
    private function detectFromHeaders(array $headers): array
    {
        $technologies = [];

        // Check server header
        $server = $headers['server'] ?? '';
        if ($server !== '') {
            foreach (self::SERVER_HEADER_PATTERNS as $pattern => $name) {
                if (preg_match('/'.$pattern.'/i', $server)) {
                    $technologies[] = $name;
                }
            }
        }

        // Check x-powered-by header
        $poweredBy = $headers['x-powered-by'] ?? '';
        if ($poweredBy !== '') {
            foreach (self::POWERED_BY_PATTERNS as $pattern => $name) {
                if (preg_match('/'.$pattern.'/i', $poweredBy)) {
                    $technologies[] = $name;
                }
            }
        }

        // Check for presence-based headers
        if (array_key_exists('x-vercel-id', $headers)) {
            $technologies[] = 'Vercel';
        }

        if (array_key_exists('x-nf-request-id', $headers)) {
            $technologies[] = 'Netlify';
        }

        return $technologies;
    }
}
