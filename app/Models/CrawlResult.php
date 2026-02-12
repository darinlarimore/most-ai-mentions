<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrawlResult extends Model
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
            'mention_details' => 'array',
            'computed_styles' => 'array',
            'redirect_chain' => 'array',
            'detected_tech_stack' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function crawlErrors(): HasMany
    {
        return $this->hasMany(CrawlError::class);
    }
}
