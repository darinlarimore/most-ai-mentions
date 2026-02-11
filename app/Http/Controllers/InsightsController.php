<?php

namespace App\Http\Controllers;

use App\Models\CrawlResult;
use App\Models\ScoreHistory;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InsightsController extends Controller
{
    public function index(): Response
    {
        $totalSites = Site::active()->count();
        $crawledSites = Site::active()->whereNotNull('last_crawled_at')->count();
        $pendingSites = Site::active()->whereNull('last_crawled_at')->count();
        $totalCrawls = CrawlResult::count();

        return Inertia::render('Insights/Index', [
            'pipelineStats' => [
                'total_sites' => $totalSites,
                'crawled_sites' => $crawledSites,
                'pending_sites' => $pendingSites,
                'total_crawls' => $totalCrawls,
            ],
            'termFrequency' => Inertia::defer(fn () => $this->getTermFrequency()),
            'techStackDistribution' => Inertia::defer(fn () => $this->getTechStackDistribution()),
            'serverDistribution' => Inertia::defer(fn () => $this->getServerDistribution(), 'metadata'),
            'categoryBreakdown' => Inertia::defer(fn () => $this->getCategoryBreakdown(), 'metadata'),
            'scoreDistribution' => Inertia::defer(fn () => $this->getScoreDistribution(), 'metadata'),
            'mentionsVsScore' => Inertia::defer(fn () => $this->getMentionsVsScore(), 'scatter'),
            'hostingMap' => Inertia::defer(fn () => $this->getHostingMapData(), 'map'),
            'scoreTimeline' => Inertia::defer(fn () => $this->getScoreTimeline(), 'timeline'),
        ]);
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
     * Group active sites by server software.
     *
     * @return list<array{server: string, count: int}>
     */
    private function getServerDistribution(): array
    {
        return Site::active()
            ->whereNotNull('server_software')
            ->select('server_software', DB::raw('count(*) as count'))
            ->groupBy('server_software')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['server' => $row->server_software, 'count' => $row->count])
            ->all();
    }

    /**
     * Group active crawled sites by category.
     *
     * @return list<array{category: string, count: int}>
     */
    private function getCategoryBreakdown(): array
    {
        return Site::active()
            ->whereNotNull('last_crawled_at')
            ->select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => ['category' => $row->category, 'count' => $row->count])
            ->all();
    }

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
     * Daily average hype scores over the last 60 days for the horizon chart.
     *
     * @return list<array{date: string, value: float}>
     */
    private function getScoreTimeline(): array
    {
        $driver = DB::connection()->getDriverName();

        $dateExpr = $driver === 'sqlite'
            ? 'date(recorded_at) as day'
            : 'DATE(recorded_at) as day';

        return ScoreHistory::query()
            ->where('recorded_at', '>=', now()->subDays(60))
            ->selectRaw("{$dateExpr}, AVG(hype_score) as avg_score")
            ->groupByRaw($driver === 'sqlite' ? 'date(recorded_at)' : 'DATE(recorded_at)')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->day,
                'value' => round((float) $row->avg_score, 1),
            ])
            ->all();
    }
}
