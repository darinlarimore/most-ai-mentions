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

class Site extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $guarded = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_crawled_at' => 'datetime',
            'is_active' => 'boolean',
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
        $query->where(function (Builder $query) {
            $query->whereNull('last_crawled_at')
                ->orWhereColumn('last_crawled_at', '<=', \Illuminate\Support\Facades\DB::raw('NOW() - INTERVAL cooldown_hours HOUR'));
        });
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
