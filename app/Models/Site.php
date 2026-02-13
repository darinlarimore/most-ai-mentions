<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Site extends Model
{
    use HasFactory;

    /** Maximum consecutive failures before a site is auto-deactivated. */
    public const MAX_CONSECUTIVE_FAILURES = 6;

    /** @var list<string> */
    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::creating(function (Site $site) {
            if (empty($site->slug) && $site->domain) {
                $site->slug = self::generateSlug($site->domain);
            }
        });
    }

    public static function generateSlug(string $domain): string
    {
        $domain = preg_replace('/^www\./', '', $domain);

        return Str::slug(str_replace('.', '-', $domain));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_crawled_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'is_active' => 'boolean',
            'tech_stack' => 'array',
            'consecutive_failures' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    protected function screenshotPath(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Storage::disk('public')->url($value) : null,
        );
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function crawlResults(): HasMany
    {
        return $this->hasMany(CrawlResult::class);
    }

    public function latestCrawlResult(): HasOne
    {
        return $this->hasOne(CrawlResult::class)->latestOfMany();
    }

    public function scoreHistories(): HasMany
    {
        return $this->hasMany(ScoreHistory::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function crawlErrors(): HasMany
    {
        return $this->hasMany(CrawlError::class);
    }

    /**
     * Scope a query to only include active sites.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope a query to only include sites ready to crawl.
     *
     * Uses exponential backoff based on consecutive_failures:
     * 0 failures = 0hr, 1 = 1hr, 2 = 6hr, 3 = 24hr, 4 = 72hr, 5 = 168hr, 6+ = deactivated.
     */
    public function scopeReadyToCrawl(Builder $query): void
    {
        $driver = $query->getConnection()->getDriverName();

        $cooldownExpr = $driver === 'sqlite'
            ? "last_crawled_at <= datetime('now', '-' || cooldown_hours || ' hours')"
            : 'last_crawled_at <= NOW() - INTERVAL cooldown_hours HOUR';

        $backoffExpr = self::attemptBackoffExpression($driver);

        $attemptExpr = $driver === 'sqlite'
            ? "last_attempted_at <= datetime('now', '-' || ({$backoffExpr}) || ' hours')"
            : "last_attempted_at <= NOW() - INTERVAL ({$backoffExpr}) HOUR";

        $query->where('consecutive_failures', '<', self::MAX_CONSECUTIVE_FAILURES)
            ->where(function (Builder $query) use ($cooldownExpr) {
                $query->whereNull('last_crawled_at')
                    ->orWhereRaw($cooldownExpr);
            })->where(function (Builder $query) use ($attemptExpr) {
                $query->whereNull('last_attempted_at')
                    ->orWhereRaw($attemptExpr);
            });
    }

    /**
     * SQL CASE expression returning backoff hours based on consecutive_failures.
     */
    private static function attemptBackoffExpression(string $driver): string
    {
        return 'CASE consecutive_failures
            WHEN 0 THEN 0
            WHEN 1 THEN 1
            WHEN 2 THEN 6
            WHEN 3 THEN 24
            WHEN 4 THEN 72
            WHEN 5 THEN 168
            ELSE 9999
        END';
    }

    /**
     * Scope to filter and order sites by crawl queue priority.
     *
     * User-submitted never-crawled sites first, then never-crawled, then oldest crawled.
     */
    public function scopeCrawlQueue(Builder $query): void
    {
        $query->active()
            ->readyToCrawl()
            ->where('status', '!=', 'crawling')
            ->orderByRaw("source = 'submitted' AND last_crawled_at IS NULL DESC")
            ->orderByRaw('last_crawled_at IS NULL DESC')
            ->orderBy('last_crawled_at')
            ->orderBy('id');
    }

    /**
     * Scope for sites that have been crawled but are missing category or screenshot.
     *
     * Used to backfill data during queue downtime.
     */
    public function scopeNeedsBackfill(Builder $query): void
    {
        $driver = $query->getConnection()->getDriverName();

        $backoffExpr = self::attemptBackoffExpression($driver);

        $attemptExpr = $driver === 'sqlite'
            ? "last_attempted_at <= datetime('now', '-' || ({$backoffExpr}) || ' hours')"
            : "last_attempted_at <= NOW() - INTERVAL ({$backoffExpr}) HOUR";

        $query->active()
            ->where('consecutive_failures', '<', self::MAX_CONSECUTIVE_FAILURES)
            ->where('status', '!=', 'crawling')
            ->whereNotNull('last_crawled_at')
            ->where(function (Builder $query) {
                $query->where('category', 'other')
                    ->orWhereNull('screenshot_path')
                    ->orWhere(fn (Builder $q) => $q->whereNotNull('server_ip')->whereNull('latitude'))
                    ->orWhereHas('latestCrawlResult', fn (Builder $q) => $q->whereNull('total_word_count'));
            })
            ->where(function (Builder $query) use ($attemptExpr) {
                $query->whereNull('last_attempted_at')
                    ->orWhereRaw($attemptExpr);
            })
            ->orderBy('last_crawled_at')
            ->orderBy('id');
    }

    /**
     * Determine if the site is currently on cooldown.
     */
    public function isOnCooldown(): bool
    {
        if ($this->last_crawled_at === null) {
            return false;
        }

        return $this->last_crawled_at->addHours($this->cooldown_hours)->isFuture();
    }
}
