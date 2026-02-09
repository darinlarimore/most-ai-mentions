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
        return Inertia::render('Crawl/Live', [
            'currentSite' => Site::where('status', 'crawling')->first(),
            'queuedSites' => Inertia::defer(fn () => Site::active()
                ->where('status', '!=', 'crawling')
                ->orderByRaw('submitted_by IS NOT NULL AND last_crawled_at IS NULL DESC')
                ->orderByRaw('last_crawled_at IS NULL DESC')
                ->orderBy('last_crawled_at')
                ->get()),
        ]);
    }
}
