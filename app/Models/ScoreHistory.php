<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreHistory extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'site_id',
        'crawl_result_id',
        'hype_score',
        'ai_mention_count',
        'lighthouse_performance',
        'lighthouse_accessibility',
        'recorded_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function crawlResult(): BelongsTo
    {
        return $this->belongsTo(CrawlResult::class);
    }
}
