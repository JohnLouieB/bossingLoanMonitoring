<?php

namespace App\Http\Controllers;

use App\Models\CashFlow;
use App\Models\CapitalTransaction;
use App\Models\Loan;
use App\Models\MonthlyContribution;
use App\Models\MonthlyInterestPayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CapitalCashFlowController extends Controller
{
    /**
     * Display the capital and cash flow page.
     */
    public function index(Request $request): Response
    {
        $currentYear = $request->get('year', date('Y'));
        
        // Get or create cash flow entry for the selected year
        $cashFlow = CashFlow::getOrCreate($currentYear);

        // Get values directly from cash_flows table
        $totalInterestCollected = $cashFlow->interest_collected;
        $totalContributionsCollected = $cashFlow->monthly_contributions_collected;
        $moneyReleased = $cashFlow->money_released;
        $initialCapital = $cashFlow->capital;

        // Calculate total advance payments collected for this year (still from transactions)
        $totalAdvancePayments = CapitalTransaction::where('year', $currentYear)
            ->where('type', 'addition')
            ->where('description', 'like', '%Advance payment%')
            ->sum('amount');

        // Calculate total money collected (interest + contributions + advance payments)
        $totalMoneyCollected = $totalInterestCollected + $totalContributionsCollected + $totalAdvancePayments;
        
        // Base capital = initial capital (manually set) + total money collected
        $baseCapital = $initialCapital + $totalMoneyCollected;

        // Calculate total remaining balance of all loans for this year
        $totalLoanBalances = Loan::where('year', $currentYear)
            ->get()
            ->sum(function ($loan) {
                // Calculate remaining balance: loan amount - total advance payments
                $totalAdvancePayments = $loan->advancePayments()->sum('amount');
                return max(0, $loan->amount - $totalAdvancePayments);
            });

        // Calculate available capital: (interest + contributions + advance payments) - total loan balances
        $availableCapital = max(0, ($totalInterestCollected + $totalContributionsCollected + $totalAdvancePayments) - $totalLoanBalances);

        // Get transactions for the selected year
        // Include loan disbursements (deductions) and advance payments (additions)
        $transactions = CapitalTransaction::where('year', $currentYear)
            ->with('loan')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get all paid interest payments where the loan's year matches the selected year
        // Filter by loan's year, not the interest payment's year
        $interestPayments = MonthlyInterestPayment::where('status', 'paid')
            ->whereHas('loan', function ($query) use ($currentYear) {
                $query->where('year', $currentYear);
            })
            ->with(['loan.member'])
            ->orderBy('created_at', 'desc') // Sort by when it was marked as paid (latest first)
            ->orderBy('payment_date', 'desc')
            ->get()
            ->map(function ($payment) {
                $loan = $payment->loan;
                
                // Skip if loan doesn't have a year set
                if (!$loan->year) {
                    return null;
                }
                
                $borrowerName = $loan->member_id 
                    ? ($loan->member ? $loan->member->first_name . ' ' . $loan->member->last_name : 'Unknown Member')
                    : ($loan->non_member_name ?? 'Unknown');
                
                return [
                    'id' => $payment->id,
                    'borrower_name' => $borrowerName,
                    'interest_amount' => $payment->interest_amount,
                    'month' => $payment->month,
                    'year' => $payment->year,
                    'payment_date' => $payment->payment_date,
                    'created_at' => $payment->created_at,
                    'loan_id' => $loan->id,
                    'loan_year' => $loan->year,
                ];
            })
            ->filter() // Remove null entries (loans without year)
            ->values(); // Re-index array

        // Get all paid monthly contributions for the selected year
        $contributions = MonthlyContribution::where('year', $currentYear)
            ->where('status', 'paid')
            ->with('member')
            ->orderBy('created_at', 'desc') // Sort by when it was marked as paid (latest first)
            ->orderBy('payment_date', 'desc')
            ->get()
            ->map(function ($contribution) {
                $member = $contribution->member;
                $memberName = $member 
                    ? $member->first_name . ' ' . $member->last_name
                    : 'Unknown Member';
                
                return [
                    'id' => $contribution->id,
                    'member_name' => $memberName,
                    'amount' => $contribution->amount,
                    'month' => $contribution->month,
                    'year' => $contribution->year,
                    'payment_date' => $contribution->payment_date,
                    'created_at' => $contribution->created_at,
                    'member_id' => $contribution->member_id,
                ];
            });

        return Inertia::render('CapitalCashFlow/Index', [
            'initialCapital' => $initialCapital, // Initial/manual capital amount
            'baseCapital' => $baseCapital, // Base capital = initial + total money collected
            'availableCapital' => $availableCapital, // Available capital = (interest + contributions + advance payments) - total loan balances
            'totalLoanBalances' => $totalLoanBalances, // Sum of all remaining loan balances
            'totalInterestCollected' => $totalInterestCollected, // Total interest collected for this year
            'totalContributionsCollected' => $totalContributionsCollected, // Total contributions collected for this year
            'totalAdvancePayments' => $totalAdvancePayments, // Total advance payments collected for this year
            'moneyReleased' => $moneyReleased, // Total money released (loans) for this year
            'currentYear' => (int) $currentYear,
            'filters' => $request->only(['year']),
            'transactions' => $transactions,
            'interestPayments' => $interestPayments,
            'contributions' => $contributions,
        ]);
    }

    /**
     * Update the capital for the selected year.
     * This updates the initial/base capital amount.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'capital' => 'required|numeric|min:0',
        ]);

        $cashFlow = CashFlow::getOrCreate($validated['year']);
        $cashFlow->capital = $validated['capital'];
        $cashFlow->save();

        return back()->with('success', 'Capital updated successfully.');
    }
}
