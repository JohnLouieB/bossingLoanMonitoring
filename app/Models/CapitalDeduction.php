<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapitalDeduction extends Model
{
    protected $fillable = [
        'year',
        'amount',
        'month',
        'description',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'month' => 'integer',
        ];
    }

    /**
     * Get the user (admin) who recorded the deduction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
