<?php

namespace App\Observers;

use App\Events\ScoreUpdated;
use App\Models\ScoreHistory;
use App\Models\Site;

class SiteObserver
{
    public function updated(Site $site): void
    {
        if (! $site->wasChanged('hype_score')) {
            return;
        }

        $previousScore = (float) $site->getOriginal('hype_score');
        $newScore = (float) $site->hype_score;

        ScoreHistory::forceCreate([
            'site_id' => $site->id,
            'hype_score' => (int) $newScore,
            'recorded_at' => now(),
            'crawl_result_id' => $site->crawlResults()->latest()->value('id'),
        ]);

        ScoreUpdated::dispatch($site->id, $newScore, $previousScore);
    }
}
