<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    /**
     * Display the main leaderboard ordered by hype score.
     */
    public function index(): Response
    {
        $sites = Site::active()
            ->orderByDesc('hype_score')
            ->paginate(25);

        return Inertia::render('Leaderboard/Index', [
            'sites' => $sites,
        ]);
    }

    /**
     * Display the user-rated leaderboard ordered by average user rating.
     */
    public function userRated(): Response
    {
        $sites = Site::active()
            ->orderByDesc('user_rating_avg')
            ->paginate(25);

        return Inertia::render('Leaderboard/UserRated', [
            'sites' => $sites,
        ]);
    }
}
