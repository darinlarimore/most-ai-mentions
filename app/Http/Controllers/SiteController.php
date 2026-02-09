<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitSiteRequest;
use App\Jobs\CrawlSiteJob;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    /**
     * Display a single site with its crawl results, score history, and ratings.
     */
    public function show(Site $site): Response
    {
        $site->load([
            'latestCrawlResult',
            'scoreHistories' => fn ($q) => $q->orderBy('recorded_at'),
            'ratings' => fn ($q) => $q->with('user:id,name')->latest(),
            'submitter:id,name',
        ]);

        return Inertia::render('Sites/Show', [
            'site' => $site,
        ]);
    }

    /**
     * Display the annotated/highlighted screenshot of a crawled site.
     */
    public function annotated(Site $site): Response
    {
        $crawlResult = $site->crawlResults()->latest()->first();

        $annotatedScreenshotUrl = null;
        if ($crawlResult?->annotated_screenshot_path) {
            $annotatedScreenshotUrl = Storage::disk('public')->url($crawlResult->annotated_screenshot_path);
        }

        return Inertia::render('Sites/Annotated', [
            'site' => $site,
            'annotatedScreenshotUrl' => $annotatedScreenshotUrl,
            'crawlResult' => $crawlResult,
        ]);
    }

    /**
     * Show the form to submit a new site.
     */
    public function create(): Response
    {
        return Inertia::render('Sites/Submit');
    }

    /**
     * Validate and store a newly submitted site, then queue a crawl.
     */
    public function store(SubmitSiteRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $site = Site::create([
            'url' => $validated['url'],
            'name' => $validated['name'] ?? null,
            'domain' => parse_url($validated['url'], PHP_URL_HOST),
            'submitted_by' => $request->user()?->id,
            'status' => 'queued',
        ]);

        CrawlSiteJob::dispatch($site);

        return redirect()->route('sites.show', $site);
    }
}
