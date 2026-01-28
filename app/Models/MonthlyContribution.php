<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyContribution extends Model
{
    protected $fillable = [
        'member_id',
        'amount',
        'month',
        'year',
        'status',
        'payment_date',
        'notes',
    ];

    /**
     * Get the member that owns the monthly contribution.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
