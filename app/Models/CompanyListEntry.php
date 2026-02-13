<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyListEntry extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    public function companyList(): BelongsTo
    {
        return $this->belongsTo(CompanyList::class);
    }
}
