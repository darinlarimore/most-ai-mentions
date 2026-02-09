<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Inertia\Inertia;
use Inertia\Response;

class CrawlQueueController extends Controller
{
    /**
     * Display the crawl queue with queued and currently crawling sites.
     */
    public function index(): Response
    {
        return Inertia::render('Crawl/Queue', [
            'queuedSites' => Site::active()
                ->readyToCrawl()
                ->where('status', '!=', 'crawling')
                ->orderByRaw('submitted_by IS NOT NULL DESC')
                ->orderByRaw('last_crawled_at IS NULL DESC')
                ->orderBy('last_crawled_at')
                ->get(),
            'currentlyCrawling' => Site::where('status', 'crawling')->first(),
        ]);
    }

    /**
     * Display the currently crawling site with live info.
     */
    public function live(): Response
    {
        return Inertia::render('Crawl/Live', [
            'currentSite' => Site::where('status', 'crawling')->first(),
        ]);
    }
}
