<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CrawlQueueController extends Controller
{
    /**
     * Redirect old queue page to live crawl view.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('crawl.live');
    }

    /**
     * Display the live crawl view with queue and real-time progress.
     */
    public function live(): Response
    {
        $currentSite = Site::where('status', 'crawling')->first();

        return Inertia::render('Crawl/Live', [
            'currentSite' => $currentSite,
            'lastCrawledSite' => $currentSite ? null : Site::active()
                ->with('latestCrawlResult')
                ->whereNotNull('last_crawled_at')
                ->orderByDesc('last_crawled_at')
                ->first(),
            'queueCount' => Site::query()->crawlQueue()->count(),
            'queuedSites' => Inertia::scroll(fn () => Site::query()
                ->crawlQueue()
                ->simplePaginate(10)),
        ]);
    }
}
