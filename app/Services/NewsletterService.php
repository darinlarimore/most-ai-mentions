<?php

namespace App\Services;

use App\Models\NewsletterEdition;
use App\Models\NewsletterSubscriber;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class NewsletterService
{
    /** @var int Number of top-scoring sites to feature in each weekly edition. */
    private const TOP_SITES_LIMIT = 10;

    /**
     * Compile a new weekly newsletter edition from the past week's data.
     *
     * Gathers the top-scoring active sites whose hype scores changed during
     * the past seven days, builds a subject line and structured content
     * payload, and persists a new NewsletterEdition record ready for sending.
     *
     * @return NewsletterEdition The newly created (unsent) newsletter edition.
     */
    public function compileWeeklyEdition(): NewsletterEdition
    {
        $weekEnd = Carbon::now();
        $weekStart = $weekEnd->copy()->subWeek();

        $topSites = Site::query()
            ->active()
            ->where('crawl_count', '>', 0)
            ->orderByDesc('hype_score')
            ->limit(self::TOP_SITES_LIMIT)
            ->get();

        $topSitesData = $topSites->map(fn (Site $site): array => [
            'id' => $site->id,
            'name' => $site->name ?? $site->domain,
            'domain' => $site->domain,
            'url' => $site->url,
            'hype_score' => $site->hype_score,
            'screenshot_path' => $site->screenshot_path,
        ])->toArray();

        $subscriberCount = NewsletterSubscriber::where('is_active', true)->count();

        $subject = sprintf(
            'AI Hype Leaderboard - Week of %s',
            $weekStart->format('M j, Y'),
        );

        return NewsletterEdition::create([
            'subject' => $subject,
            'content' => $this->buildContent($topSitesData, $weekStart, $weekEnd),
            'top_sites' => $topSitesData,
            'subscriber_count' => $subscriberCount,
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
        ]);
    }

    /**
     * Subscribe an email address to the newsletter.
     *
     * If the email already exists and was previously unsubscribed, the
     * subscription is reactivated. Otherwise a new subscriber record is
     * created with a unique unsubscribe token.
     *
     * @param  string  $email  The subscriber's email address.
     * @param  string|null  $name  Optional display name.
     * @param  User|null  $user  Optional associated user account.
     * @return NewsletterSubscriber The created or reactivated subscriber.
     */
    public function subscribe(string $email, ?string $name = null, ?User $user = null): NewsletterSubscriber
    {
        $existing = NewsletterSubscriber::where('email', $email)->first();

        if ($existing) {
            // Reactivate if previously unsubscribed
            if (! $existing->is_active) {
                $existing->update([
                    'is_active' => true,
                    'name' => $name ?? $existing->name,
                    'user_id' => $user?->id ?? $existing->user_id,
                    'unsubscribed_at' => null,
                    'confirmed_at' => Carbon::now(),
                ]);
            }

            return $existing->refresh();
        }

        return NewsletterSubscriber::create([
            'email' => $email,
            'name' => $name,
            'user_id' => $user?->id,
            'token' => Str::random(64),
            'is_active' => true,
            'confirmed_at' => Carbon::now(),
        ]);
    }

    /**
     * Unsubscribe a newsletter subscriber using their unique token.
     *
     * Locates the active subscriber by token, marks them as inactive, and
     * records the unsubscription timestamp. Returns false if the token does
     * not match any active subscriber.
     *
     * @param  string  $token  The unique unsubscribe token.
     * @return bool True if the subscriber was successfully unsubscribed, false otherwise.
     */
    public function unsubscribe(string $token): bool
    {
        $subscriber = NewsletterSubscriber::where('token', $token)
            ->where('is_active', true)
            ->first();

        if (! $subscriber) {
            return false;
        }

        $subscriber->update([
            'is_active' => false,
            'unsubscribed_at' => Carbon::now(),
        ]);

        return true;
    }

    /**
     * Build the newsletter edition content string.
     *
     * Generates a plain-text summary of the weekly top sites suitable
     * for rendering in email templates.
     *
     * @param  array<int, array{id: int, name: string, domain: string, url: string, hype_score: int, screenshot_path: string|null}>  $topSites
     */
    private function buildContent(array $topSites, Carbon $weekStart, Carbon $weekEnd): string
    {
        $lines = [
            sprintf('AI Hype Leaderboard for %s - %s', $weekStart->format('M j'), $weekEnd->format('M j, Y')),
            '',
            sprintf('Top %d Most AI-Hyped Sites This Week:', count($topSites)),
            '',
        ];

        foreach ($topSites as $rank => $site) {
            $lines[] = sprintf(
                '%d. %s (%s) - Hype Score: %d',
                $rank + 1,
                $site['name'],
                $site['domain'],
                $site['hype_score'],
            );
        }

        $lines[] = '';
        $lines[] = 'Visit most-ai-mentions.com for full details, screenshots, and score breakdowns.';

        return implode("\n", $lines);
    }
}
