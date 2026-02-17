<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashFlow extends Model
{
    protected $fillable = [
        'year',
        'capital',
        'interest_collected',
        'interest_collected_base',
        'monthly_contributions_collected',
        'money_released',
    ];

    protected function casts(): array
    {
        return [
            'capital' => 'decimal:2',
            'interest_collected' => 'decimal:2',
            'interest_collected_base' => 'decimal:2',
            'monthly_contributions_collected' => 'decimal:2',
            'money_released' => 'decimal:2',
        ];
    }

    /**
     * Get or create cash flow entry for a year.
     */
    public static function getOrCreate(int $year): self
    {
        return static::firstOrCreate(
            ['year' => $year],
            [
                'capital' => 0,
                'interest_collected' => 0,
                'interest_collected_base' => 0,
                'monthly_contributions_collected' => 0,
                'money_released' => 0,
            ]
        );
    }

    /**
     * Recalculate money_released for a year.
     */
    public static function recalculateMoneyReleased(int $year): void
    {
        $moneyReleased = Loan::where('year', $year)->sum('amount') ?? 0;

        $cashFlow = static::getOrCreate($year);
        $cashFlow->money_released = $moneyReleased;
        $cashFlow->save();
    }

    /**
     * Recalculate interest_collected for a year.
     * This adds the manually set base amount to the sum of ALL paid interest payments for loans in the specified year.
     * Formula: interest_collected = interest_collected_base + sum_of_paid_payments
     */
    public static function recalculateInterestCollected(int $year): void
    {
        // Use a join query to ensure we get the correct results even if relationships are cached
        // Filter by both loan's year and payment's year to ensure accuracy
        $paidPaymentsTotal = MonthlyInterestPayment::join('loans', 'monthly_interest_payments.loan_id', '=', 'loans.id')
            ->where('monthly_interest_payments.status', 'paid')
            ->where('loans.year', $year)
            ->where('monthly_interest_payments.year', $year) // Also filter by payment's year for accuracy
            ->sum('monthly_interest_payments.interest_amount') ?? 0;

        $cashFlow = static::getOrCreate($year);

        // Get the base amount (manually set via command), default to 0 if not set
        $baseAmount = $cashFlow->interest_collected_base ?? 0;

        // Total = base amount + paid payments
        $cashFlow->interest_collected = $baseAmount + $paidPaymentsTotal;
        $cashFlow->save();
    }

    /**
     * Recalculate monthly_contributions_collected for a year.
     */
    public static function recalculateContributionsCollected(int $year): void
    {
        $contributionsCollected = MonthlyContribution::where('year', $year)
            ->where('status', 'paid')
            ->sum('amount') ?? 0;

        $cashFlow = static::getOrCreate($year);
        $cashFlow->monthly_contributions_collected = $contributionsCollected;
        $cashFlow->save();
    }

    /**
     * Update all calculated fields for a year.
     */
    public static function updateCalculatedFields(int $year): void
    {
        static::recalculateMoneyReleased($year);
        static::recalculateInterestCollected($year);
        static::recalculateContributionsCollected($year);
    }

    /**
     * Calculate available capital for a year.
     * Available capital = (interest + contributions) - total loan balances - total deductions.
     * Must match Capital Cash Flow page and Dashboard "Available Balance by Year".
     */
    public static function calculateAvailableCapital(int $year): float
    {
        $cashFlow = static::getOrCreate($year);

        $totalInterestCollected = $cashFlow->interest_collected;
        $totalContributionsCollected = $cashFlow->monthly_contributions_collected;

        // Calculate total remaining balance of all loans for this year
        $totalLoanBalances = \App\Models\Loan::where('year', $year)
            ->get()
            ->sum(function ($loan) {
                $totalAdvancePayments = $loan->advancePayments()->sum('amount');

                return max(0, $loan->amount - $totalAdvancePayments);
            });

        $totalDeductions = \App\Models\CapitalDeduction::where('year', $year)->sum('amount');

        // Available capital = (interest + contributions) - total loan balances - total deductions
        return max(0, ($totalInterestCollected + $totalContributionsCollected) - $totalLoanBalances - $totalDeductions);
    }
}
