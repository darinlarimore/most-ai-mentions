<?php

namespace App\Http\Controllers;

use App\Http\Requests\RateRequest;
use App\Models\Rating;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;

class RatingController extends Controller
{
    /**
     * Create or update the authenticated user's rating for a site.
     */
    public function store(RateRequest $request, Site $site): RedirectResponse
    {
        $validated = $request->validated();

        Rating::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'site_id' => $site->id,
            ],
            [
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

    /**
     * Remove the authenticated user's rating for a site.
     */
    public function destroy(Site $site): RedirectResponse
    {
        Rating::where('user_id', auth()->id())
            ->where('site_id', $site->id)
            ->delete();

        $site->update([
            'user_rating_avg' => $site->ratings()->avg('score') ?? 0,
            'user_rating_count' => $site->ratings()->count(),
        ]);

        return back();
    }
}
