<?php

namespace App\Http\Controllers;

use App\Models\AdvancePayment;
use App\Models\CapitalCashFlow;
use App\Models\CapitalTransaction;
use App\Models\Loan;
use App\Models\Member;
use App\Models\MonthlyInterestPayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    /**
     * Display a listing of members who have loans.
     */
    public function index(Request $request): Response
    {
        // Get members who have at least one loan, with their loans and advance payments
        $query = Member::whereHas('loans')
            ->with(['loans' => function ($q) {
                $q->with('advancePayments')->orderBy('created_at', 'desc');
            }]);

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Get all members first to calculate balances and filter properly
        $allMembers = $query->orderBy('created_at', 'desc')->get();

        // Transform data to include loan counts, total amounts, and total remaining balance
        $allMembers->transform(function ($member) {
            // Calculate total remaining balance (sum of balance column from all loans)
            $totalRemainingBalance = $member->loans->sum('balance');

            // Set attributes directly on the model
            $member->loans_count = $member->loans->count();
            $member->total_loan_amount = $member->loans->sum('amount');
            $member->total_remaining_balance = $totalRemainingBalance;

            // Make sure these attributes are included in JSON serialization
            $member->makeVisible(['loans_count', 'total_loan_amount', 'total_remaining_balance']);

            // Also set as attributes to ensure they're serialized
            $member->setAttribute('loans_count', $member->loans->count());
            $member->setAttribute('total_loan_amount', $member->loans->sum('amount'));
            $member->setAttribute('total_remaining_balance', $totalRemainingBalance);

            return $member;
        });

        // Filter by balance status before pagination
        $balanceFilter = $request->get('balance_filter', 'all');
        if ($balanceFilter === 'has_balance') {
            $allMembers = $allMembers->filter(function ($member) {
                return $member->total_remaining_balance > 0;
            });
        } elseif ($balanceFilter === 'paid') {
            $allMembers = $allMembers->filter(function ($member) {
                return $member->total_remaining_balance == 0;
            });
        }

        // Manually paginate the filtered results
        $perPage = 10;
        $currentPage = (int) $request->get('page', 1);
        $total = $allMembers->count();
        $items = $allMembers->forPage($currentPage, $perPage)->values();

        // Create paginator manually
        $members = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        // Get all members for the dropdown (for creating new loans)
        $allMembers = Member::orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'email']);

        return Inertia::render('Loans/Index', [
            'members' => $members,
            'allMembers' => $allMembers,
            'filters' => $request->only(['search', 'balance_filter']),
            'monthlyInterestPayments' => [],
            'remainingBalance' => 0,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'borrower_type' => 'required|in:member,non-member',
            'member_id' => 'required_if:borrower_type,member|nullable|exists:members,id',
            'non_member_name' => 'required_if:borrower_type,non-member|nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:pending,approved,rejected,paid',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        // Prepare loan data
        $loanData = [
            'amount' => $validated['amount'],
            'balance' => $validated['amount'], // Initially balance equals amount
            'interest_rate' => $validated['interest_rate'] ?? 0,
            'status' => $validated['status'] ?? 'pending',
            'description' => $validated['description'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'year' => $validated['year'],
        ];

        // Set member_id or non_member_name based on borrower type
        if ($validated['borrower_type'] === 'member') {
            $loanData['member_id'] = $validated['member_id'];
            $loanData['non_member_name'] = null;
        } else {
            $loanData['member_id'] = null;
            $loanData['non_member_name'] = $validated['non_member_name'];
        }

        $loan = Loan::create($loanData);

        // Deduct loan amount from capital for the selected year
        $year = $validated['year'];
        $loanAmount = $validated['amount'];

        // Get or create capital entry for the year
        $capitalEntry = CapitalCashFlow::firstOrCreate(
            ['year' => $year],
            ['capital' => 0]
        );

        // Deduct the loan amount from capital
        $capitalEntry->capital = max(0, $capitalEntry->capital - $loanAmount);
        $capitalEntry->save();

        // Record the transaction
        $borrowerName = $validated['borrower_type'] === 'member' 
            ? (function() use ($validated) {
                $member = Member::find($validated['member_id']);
                return $member ? $member->first_name . ' ' . $member->last_name : 'Unknown Member';
            })()
            : $validated['non_member_name'];

        CapitalTransaction::create([
            'year' => $year,
            'loan_id' => $loan->id,
            'type' => 'deduction',
            'amount' => $loanAmount,
            'description' => 'Loan disbursement to ' . $borrowerName,
        ]);

        return redirect()->route('loans.index')
            ->with('success', 'Loan created successfully and capital deducted.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Loan $loan)
    {
        $loan->load(['member', 'monthlyInterestPayments', 'advancePayments']);

        // Get current year
        $currentYear = date('Y');

        // Initialize monthly interest payments for current year if they don't exist
        for ($month = 1; $month <= 12; $month++) {
            $existingPayment = MonthlyInterestPayment::where('loan_id', $loan->id)
                ->where('month', $month)
                ->where('year', $currentYear)
                ->first();

            if (! $existingPayment) {
                // January is always 0 peso (payments start in February)
                $interestAmount = $month === 1 ? 0 : (($loan->remaining_balance * $loan->interest_rate) / 100);

                MonthlyInterestPayment::create([
                    'loan_id' => $loan->id,
                    'month' => $month,
                    'year' => $currentYear,
                    'interest_amount' => $interestAmount,
                    'status' => 'pending',
                ]);
            } else {
                // Ensure January is always 0
                if ($month === 1 && $existingPayment->interest_amount != 0) {
                    $existingPayment->update(['interest_amount' => 0]);
                }
            }
        }

        // Reload to get the newly created payments
        $loan->refresh();
        $loan->load(['monthlyInterestPayments', 'advancePayments']);

        // Calculate and set balance
        $totalAdvancePayments = $loan->advancePayments->sum('amount');
        $balance = max(0, $loan->amount - $totalAdvancePayments);
        $loan->setAttribute('balance', $balance);
        $loan->makeVisible(['balance']);

        $monthlyInterestPayments = $loan->monthlyInterestPayments()->where('year', $currentYear)->get()->toArray();

        // Return JSON for AJAX requests (used in modal via fetch)
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'loan' => $loan,
                'monthlyInterestPayments' => $monthlyInterestPayments,
                'advancePayments' => $loan->advancePayments,
                'remainingBalance' => $loan->balance,
            ]);
        }

        // Return Inertia response (for router.get calls with 'only' option)
        // Since we're using 'only', we need to return to the same page component
        // Get the current index data to return
        $query = Member::whereHas('loans')
            ->with(['loans' => function ($q) {
                $q->orderBy('created_at', 'desc');
            }]);

        // Preserve search filter if exists
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $members = $query->with(['loans' => function ($q) {
            $q->with('advancePayments')->orderBy('created_at', 'desc');
        }])->orderBy('created_at', 'desc')->paginate(10);

        $members->getCollection()->transform(function ($member) {
            // Calculate balance for each loan
            $member->loans->transform(function ($loan) {
                $totalAdvancePayments = $loan->advancePayments->sum('amount');
                $balance = max(0, $loan->amount - $totalAdvancePayments);
                $loan->setAttribute('balance', $balance);
                $loan->makeVisible(['balance']);

                return $loan;
            });

            $totalRemainingBalance = $member->loans->sum(function ($loan) {
                return $loan->balance ?? 0;
            });

            $member->loans_count = $member->loans->count();
            $member->total_loan_amount = $member->loans->sum('amount');
            $member->total_remaining_balance = $totalRemainingBalance;
            $member->makeVisible(['loans_count', 'total_loan_amount', 'total_remaining_balance']);

            return $member;
        });

        $allMembers = Member::orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'email']);

        return Inertia::render('Loans/Index', [
            'members' => $members,
            'allMembers' => $allMembers,
            'filters' => $request->only(['search']),
            'monthlyInterestPayments' => $monthlyInterestPayments,
            'remainingBalance' => $loan->remaining_balance,
        ]);
    }

    /**
     * Update monthly interest payment status.
     */
    public function updateMonthlyInterest(Request $request, Loan $loan)
    {
        $validated = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer',
            'status' => 'required|in:pending,paid',
            'payment_date' => 'nullable|date',
        ]);

        $monthlyInterest = MonthlyInterestPayment::where('loan_id', $loan->id)
            ->where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->firstOrFail();

        $oldStatus = $monthlyInterest->status;
        $newStatus = $validated['status'];
        $interestAmount = $monthlyInterest->interest_amount;
        $loanYear = $loan->year;

        // Handle capital adjustment based on status change
        if ($loanYear) {
            if ($oldStatus === 'pending' && $newStatus === 'paid') {
                // Add interest to capital when marked as paid
                $capitalEntry = CapitalCashFlow::firstOrCreate(
                    ['year' => $loanYear],
                    ['capital' => 0]
                );
                $capitalEntry->capital += $interestAmount;
                $capitalEntry->save();

                // Create capital transaction for interest payment
                $loan->load('member');
                $borrowerName = $loan->member_id 
                    ? ($loan->member ? $loan->member->first_name . ' ' . $loan->member->last_name : 'Unknown Member')
                    : ($loan->non_member_name ?? 'Unknown');

                $monthName = date('F', mktime(0, 0, 0, $validated['month'], 1));

                CapitalTransaction::create([
                    'year' => $loanYear,
                    'loan_id' => $loan->id,
                    'type' => 'addition',
                    'amount' => $interestAmount,
                    'description' => 'Interest payment from ' . $borrowerName . ' - ' . $monthName . ' ' . $validated['year'],
                ]);
            } elseif ($oldStatus === 'paid' && $newStatus === 'pending') {
                // Deduct interest from capital when marked as pending (revert)
                $capitalEntry = CapitalCashFlow::where('year', $loanYear)->first();
                if ($capitalEntry) {
                    $capitalEntry->capital = max(0, $capitalEntry->capital - $interestAmount);
                    $capitalEntry->save();
                }

                // Delete the capital transaction for this interest payment
                $monthName = date('F', mktime(0, 0, 0, $validated['month'], 1));
                CapitalTransaction::where('loan_id', $loan->id)
                    ->where('year', $loanYear)
                    ->where('type', 'addition')
                    ->where('description', 'like', '%Interest payment%' . $monthName . '%')
                    ->where('amount', $interestAmount)
                    ->orderBy('created_at', 'desc')
                    ->first()
                    ?->delete();
            }
        }

        $monthlyInterest->update([
            'status' => $validated['status'],
            'payment_date' => $validated['payment_date'] ?? ($validated['status'] === 'paid' ? now() : null),
        ]);

        // Reload loan with updated monthly interest payments
        $loan->refresh();
        $loan->load(['monthlyInterestPayments', 'advancePayments']);
        $currentYear = date('Y');

        // Return Inertia response with updated data
        $monthlyInterestPayments = $loan->monthlyInterestPayments()->where('year', $currentYear)->get()->toArray();

        return back()->with([
            'success' => 'Monthly interest payment updated successfully.',
            'monthlyInterestPayments' => $monthlyInterestPayments,
            'remainingBalance' => $loan->remaining_balance,
        ]);
    }

    /**
     * Store advance payment.
     */
    public function storeAdvancePayment(Request $request, Loan $loan)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        AdvancePayment::create([
            'loan_id' => $loan->id,
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'notes' => $validated['notes'] ?? null,
        ]);

        // Update the loan balance (balance = balance - advance payment amount)
        $loan->balance = max(0, $loan->balance - $validated['amount']);
        $loan->save();

        // Recalculate monthly interest for remaining months
        $currentYear = date('Y');
        $currentMonth = date('n');
        $remainingBalance = $loan->fresh()->remaining_balance;

        for ($month = $currentMonth; $month <= 12; $month++) {
            $monthlyInterest = MonthlyInterestPayment::where('loan_id', $loan->id)
                ->where('month', $month)
                ->where('year', $currentYear)
                ->first();

            if ($monthlyInterest && $monthlyInterest->status === 'pending') {
                // January is always 0 peso (payments start in February)
                $interestAmount = $month === 1 ? 0 : (($remainingBalance * $loan->interest_rate) / 100);
                $monthlyInterest->update(['interest_amount' => $interestAmount]);
            }
        }

        // Reload loan with updated data
        $loan->refresh();
        $loan->load(['monthlyInterestPayments', 'advancePayments']);
        $monthlyInterestPayments = $loan->monthlyInterestPayments()->where('year', $currentYear)->get()->toArray();

        // Return back with updated data - this prevents Inertia from trying to GET the advance-payment route
        return back()->with([
            'success' => 'Advance payment recorded successfully.',
            'monthlyInterestPayments' => $monthlyInterestPayments,
            'remainingBalance' => $loan->balance,
        ]);
    }

    /**
     * Revert (delete) an advance payment.
     */
    public function revertAdvancePayment(Request $request, Loan $loan, AdvancePayment $advancePayment)
    {
        // Verify the advance payment belongs to the loan
        if ($advancePayment->loan_id !== $loan->id) {
            abort(404);
        }

        $amount = $advancePayment->amount;

        // Delete the advance payment
        $advancePayment->delete();

        // Update the loan balance (balance = balance + advance payment amount)
        $loan->balance = min($loan->amount, $loan->balance + $amount);
        $loan->save();

        // Recalculate monthly interest for remaining months
        $currentYear = date('Y');
        $currentMonth = date('n');
        $remainingBalance = $loan->fresh()->remaining_balance;

        for ($month = $currentMonth; $month <= 12; $month++) {
            $monthlyInterest = MonthlyInterestPayment::where('loan_id', $loan->id)
                ->where('month', $month)
                ->where('year', $currentYear)
                ->first();

            if ($monthlyInterest && $monthlyInterest->status === 'pending') {
                // January is always 0 peso (payments start in February)
                $interestAmount = $month === 1 ? 0 : (($remainingBalance * $loan->interest_rate) / 100);
                $monthlyInterest->update(['interest_amount' => $interestAmount]);
            }
        }

        // Reload loan with updated data
        $loan->refresh();
        $loan->load(['monthlyInterestPayments', 'advancePayments']);
        $monthlyInterestPayments = $loan->monthlyInterestPayments()->where('year', $currentYear)->get()->toArray();

        return back()->with([
            'success' => 'Advance payment reverted successfully.',
            'monthlyInterestPayments' => $monthlyInterestPayments,
            'remainingBalance' => $loan->balance,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Loan $loan)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Loan $loan)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected,paid',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        // Load member relationship if needed
        $loan->load('member');

        $oldYear = $loan->year;
        $newYear = $validated['year'] ?? $oldYear;
        $loanAmount = $loan->amount;

        // Handle year change and capital adjustments
        if ($oldYear && $oldYear != $newYear) {
            // Restore capital to old year
            $oldCapitalEntry = CapitalCashFlow::where('year', $oldYear)->first();
            if ($oldCapitalEntry) {
                $oldCapitalEntry->capital += $loanAmount;
                $oldCapitalEntry->save();
            }

            // Delete old capital transaction
            CapitalTransaction::where('loan_id', $loan->id)
                ->where('year', $oldYear)
                ->delete();

            // Deduct capital from new year
            $newCapitalEntry = CapitalCashFlow::firstOrCreate(
                ['year' => $newYear],
                ['capital' => 0]
            );
            $newCapitalEntry->capital = max(0, $newCapitalEntry->capital - $loanAmount);
            $newCapitalEntry->save();

            // Create new capital transaction
            $borrowerName = $loan->member_id 
                ? ($loan->member ? $loan->member->first_name . ' ' . $loan->member->last_name : 'Unknown Member')
                : ($loan->non_member_name ?? 'Unknown');

            CapitalTransaction::create([
                'year' => $newYear,
                'loan_id' => $loan->id,
                'type' => 'deduction',
                'amount' => $loanAmount,
                'description' => 'Loan disbursement to ' . $borrowerName . ' (Year updated)',
            ]);
        } elseif (!$oldYear && $newYear) {
            // Loan didn't have a year before, now it does - deduct from capital
            $capitalEntry = CapitalCashFlow::firstOrCreate(
                ['year' => $newYear],
                ['capital' => 0]
            );
            $capitalEntry->capital = max(0, $capitalEntry->capital - $loanAmount);
            $capitalEntry->save();

            // Create capital transaction
            $borrowerName = $loan->member_id 
                ? ($loan->member ? $loan->member->first_name . ' ' . $loan->member->last_name : 'Unknown Member')
                : ($loan->non_member_name ?? 'Unknown');

            CapitalTransaction::create([
                'year' => $newYear,
                'loan_id' => $loan->id,
                'type' => 'deduction',
                'amount' => $loanAmount,
                'description' => 'Loan disbursement to ' . $borrowerName . ' (Year added)',
            ]);
        }

        // Update loan
        $updateData = [
            'status' => $validated['status'],
        ];

        if (isset($validated['year'])) {
            $updateData['year'] = $validated['year'];
        }

        $loan->update($updateData);

        return redirect()->route('loans.index')
            ->with('success', 'Loan updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Loan $loan)
    {
        // Get loan details before deletion
        $loanAmount = $loan->amount;
        $loanYear = $loan->year;

        // Restore capital if year exists
        if ($loanYear) {
            $capitalEntry = CapitalCashFlow::where('year', $loanYear)->first();
            if ($capitalEntry) {
                // Add back the loan amount to capital
                $capitalEntry->capital += $loanAmount;
                $capitalEntry->save();
            }

            // Delete the associated capital transaction
            CapitalTransaction::where('loan_id', $loan->id)
                ->where('year', $loanYear)
                ->delete();
        }

        // Delete the loan (this will cascade delete advance payments and monthly interest payments)
        $loan->delete();

        return redirect()->route('loans.index')
            ->with('success', 'Loan deleted successfully and capital restored.');
    }
}
