<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'is_active',
    ];

    /**
     * Get the loans for the member.
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * Get the monthly contributions for the member.
     */
    public function monthlyContributions(): HasMany
    {
        return $this->hasMany(MonthlyContribution::class);
    }
}
