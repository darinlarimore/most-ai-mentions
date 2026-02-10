<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $sites = Site::query()
            ->active()
            ->whereNotNull('last_crawled_at')
            ->select(['slug', 'updated_at'])
            ->orderByDesc('updated_at')
            ->get();

        $urls = collect([
            ['loc' => url('/'), 'changefreq' => 'hourly', 'priority' => '1.0'],
            ['loc' => url('/user-rated'), 'changefreq' => 'hourly', 'priority' => '0.8'],
            ['loc' => url('/algorithm'), 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => url('/crawl/live'), 'changefreq' => 'always', 'priority' => '0.7'],
            ['loc' => url('/submit'), 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => url('/donate'), 'changefreq' => 'monthly', 'priority' => '0.4'],
        ]);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $entry) {
            $xml .= '  <url>'."\n";
            $xml .= '    <loc>'.e($entry['loc']).'</loc>'."\n";
            $xml .= '    <changefreq>'.$entry['changefreq'].'</changefreq>'."\n";
            $xml .= '    <priority>'.$entry['priority'].'</priority>'."\n";
            $xml .= '  </url>'."\n";
        }

        foreach ($sites as $site) {
            $xml .= '  <url>'."\n";
            $xml .= '    <loc>'.e(url('/sites/'.$site->slug)).'</loc>'."\n";
            $xml .= '    <lastmod>'.$site->updated_at->toW3cString().'</lastmod>'."\n";
            $xml .= '    <changefreq>weekly</changefreq>'."\n";
            $xml .= '    <priority>0.6</priority>'."\n";
            $xml .= '  </url>'."\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
