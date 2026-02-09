<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
            'ai_image_details' => 'array',
        ];
    }

    protected function annotatedScreenshotPath(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Storage::disk('public')->url($value) : null,
        );
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
