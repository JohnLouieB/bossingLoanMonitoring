<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CapitalCashFlow extends Model
{
    protected $fillable = [
        'year',
        'capital',
    ];

    protected function casts(): array
    {
        return [
            'capital' => 'decimal:2',
        ];
    }

    /**
     * Get the capital transactions for this year.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(CapitalTransaction::class, 'year', 'year');
    }
}
