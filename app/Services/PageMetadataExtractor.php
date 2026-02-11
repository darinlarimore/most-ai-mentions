<?php

namespace App\Services;

class PageMetadataExtractor
{
    /**
     * Extract the page title from HTML.
     */
    public function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $match)) {
            $title = html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Trim to reasonable length
            return mb_strlen($title) > 255 ? mb_substr($title, 0, 255) : ($title ?: null);
        }

        return null;
    }

    /**
     * Extract the meta description from HTML.
     */
    public function extractDescription(string $html): ?string
    {
        // Match <meta name="description" content="..."> (with flexible attribute ordering)
        if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/si', $html, $match)) {
            $desc = html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return $desc ?: null;
        }

        // Also handle content before name ordering
        if (preg_match('/<meta[^>]*content=["\']([^"\']*)["\'][^>]*name=["\']description["\'][^>]*>/si', $html, $match)) {
            $desc = html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return $desc ?: null;
        }

        return null;
    }
}
