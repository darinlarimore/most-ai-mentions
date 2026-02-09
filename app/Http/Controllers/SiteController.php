<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitSiteRequest;
use App\Jobs\CrawlSiteJob;
use App\Models\Site;
use App\Services\HtmlAnnotationService;
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
            'canRate' => auth()->check() && ! $site->ratings()->where('user_id', auth()->id())->exists(),
        ]);
    }

    /**
     * Display the annotated/highlighted version of a crawled site.
     */
    public function annotated(Site $site, HtmlAnnotationService $annotationService): Response
    {
        $crawlResult = $site->crawlResults()->latest()->first();

        $annotatedHtml = '';
        if ($crawlResult) {
            // Use cached annotated HTML if available, otherwise generate on the fly
            if ($crawlResult->annotated_html) {
                $annotatedHtml = $crawlResult->annotated_html;
            } elseif ($crawlResult->crawled_html) {
                $annotatedHtml = $annotationService->annotate(
                    $crawlResult->crawled_html,
                    $crawlResult->mention_details ?? [],
                    [
                        'total_score' => $crawlResult->total_score,
                        'mention_score' => $crawlResult->mention_score,
                        'font_size_score' => $crawlResult->font_size_score,
                        'animation_score' => $crawlResult->animation_score,
                        'visual_effects_score' => $crawlResult->visual_effects_score,
                        'lighthouse_perf_bonus' => $crawlResult->lighthouse_perf_bonus,
                        'lighthouse_a11y_bonus' => $crawlResult->lighthouse_a11y_bonus,
                        'ai_mention_count' => $crawlResult->ai_mention_count,
                        'animation_count' => $crawlResult->animation_count,
                        'glow_effect_count' => $crawlResult->glow_effect_count,
                        'rainbow_border_count' => $crawlResult->rainbow_border_count,
                        'ai_image_count' => $crawlResult->ai_image_count,
                        'ai_image_hype_bonus' => $crawlResult->ai_image_hype_bonus,
                    ],
                    $crawlResult->ai_image_details ?? [],
                );

                // Cache for next time
                $crawlResult->update(['annotated_html' => $annotatedHtml]);
            }
        }

        return Inertia::render('Sites/Annotated', [
            'site' => $site,
            'annotatedHtml' => $annotatedHtml,
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
