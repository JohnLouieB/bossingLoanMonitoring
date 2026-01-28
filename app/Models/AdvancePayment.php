<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvancePayment extends Model
{
    protected $fillable = [
        'loan_id',
        'amount',
        'payment_date',
        'notes',
    ];

    /**
     * Get the loan that owns the advance payment.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
