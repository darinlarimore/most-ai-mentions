<?php

namespace App\Services;

use App\Models\CrawlResult;
use App\Models\ScoreHistory;
use App\Models\Site;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class CrawlService
{
    /**
     * Create a new CrawlService instance.
     *
     * @param  HypeScoreCalculator  $calculator  The scoring algorithm used to compute hype scores after a crawl.
     */
    public function __construct(
        private readonly HypeScoreCalculator $calculator,
    ) {}

    /**
     * Queue a site for crawling by dispatching the crawl job.
     *
     * Updates the site's status to "queued" and dispatches the
     * CrawlSiteJob onto the default queue. If the site is on cooldown,
     * a \RuntimeException is thrown.
     *
     * @param  Site  $site  The site to enqueue for crawling.
     *
     * @throws \RuntimeException If the site is still on cooldown.
     */
    public function queueSite(Site $site): void
    {
        if (! $this->canCrawl($site)) {
            throw new \RuntimeException(
                "Site [{$site->domain}] is on cooldown until ".
                $site->last_crawled_at?->addHours($site->cooldown_hours)?->toDateTimeString()
            );
        }

        $site->update(['status' => 'queued']);

        dispatch(new \App\Jobs\CrawlSiteJob($site));
    }

    /**
     * Get the site that is currently being crawled, if any.
     *
     * @return Site|null The site with status "crawling", or null if none.
     */
    public function getActiveCrawl(): ?Site
    {
        return Site::where('status', 'crawling')->first();
    }

    /**
     * Get all sites that are queued and waiting to be crawled.
     *
     * @return Collection<int, Site>
     */
    public function getQueuedSites(): Collection
    {
        return Site::where('status', 'queued')
            ->orderBy('updated_at', 'asc')
            ->get();
    }

    /**
     * Determine whether a site can be crawled right now.
     *
     * A site can be crawled if it is not currently on cooldown (i.e., it
     * has never been crawled, or enough time has elapsed since the last crawl).
     *
     * @param  Site  $site  The site to check.
     * @return bool True if the site is eligible for crawling.
     */
    public function canCrawl(Site $site): bool
    {
        return ! $site->isOnCooldown();
    }

    /**
     * Mark a site as actively being crawled.
     *
     * Sets the site status to "crawling" so other processes know a crawl
     * is in progress and will not dispatch duplicate jobs.
     *
     * @param  Site  $site  The site to mark.
     */
    public function markCrawling(Site $site): void
    {
        $site->update(['status' => 'crawling']);
    }

    /**
     * Mark a site as having completed its crawl and persist the results.
     *
     * Updates the site's hype score, crawl timestamp, crawl count, and
     * status. Also records a ScoreHistory entry for trend tracking.
     *
     * @param  Site  $site  The site that was crawled.
     * @param  CrawlResult  $result  The crawl result containing scores and metadata.
     */
    public function markCompleted(Site $site, CrawlResult $result): void
    {
        $site->update([
            'status' => 'completed',
            'hype_score' => $result->total_score,
            'last_crawled_at' => Carbon::now(),
            'crawl_count' => $site->crawl_count + 1,
        ]);

        ScoreHistory::forceCreate([
            'site_id' => $site->id,
            'crawl_result_id' => $result->id,
            'hype_score' => $result->total_score,
            'ai_mention_count' => $result->ai_mention_count,
            'lighthouse_performance' => $result->lighthouse_performance,
            'lighthouse_accessibility' => $result->lighthouse_accessibility,
            'recorded_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark a site as having failed its crawl.
     *
     * Sets the site status to "failed" so it can be retried later or
     * investigated by an administrator.
     *
     * @param  Site  $site  The site whose crawl failed.
     * @param  string  $reason  A human-readable error message describing the failure.
     */
    public function markFailed(Site $site, string $reason): void
    {
        $site->update(['status' => 'failed']);

        CrawlResult::create([
            'site_id' => $site->id,
            'status' => 'failed',
            'error_message' => $reason,
        ]);
    }
}
