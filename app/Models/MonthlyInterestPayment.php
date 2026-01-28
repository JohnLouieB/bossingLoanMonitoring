<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyInterestPayment extends Model
{
    protected $fillable = [
        'loan_id',
        'month',
        'year',
        'interest_amount',
        'status',
        'payment_date',
        'notes',
    ];

    /**
     * Get the loan that owns the monthly interest payment.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
