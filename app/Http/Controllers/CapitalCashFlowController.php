<?php

namespace App\Http\Controllers;

use App\Models\CapitalCashFlow;
use App\Models\CapitalTransaction;
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
        
        // Get or create capital entry for the selected year
        $capitalEntry = CapitalCashFlow::firstOrCreate(
            ['year' => $currentYear],
            ['capital' => 0]
        );

        // Get transactions for the selected year (only loan disbursements, exclude interest additions)
        $transactions = CapitalTransaction::where('year', $currentYear)
            ->where('type', 'deduction') // Only show loan disbursements (deductions)
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

        return Inertia::render('CapitalCashFlow/Index', [
            'capital' => $capitalEntry->capital,
            'currentYear' => (int) $currentYear,
            'filters' => $request->only(['year']),
            'transactions' => $transactions,
            'interestPayments' => $interestPayments,
        ]);
    }

    /**
     * Update the capital for the selected year.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'capital' => 'required|numeric|min:0',
        ]);

        $capitalEntry = CapitalCashFlow::updateOrCreate(
            ['year' => $validated['year']],
            ['capital' => $validated['capital']]
        );

        return back()->with('success', 'Capital updated successfully.');
    }
}
