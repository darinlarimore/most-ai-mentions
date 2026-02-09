<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'stripe_payment_id',
        'amount',
        'currency',
        'status',
        'user_id',
        'donor_name',
        'donor_email',
        'message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Format the amount from cents to a dollar string.
     */
    public function formattedAmount(): string
    {
        return '$'.number_format($this->amount / 100, 2);
    }
}
