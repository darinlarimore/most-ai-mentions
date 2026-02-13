<?php

namespace App\Models;

use App\Enums\CrawlErrorCategory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlError extends Model
{
    /** @use HasFactory<\Database\Factories\CrawlErrorFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'site_id',
        'crawl_result_id',
        'category',
        'message',
        'url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => CrawlErrorCategory::class,
        ];
    }

    /** @var list<string> */
    protected $appends = ['category_label'];

    protected function categoryLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->category?->label(),
        );
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
