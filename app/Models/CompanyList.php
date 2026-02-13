<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyList extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CompanyListEntry::class);
    }

    /**
     * Get a query builder for active, crawled sites matching this list's entries.
     */
    public function matchedSites(): Builder
    {
        return Site::query()
            ->active()
            ->whereNotNull('last_crawled_at')
            ->whereIn('domain', $this->entries()->select('domain'));
    }
}
