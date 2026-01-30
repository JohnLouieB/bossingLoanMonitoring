<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $fillable = [
        'member_id',
        'non_member_name',
        'amount',
        'balance',
        'interest_rate',
        'status',
        'description',
        'notes',
        'year',
    ];

    /**
     * Get the member that owns the loan.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the monthly interest payments for the loan.
     */
    public function monthlyInterestPayments(): HasMany
    {
        return $this->hasMany(MonthlyInterestPayment::class);
    }

    /**
     * Get the advance payments for the loan.
     */
    public function advancePayments(): HasMany
    {
        return $this->hasMany(AdvancePayment::class);
    }

    /**
     * Calculate the remaining balance (loan amount - total advance payments).
     */
    public function getRemainingBalanceAttribute(): float
    {
        $totalAdvancePayments = $this->advancePayments()->sum('amount');
        return max(0, $this->amount - $totalAdvancePayments);
    }
}
