<?php

namespace App\Http\Controllers;

use App\Http\Requests\BatchSubmitSiteRequest;
use App\Http\Requests\SubmitSiteRequest;
use App\Jobs\CrawlSiteJob;
use App\Models\CrawlResult;
use App\Models\Site;
use App\Services\DomainFilterService;
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
            'latestCrawlError',
            'scoreHistories' => fn ($q) => $q->orderBy('recorded_at'),
            'ratings' => fn ($q) => $q->with('user:id,name')->latest(),
            'submitter:id,name',
        ]);

        return Inertia::render('Sites/Show', [
            'site' => $site,
            'scoreAverages' => CrawlResult::query()->selectRaw('
                ROUND(AVG(density_score)) as density_score,
                ROUND(AVG(mention_score)) as mention_score,
                ROUND(AVG(font_size_score)) as font_size_score,
                ROUND(AVG(animation_score)) as animation_score,
                ROUND(AVG(visual_effects_score)) as visual_effects_score,
                ROUND(AVG(total_score)) as total_score
            ')->first(),
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

        // Normalize to homepage URL
        $parsed = parse_url($validated['url']);
        $host = preg_replace('/^www\./', '', $parsed['host'] ?? '');
        $normalizedUrl = ($parsed['scheme'] ?? 'https')."://{$host}";

        // Block inappropriate domains
        if (app(DomainFilterService::class)->isBlocked($host)) {
            return back()->withErrors(['url' => 'This site cannot be added.']);
        }

        // Check for existing site by domain or slug (handles www variants and race conditions)
        $slug = Site::generateSlug($host);
        $existing = Site::where('domain', $host)
            ->orWhere('domain', "www.{$host}")
            ->orWhere('slug', $slug)
            ->first();
        if ($existing) {
            return redirect()->route('sites.show', $existing);
        }

        $site = Site::create([
            'url' => $normalizedUrl,
            'domain' => $host,
            'category' => 'other',
            'submitted_by' => $request->user()?->id,
            'source' => 'submitted',
            'status' => 'queued',
        ]);

        CrawlSiteJob::dispatch($site);

        return redirect()->route('sites.show', $site)
            ->with('submitted_site', ['id' => $site->id, 'url' => $site->url, 'slug' => $site->slug]);
    }

    /**
     * Batch submit multiple sites from a list of URLs.
     */
    public function storeBatch(BatchSubmitSiteRequest $request): RedirectResponse
    {
        $urls = preg_split('/[\n,]+/', $request->validated()['urls']);
        $urls = array_filter(array_map('trim', $urls));

        $domainFilter = app(DomainFilterService::class);
        $added = 0;
        $skipped = 0;
        $invalid = 0;
        $createdSites = [];

        foreach ($urls as $rawUrl) {
            // Add scheme if missing
            if (! preg_match('#^https?://#i', $rawUrl)) {
                $rawUrl = 'https://'.$rawUrl;
            }

            if (! filter_var($rawUrl, FILTER_VALIDATE_URL)) {
                $invalid++;

                continue;
            }

            $parsed = parse_url($rawUrl);
            $host = preg_replace('/^www\./', '', $parsed['host'] ?? '');

            if (! $host || $domainFilter->isBlocked($host)) {
                $invalid++;

                continue;
            }

            $normalizedUrl = ($parsed['scheme'] ?? 'https')."://{$host}";
            $slug = Site::generateSlug($host);

            $existing = Site::where('domain', $host)
                ->orWhere('domain', "www.{$host}")
                ->orWhere('slug', $slug)
                ->exists();

            if ($existing) {
                $skipped++;

                continue;
            }

            $site = Site::create([
                'url' => $normalizedUrl,
                'domain' => $host,
                'category' => 'other',
                'submitted_by' => $request->user()?->id,
                'source' => 'submitted',
                'status' => 'queued',
            ]);

            CrawlSiteJob::dispatch($site);
            $createdSites[] = ['id' => $site->id, 'url' => $site->url, 'slug' => $site->slug];
            $added++;
        }

        $parts = [];
        if ($added > 0) {
            $parts[] = "{$added} site".($added !== 1 ? 's' : '').' added';
        }
        if ($skipped > 0) {
            $parts[] = "{$skipped} duplicate".($skipped !== 1 ? 's' : '').' skipped';
        }
        if ($invalid > 0) {
            $parts[] = "{$invalid} invalid URL".($invalid !== 1 ? 's' : '').' skipped';
        }

        $message = implode(', ', $parts).'.';

        if ($added === 0) {
            return back()->withErrors(['urls' => "No new sites were added. {$message}"]);
        }

        return back()->with('success', $message)->with('submitted_sites', $createdSites);
    }
}
