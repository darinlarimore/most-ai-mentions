<?php

namespace App\Http\Controllers;

use App\Enums\SiteCategory;
use App\Models\CrawlResult;
use App\Models\Site;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    /**
     * Display the main leaderboard with time-period and sort filters.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $category = $request->string('category')->trim()->value();
        $period = $request->string('period')->trim()->value() ?: 'all';
        $sort = $request->string('sort')->trim()->value() ?: 'hype_score';

        $periodDate = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            'year' => now()->subYear(),
            default => null,
        };

        $query = Site::active()
            ->whereNotNull('last_crawled_at')
            ->with('latestCrawlResult')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('domain', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($category, function ($query, $category) {
                $query->where('category', $category);
            })
            ->when($periodDate, function ($query) use ($periodDate) {
                $query->where('last_crawled_at', '>=', $periodDate);
            });

        match ($sort) {
            'mentions' => $query->orderByDesc(
                CrawlResult::select('ai_mention_count')
                    ->whereColumn('crawl_results.site_id', 'sites.id')
                    ->latest()
                    ->limit(1)
            ),
            'user_rating' => $query->orderByDesc('user_rating_avg'),
            'newest' => $query->orderByDesc('last_crawled_at'),
            'recently_added' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('hype_score'),
        };

        $sites = $query->paginate(24)->withQueryString();

        $categories = collect(SiteCategory::cases())->map(fn (SiteCategory $c) => [
            'value' => $c->value,
            'label' => $c->label(),
        ])->all();

        return Inertia::render('Leaderboard/Index', [
            'sites' => $sites,
            'search' => $search,
            'category' => $category,
            'categories' => $categories,
            'period' => $period !== 'all' ? $period : '',
            'sort' => $sort !== 'hype_score' ? $sort : '',
        ]);
    }

    /**
     * Display the user-rated leaderboard ordered by average user rating.
     */
    public function userRated(): Response
    {
        $sites = Site::active()
            ->whereNotNull('last_crawled_at')
            ->orderByDesc('user_rating_avg')
            ->paginate(24);

        return Inertia::render('Leaderboard/UserRated', [
            'sites' => $sites,
        ]);
    }
}
