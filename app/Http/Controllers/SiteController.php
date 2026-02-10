<?php

namespace App\Http\Controllers;

use App\Enums\SiteCategory;
use App\Http\Requests\SubmitSiteRequest;
use App\Jobs\CrawlSiteJob;
use App\Models\CrawlResult;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
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
            'scoreAverages' => CrawlResult::query()->selectRaw('
                ROUND(AVG(mention_score)) as mention_score,
                ROUND(AVG(font_size_score)) as font_size_score,
                ROUND(AVG(animation_score)) as animation_score,
                ROUND(AVG(visual_effects_score)) as visual_effects_score,
                ROUND(AVG(ai_image_hype_bonus)) as ai_image_hype_bonus,
                ROUND(AVG(lighthouse_perf_bonus)) as lighthouse_perf_bonus,
                ROUND(AVG(lighthouse_a11y_bonus)) as lighthouse_a11y_bonus,
                ROUND(AVG(total_score)) as total_score
            ')->first(),
        ]);
    }

    /**
     * Display the annotated/highlighted screenshot of a crawled site.
     */
    public function annotated(Site $site): Response
    {
        $crawlResult = $site->crawlResults()->latest()->first();

        return Inertia::render('Sites/Annotated', [
            'site' => $site,
            'annotatedScreenshotUrl' => $crawlResult?->annotated_screenshot_path,
            'crawlResult' => $crawlResult,
        ]);
    }

    /**
     * Show the form to submit a new site.
     */
    public function create(): Response
    {
        return Inertia::render('Sites/Submit', [
            'categories' => collect(SiteCategory::cases())
                ->reject(fn (SiteCategory $c) => $c === SiteCategory::Other)
                ->map(fn (SiteCategory $c) => ['value' => $c->value, 'label' => $c->label()])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Validate and store a newly submitted site, then queue a crawl.
     */
    public function store(SubmitSiteRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Normalize to homepage URL
        $parsed = parse_url($validated['url']);
        $host = preg_replace('/^www\./', '', $parsed['host'] ?? '');
        $normalizedUrl = ($parsed['scheme'] ?? 'https')."://{$host}";

        // Check for existing site by domain (handles race conditions past validation)
        $existing = Site::where('domain', $host)->first();
        if ($existing) {
            return redirect()->route('sites.show', $existing)
                ->with('info', 'This site has already been submitted! Here are its results.');
        }

        $site = Site::create([
            'url' => $normalizedUrl,
            'name' => $validated['name'] ?? null,
            'domain' => $host,
            'category' => $validated['category'] ?? 'other',
            'submitted_by' => $request->user()?->id,
            'status' => 'queued',
        ]);

        CrawlSiteJob::dispatch($site);

        return redirect()->route('sites.show', $site);
    }
}
