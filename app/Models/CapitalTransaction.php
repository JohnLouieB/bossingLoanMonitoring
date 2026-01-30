<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapitalTransaction extends Model
{
    protected $fillable = [
        'year',
        'loan_id',
        'type',
        'amount',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Get the loan that this transaction is associated with.
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}
