<?php

namespace App\Http\Controllers;

use App\Http\Requests\RateRequest;
use App\Models\Rating;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;

class RatingController extends Controller
{
    /**
     * Create or update a rating for a site, keyed by IP address.
     */
    public function store(RateRequest $request, Site $site): RedirectResponse
    {
        $validated = $request->validated();

        Rating::updateOrCreate(
            [
                'ip_address' => $request->ip(),
                'site_id' => $site->id,
            ],
            [
                'user_id' => $request->user()?->id,
                'score' => $validated['score'],
                'comment' => $validated['comment'] ?? null,
            ],
        );

        $site->update([
            'user_rating_avg' => $site->ratings()->avg('score'),
            'user_rating_count' => $site->ratings()->count(),
        ]);

        return back();
    }
}
