<?php

namespace App\Http\Controllers;

use App\Enums\CrawlErrorCategory;
use App\Models\CrawlError;
use App\Models\CrawlResult;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class InsightsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Insights/Index', [
            'pipelineStats' => $this->getPipelineStats(),
            'hostingMap' => Inertia::defer(fn () => $this->getHostingMapData(), 'map'),
            'termFrequency' => Inertia::defer(fn () => $this->getTermFrequency(), 'charts'),
            'techStackDistribution' => Inertia::defer(fn () => $this->getTechStackDistribution(), 'charts'),
            'scoreDistribution' => Inertia::defer(fn () => $this->getScoreDistribution(), 'charts'),
            'mentionsVsScore' => Inertia::defer(fn () => $this->getMentionsVsScore(), 'charts'),
            'crawlerSpeed' => Inertia::defer(fn () => $this->getCrawlerSpeed(), 'charts'),
            'crawlErrors' => Inertia::defer(fn () => $this->getCrawlErrors(), 'charts'),
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json($this->getPipelineStats());
    }

    /**
     * Return all chart datasets as JSON for incremental real-time updates.
     */
    public function charts(): JsonResponse
    {
        return response()->json([
            'termFrequency' => $this->getTermFrequency(),
            'techStackDistribution' => $this->getTechStackDistribution(),
            'scoreDistribution' => $this->getScoreDistribution(),
            'mentionsVsScore' => $this->getMentionsVsScore(),
            'crawlErrors' => $this->getCrawlErrors(),
        ]);
    }

    /**
     * Return nodes + links for the Sites ↔ AI Terms force graph.
     *
     * @return array{nodes: list<array<string, mixed>>, links: list<array<string, mixed>>}
     */
    public function network(): JsonResponse
    {
        $sites = Site::active()
            ->whereNotNull('last_crawled_at')
            ->with(['latestCrawlResult' => fn ($q) => $q->select('crawl_results.id', 'crawl_results.site_id', 'crawl_results.mention_details')])
            ->select(['id', 'domain', 'slug', 'category', 'hype_score'])
            ->orderByDesc('last_crawled_at')
            ->limit(500)
            ->get()
            ->filter(fn (Site $site) => ! empty($site->latestCrawlResult?->mention_details));

        // Collect unique terms per site
        $termCounts = []; // term → number of sites mentioning it
        $siteTerms = [];  // site_id → [terms]

        foreach ($sites as $site) {
            $terms = collect($site->latestCrawlResult->mention_details)
                ->pluck('text')
                ->map(fn ($t) => mb_strtolower(trim($t)))
                ->unique()
                ->values()
                ->all();

            $siteTerms[$site->id] = $terms;

            foreach ($terms as $term) {
                $termCounts[$term] = ($termCounts[$term] ?? 0) + 1;
            }
        }

        // Keep only terms on 2+ sites
        $termCounts = array_filter($termCounts, fn ($c) => $c >= 2);

        $nodes = [];
        $links = [];

        foreach ($sites as $site) {
            $relevantTerms = array_intersect($siteTerms[$site->id] ?? [], array_keys($termCounts));
            if (empty($relevantTerms)) {
                continue;
            }

            $nodes[] = [
                'id' => 'site:'.$site->id,
                'type' => 'site',
                'label' => $site->domain,
                'slug' => $site->slug,
                'category' => $site->category,
                'score' => $site->hype_score,
            ];

            foreach ($relevantTerms as $term) {
                $links[] = [
                    'source' => 'site:'.$site->id,
                    'target' => 'term:'.$term,
                ];
            }
        }

        // Add term nodes
        foreach ($termCounts as $term => $count) {
            $nodes[] = [
                'id' => 'term:'.$term,
                'type' => 'term',
                'label' => $term,
                'count' => $count,
            ];
        }

        return response()->json(['nodes' => $nodes, 'links' => $links]);
    }

    /**
     * @return array{total_sites: int, crawled_sites: int, queued_sites: int, total_crawls: int}
     */
    private function getPipelineStats(): array
    {
        return [
            'total_sites' => Site::active()->count(),
            'crawled_sites' => Site::active()->whereNotNull('last_crawled_at')->count(),
            'queued_sites' => Site::query()->crawlQueue()->count(),
            'total_crawls' => CrawlResult::count(),
        ];
    }

    /**
     * Aggregate AI term frequency from crawl result mention_details.
     *
     * @return list<array{term: string, count: int}>
     */
    private function getTermFrequency(): array
    {
        $results = CrawlResult::query()
            ->whereNotNull('mention_details')
            ->where('mention_details', '!=', '[]')
            ->latest()
            ->limit(500)
            ->pluck('mention_details');

        $terms = [];
        foreach ($results as $details) {
            $mentions = is_array($details) ? $details : json_decode($details, true);
            if (! is_array($mentions)) {
                continue;
            }
            foreach ($mentions as $mention) {
                $text = mb_strtolower(trim($mention['text'] ?? ''));
                if ($text !== '') {
                    $terms[$text] = ($terms[$text] ?? 0) + 1;
                }
            }
        }

        arsort($terms);

        return collect($terms)
            ->take(30)
            ->map(fn (int $count, string $term) => ['term' => $term, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * Aggregate tech stack across active sites.
     *
     * @return list<array{tech: string, count: int}>
     */
    private function getTechStackDistribution(): array
    {
        $sites = Site::active()
            ->whereNotNull('tech_stack')
            ->pluck('tech_stack');

        $techs = [];
        foreach ($sites as $stack) {
            $items = is_array($stack) ? $stack : json_decode($stack, true);
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $tech) {
                $techs[$tech] = ($techs[$tech] ?? 0) + 1;
            }
        }

        arsort($techs);

        return collect($techs)
            ->take(20)
            ->map(fn (int $count, string $tech) => ['tech' => $tech, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * Group active crawled sites by category.
     *
     * @return list<array{category: string, count: int}>
     */
    /**
     * Bucket hype scores into ranges for a histogram.
     *
     * @return list<array{range: string, count: int}>
     */
    private function getScoreDistribution(): array
    {
        $sites = Site::active()
            ->whereNotNull('last_crawled_at')
            ->pluck('hype_score');

        $buckets = [
            '0-100' => 0,
            '101-300' => 0,
            '301-500' => 0,
            '501-1000' => 0,
            '1001-2000' => 0,
            '2000+' => 0,
        ];

        foreach ($sites as $score) {
            if ($score <= 100) {
                $buckets['0-100']++;
            } elseif ($score <= 300) {
                $buckets['101-300']++;
            } elseif ($score <= 500) {
                $buckets['301-500']++;
            } elseif ($score <= 1000) {
                $buckets['501-1000']++;
            } elseif ($score <= 2000) {
                $buckets['1001-2000']++;
            } else {
                $buckets['2000+']++;
            }
        }

        return collect($buckets)
            ->map(fn (int $count, string $range) => ['range' => $range, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * Scatter data: mentions vs hype score per site.
     *
     * @return list<array{domain: string, mentions: int, score: int}>
     */
    private function getMentionsVsScore(): array
    {
        return Site::active()
            ->whereNotNull('last_crawled_at')
            ->with(['latestCrawlResult' => fn ($q) => $q->select('crawl_results.id', 'crawl_results.site_id', 'crawl_results.ai_mention_count')])
            ->select(['id', 'domain', 'slug', 'hype_score'])
            ->get()
            ->filter(fn (Site $site) => $site->latestCrawlResult !== null)
            ->map(fn (Site $site) => [
                'domain' => $site->domain,
                'slug' => $site->slug,
                'mentions' => $site->latestCrawlResult->ai_mention_count ?? 0,
                'score' => $site->hype_score,
            ])
            ->values()
            ->all();
    }

    /**
     * Sites with geocoded server locations for the world map.
     *
     * @return list<array{domain: string, slug: string, latitude: float, longitude: float, hype_score: int}>
     */
    private function getHostingMapData(): array
    {
        return Site::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotNull('last_crawled_at')
            ->select(['domain', 'slug', 'latitude', 'longitude', 'hype_score'])
            ->get()
            ->map(fn (Site $site) => [
                'domain' => $site->domain,
                'slug' => $site->slug,
                'latitude' => $site->latitude,
                'longitude' => $site->longitude,
                'hype_score' => $site->hype_score,
            ])
            ->all();
    }

    /**
     * Last 200 individual crawl durations for the real-time horizon chart.
     *
     * @return list<array{timestamp: string, duration_ms: int}>
     */
    private function getCrawlerSpeed(): array
    {
        return CrawlResult::query()
            ->whereNotNull('crawl_duration_ms')
            ->orderByDesc('created_at')
            ->limit(200)
            ->select(['id', 'created_at', 'crawl_duration_ms', 'site_id'])
            ->withCount('crawlErrors')
            ->get()
            ->map(fn ($row) => [
                'timestamp' => $row->created_at->toISOString(),
                'duration_ms' => $row->crawl_duration_ms,
                'has_error' => $row->crawl_errors_count > 0,
            ])
            ->reverse()->values()->all();
    }

    /**
     * Crawl error analytics: by category, over time, and top failing domains.
     *
     * @return array{by_category: list<array{label: string, value: int}>, over_time: list<array<string, mixed>>, top_domains: list<array{label: string, value: int}>}
     */
    private function getCrawlErrors(): array
    {
        return [
            'by_category' => $this->getCrawlErrorsByCategory(),
            'over_time' => $this->getCrawlErrorsOverTime(),
            'top_domains' => $this->getCrawlErrorTopDomains(),
        ];
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function getCrawlErrorsByCategory(): array
    {
        return CrawlError::query()
            ->withCasts(['category' => 'string'])
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'label' => CrawlErrorCategory::tryFrom($row->category)?->label() ?? $row->category,
                'value' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getCrawlErrorsOverTime(): array
    {
        $since = now()->subDays(30)->startOfDay();

        $rows = CrawlError::query()
            ->withCasts(['category' => 'string'])
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, category, COUNT(*) as count')
            ->groupBy('date', 'category')
            ->orderBy('date')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $date = $row->date;
            if (! isset($grouped[$date])) {
                $grouped[$date] = ['date' => $date];
            }
            $label = CrawlErrorCategory::tryFrom($row->category)?->label() ?? $row->category;
            $grouped[$date][$label] = (int) $row->count;
        }

        return array_values($grouped);
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function getCrawlErrorTopDomains(): array
    {
        return CrawlError::query()
            ->join('sites', 'crawl_errors.site_id', '=', 'sites.id')
            ->selectRaw('sites.domain, COUNT(*) as count')
            ->groupBy('sites.domain')
            ->orderByDesc('count')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->domain,
                'value' => (int) $row->count,
            ])
            ->values()
            ->all();
    }
}
