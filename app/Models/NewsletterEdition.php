<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsletterEdition extends Model
{
    use HasFactory;

    /** @var array<int, string> */
    protected $guarded = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'top_sites' => 'array',
            'sent_at' => 'datetime',
            'week_start' => 'datetime',
            'week_end' => 'datetime',
        ];
    }

    /**
     * Scope a query to only include sent editions.
     */
    public function scopeSent(Builder $query): void
    {
        $query->whereNotNull('sent_at');
    }
}
