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

    /**
     * Scope a query to only include active sites.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope a query to only include sites ready to crawl.
     */
    public function scopeReadyToCrawl(Builder $query): void
    {
        $driver = $query->getConnection()->getDriverName();

        $cooldownExpr = $driver === 'sqlite'
            ? "last_crawled_at <= datetime('now', '-' || cooldown_hours || ' hours')"
            : 'last_crawled_at <= NOW() - INTERVAL cooldown_hours HOUR';

        $attemptExpr = $driver === 'sqlite'
            ? "last_attempted_at <= datetime('now', '-24 hours')"
            : 'last_attempted_at <= NOW() - INTERVAL 24 HOUR';

        $query->where(function (Builder $query) use ($cooldownExpr) {
            $query->whereNull('last_crawled_at')
                ->orWhereRaw($cooldownExpr);
        })->where(function (Builder $query) use ($attemptExpr) {
            $query->whereNull('last_attempted_at')
                ->orWhereRaw($attemptExpr);
        });
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

        $attemptExpr = $driver === 'sqlite'
            ? "last_attempted_at <= datetime('now', '-24 hours')"
            : 'last_attempted_at <= NOW() - INTERVAL 24 HOUR';

        $query->active()
            ->where('status', '!=', 'crawling')
            ->whereNotNull('last_crawled_at')
            ->where(function (Builder $query) {
                $query->where('category', 'other')
                    ->orWhereNull('screenshot_path')
                    ->orWhere(fn (Builder $q) => $q->whereNotNull('server_ip')->whereNull('latitude'));
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
