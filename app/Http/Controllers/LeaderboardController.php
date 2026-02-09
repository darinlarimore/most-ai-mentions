<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    /**
     * Display the main leaderboard ordered by hype score.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        $sites = Site::active()
            ->whereNotNull('last_crawled_at')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('domain', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('hype_score')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Leaderboard/Index', [
            'sites' => $sites,
            'search' => $search,
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
            ->paginate(25);

        return Inertia::render('Leaderboard/UserRated', [
            'sites' => $sites,
        ]);
    }
}
