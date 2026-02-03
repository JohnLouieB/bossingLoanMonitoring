<?php

namespace App\Http\Controllers;

use App\Models\AdvancePayment;
use App\Models\CashFlow;
use App\Models\CapitalTransaction;
use App\Models\Loan;
use App\Models\Member;
use App\Models\MonthlyInterestPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        // Prepare data for validation - convert empty strings to null
        $data = $request->all();
        if (isset($data['non_member_name']) && $data['non_member_name'] === '') {
            $data['non_member_name'] = null;
        }
        if (isset($data['member_id']) && $data['member_id'] === '') {
            $data['member_id'] = null;
        }
        
        $validated = validator($data, [
            'borrower_type' => 'required|in:member,non-member',
            'member_id' => 'required|exists:members,id', // Required for both member and non-member (as co-maker)
            'non_member_name' => 'required_if:borrower_type,non-member|nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:pending,approved,rejected,paid',
            'description' => 'nullable|string',
            'year' => 'required|integer|min:2000|max:2100',
        ])->validate();

        // Prepare loan data
        $loanData = [
            'amount' => $validated['amount'],
            'balance' => $validated['amount'], // Initially balance equals amount
            'interest_rate' => $validated['interest_rate'] ?? 0,
            'status' => $validated['status'] ?? 'pending',
            'description' => $validated['description'] ?? null,
            'year' => $validated['year'],
        ];

        // Set member_id and non_member_name based on borrower type
        // For non-member loans, member_id is the co-maker
        if ($validated['borrower_type'] === 'member') {
            if (empty($validated['member_id'])) {
                return back()->withErrors(['member_id' => 'Please select a member'])->withInput();
            }
            $loanData['member_id'] = $validated['member_id'];
            $loanData['non_member_name'] = null;
        } else {
            // Non-member loan: requires both non_member_name and member_id (as co-maker)
            if (empty($validated['non_member_name'])) {
                return back()->withErrors(['non_member_name' => 'Please enter a non-member name'])->withInput();
            }
            if (empty($validated['member_id'])) {
                return back()->withErrors(['member_id' => 'Please select a member as co-maker'])->withInput();
            }
            $loanData['member_id'] = $validated['member_id']; // Co-maker
            $loanData['non_member_name'] = trim($validated['non_member_name']);
        }

        // Check available capital before creating the loan
        $year = $validated['year'];
        $loanAmount = $validated['amount'];
        $availableCapital = CashFlow::calculateAvailableCapital($year);
        
        if ($loanAmount > $availableCapital) {
            return back()
                ->withErrors([
                    'amount' => 'Loan amount exceeds available capital',
                    'available_capital' => (string) $availableCapital, // Pass as string in errors
                ])
                ->withInput();
        }

        try {
            $loan = Loan::create($loanData);

            // Update cash flow for the selected year

            // Get or create cash flow entry for the year
            $cashFlow = CashFlow::getOrCreate($year);

            // Update money_released for the year (includes the newly created loan)
            // Recalculate by summing all loans for this year
            $moneyReleased = Loan::where('year', $year)->sum('amount') ?? 0;
            $cashFlow->money_released = $moneyReleased;

            // Deduct the loan amount from capital (for backward compatibility with existing logic)
            $cashFlow->capital = max(0, $cashFlow->capital - $loanAmount);
            $cashFlow->save();
        } catch (\Exception $e) {
            Log::error('Error creating loan: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $loanData ?? null,
            ]);
            
            return back()->withErrors([
                'error' => 'Failed to create loan: ' . $e->getMessage()
            ])->withInput();
        }

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

        // Use loan's year if set, otherwise use current year
        $targetYear = $loan->year ?? date('Y');

        // Initialize monthly interest payments for the loan's year if they don't exist
        for ($month = 1; $month <= 12; $month++) {
            $existingPayment = MonthlyInterestPayment::where('loan_id', $loan->id)
                ->where('month', $month)
                ->where('year', $targetYear)
                ->first();

            if (! $existingPayment) {
                // January is always 0 peso (payments start in February)
                $interestAmount = $month === 1 ? 0 : (($loan->remaining_balance * $loan->interest_rate) / 100);

                MonthlyInterestPayment::create([
                    'loan_id' => $loan->id,
                    'month' => $month,
                    'year' => $targetYear,
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
        $loan->makeVisible(['balance', 'description']);

        $monthlyInterestPayments = $loan->monthlyInterestPayments()->where('year', $targetYear)->get()->toArray();

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
                $loan->makeVisible(['balance', 'description']);

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
                $cashFlow = CashFlow::getOrCreate($loanYear);
                $cashFlow->capital += $interestAmount;
                $cashFlow->save();

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
                $cashFlow = CashFlow::where('year', $loanYear)->first();
                if ($cashFlow) {
                    $cashFlow->capital = max(0, $cashFlow->capital - $interestAmount);
                    $cashFlow->save();
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

        // Update status and payment date first
        $monthlyInterest->update([
            'status' => $validated['status'],
            'payment_date' => $validated['payment_date'] ?? ($validated['status'] === 'paid' ? now() : null),
        ]);

        // Refresh the model to ensure we have the latest data from database
        $monthlyInterest->refresh();

        // Update interest_collected for the loan's year after status update
        // This recalculates the TOTAL of ALL paid interest payments for loans in this year
        // It sums all paid payments, not just adds/subtracts incrementally
        if ($loanYear) {
            CashFlow::recalculateInterestCollected($loanYear);
        }

        // Reload loan with updated monthly interest payments
        $loan->refresh();
        $loan->load(['monthlyInterestPayments', 'advancePayments']);
        // Use loan's year if set, otherwise use current year
        $targetYear = $loan->year ?? date('Y');

        // Return Inertia response with updated data
        $monthlyInterestPayments = $loan->monthlyInterestPayments()->where('year', $targetYear)->get()->toArray();

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

        $advancePayment = AdvancePayment::create([
            'loan_id' => $loan->id,
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'notes' => $validated['notes'] ?? null,
        ]);

        // Update the loan balance (balance = balance - advance payment amount)
        $loan->balance = max(0, $loan->balance - $validated['amount']);
        $loan->save();

        // Add advance payment to capital for the loan's year
        $loanYear = $loan->year;
        if ($loanYear) {
            $cashFlow = CashFlow::getOrCreate($loanYear);
            $cashFlow->capital += $validated['amount'];
            $cashFlow->save();

            // Create capital transaction for advance payment
            $loan->load('member');
            $borrowerName = $loan->member_id 
                ? ($loan->member ? $loan->member->first_name . ' ' . $loan->member->last_name : 'Unknown Member')
                : ($loan->non_member_name ?? 'Unknown');

            CapitalTransaction::create([
                'year' => $loanYear,
                'loan_id' => $loan->id,
                'type' => 'addition',
                'amount' => $validated['amount'],
                'description' => 'Advance payment from ' . $borrowerName . ' - Loan ID: ' . $loan->id,
            ]);
        }

        // Recalculate monthly interest for remaining months
        // Use loan's year if set, otherwise use current year
        $targetYear = $loan->year ?? date('Y');
        $currentMonth = date('n');
        $remainingBalance = $loan->fresh()->remaining_balance;

        for ($month = $currentMonth; $month <= 12; $month++) {
            $monthlyInterest = MonthlyInterestPayment::where('loan_id', $loan->id)
                ->where('month', $month)
                ->where('year', $targetYear)
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
        $monthlyInterestPayments = $loan->monthlyInterestPayments()->where('year', $targetYear)->get()->toArray();

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
        $loanYear = $loan->year;

        // Delete the advance payment
        $advancePayment->delete();

        // Update the loan balance (balance = balance + advance payment amount)
        $loan->balance = min($loan->amount, $loan->balance + $amount);
        $loan->save();

        // Deduct advance payment from capital when reverted
        if ($loanYear) {
            $cashFlow = CashFlow::where('year', $loanYear)->first();
            if ($cashFlow) {
                $cashFlow->capital = max(0, $cashFlow->capital - $amount);
                $cashFlow->save();
            }

            // Delete the capital transaction for this advance payment
            CapitalTransaction::where('loan_id', $loan->id)
                ->where('year', $loanYear)
                ->where('type', 'addition')
                ->where('description', 'like', '%Advance payment%')
                ->where('amount', $amount)
                ->orderBy('created_at', 'desc')
                ->first()
                ?->delete();
        }

        // Recalculate monthly interest for remaining months
        // Use loan's year if set, otherwise use current year
        $targetYear = $loan->year ?? date('Y');
        $currentMonth = date('n');
        $remainingBalance = $loan->fresh()->remaining_balance;

        for ($month = $currentMonth; $month <= 12; $month++) {
            $monthlyInterest = MonthlyInterestPayment::where('loan_id', $loan->id)
                ->where('month', $month)
                ->where('year', $targetYear)
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
        $monthlyInterestPayments = $loan->monthlyInterestPayments()->where('year', $targetYear)->get()->toArray();

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
            // Get all related records from old year only
            $oldYearPayments = MonthlyInterestPayment::where('loan_id', $loan->id)
                ->where('year', $oldYear)
                ->get();
            $advancePayments = AdvancePayment::where('loan_id', $loan->id)->get();
            $capitalTransactions = CapitalTransaction::where('loan_id', $loan->id)
                ->where('year', $oldYear)
                ->get();

            // Calculate totals from old year records only
            $totalInterestCollected = $oldYearPayments
                ->where('status', 'paid')
                ->sum('interest_amount');
            $totalAdvancePayments = $advancePayments->sum('amount');

            // Track payments that were deleted due to duplicates (to adjust capital correctly)
            $deletedPaidInterestAmount = 0;

            // Transfer MonthlyInterestPayment records to new year
            // Handle potential duplicates by merging existing records
            foreach ($oldYearPayments as $oldPayment) {
                // Check if a record already exists for the new year with the same month
                $existingPayment = MonthlyInterestPayment::where('loan_id', $loan->id)
                    ->where('month', $oldPayment->month)
                    ->where('year', $newYear)
                    ->first();

                if ($existingPayment) {
                    // Merge: prefer keeping paid status and payment dates from existing if paid
                    // Otherwise, use the transferred record's data
                    if ($existingPayment->status === 'paid' && $oldPayment->status === 'paid') {
                        // Both are paid - keep existing, delete old (don't double count in capital)
                        $deletedPaidInterestAmount += $oldPayment->interest_amount;
                        $oldPayment->delete();
                    } elseif ($existingPayment->status === 'paid' && $oldPayment->status === 'pending') {
                        // Existing is paid, old is pending - keep existing, delete old
                        $oldPayment->delete();
                    } else {
                        // Existing is pending, merge with old payment data
                        // Prefer paid status, payment dates, and notes from old payment if it has more info
                        $existingPayment->update([
                            'status' => $oldPayment->status === 'paid' ? 'paid' : $existingPayment->status,
                            'interest_amount' => $oldPayment->interest_amount, // Use transferred amount
                            'payment_date' => $oldPayment->payment_date ?? $existingPayment->payment_date,
                            'notes' => $oldPayment->notes ?? $existingPayment->notes,
                        ]);
                        $oldPayment->delete();
                    }
                } else {
                    // No conflict, just update the year
                    $oldPayment->update(['year' => $newYear]);
                }
            }

            // Adjust totalInterestCollected to exclude deleted duplicates
            $totalInterestCollected -= $deletedPaidInterestAmount;

            // Update CapitalTransaction records to new year
            CapitalTransaction::where('loan_id', $loan->id)
                ->where('year', $oldYear)
                ->update(['year' => $newYear]);

            // IMPORTANT: Update loan's year BEFORE recalculating interest_collected
            // so that the recalculation can find the transferred records
            $loan->year = $newYear;
            $loan->save();
            
            // Refresh the loan model to ensure relationships are updated
            $loan->refresh();

            // Adjust capital for old year: restore loan amount, remove interest and advance payments
            $oldCashFlow = CashFlow::where('year', $oldYear)->first();
            if ($oldCashFlow) {
                // Restore loan amount to capital
                $oldCashFlow->capital += $loanAmount;
                // Remove interest collected from capital (it was added when marked as paid)
                $oldCashFlow->capital = max(0, $oldCashFlow->capital - $totalInterestCollected);
                // Remove advance payments from capital (they were added when recorded)
                $oldCashFlow->capital = max(0, $oldCashFlow->capital - $totalAdvancePayments);
                $oldCashFlow->save();
            }

            // Recalculate money_released and interest_collected for old year
            CashFlow::recalculateMoneyReleased($oldYear);
            CashFlow::recalculateInterestCollected($oldYear);

            // Adjust capital for new year: deduct loan amount, add interest and advance payments
            $newCashFlow = CashFlow::getOrCreate($newYear);
            // Deduct loan amount from capital
            $newCashFlow->capital = max(0, $newCashFlow->capital - $loanAmount);
            // Add interest collected to capital (if any was paid)
            $newCashFlow->capital += $totalInterestCollected;
            // Add advance payments to capital (if any were recorded)
            $newCashFlow->capital += $totalAdvancePayments;
            $newCashFlow->save();

            // Recalculate money_released and interest_collected for new year
            // This will now correctly find the transferred records since loan year is updated
            CashFlow::recalculateMoneyReleased($newYear);
            // Force recalculation by clearing any potential query cache
            CashFlow::recalculateInterestCollected($newYear);
        } elseif (!$oldYear && $newYear) {
            // Loan didn't have a year before, now it does - deduct from capital
            // Update loan year first so recalculation can find any existing records
            $loan->year = $newYear;
            $loan->save();

            $cashFlow = CashFlow::getOrCreate($newYear);
            $cashFlow->capital = max(0, $cashFlow->capital - $loanAmount);
            $cashFlow->save();

            // Recalculate money_released and interest_collected for new year
            CashFlow::recalculateMoneyReleased($newYear);
            CashFlow::recalculateInterestCollected($newYear);

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

        // Update loan status
        // Note: Year was already updated above if it changed, so we only update status here
        $loan->update([
            'status' => $validated['status'],
        ]);

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
            $cashFlow = CashFlow::where('year', $loanYear)->first();
            if ($cashFlow) {
                // Add back the loan amount to capital
                $cashFlow->capital += $loanAmount;
                $cashFlow->save();
            }

            // Delete the associated capital transaction
            CapitalTransaction::where('loan_id', $loan->id)
                ->where('year', $loanYear)
                ->delete();
        }

        // Delete the loan (this will cascade delete advance payments and monthly interest payments)
        $loan->delete();

        // Recalculate money_released for the year after deletion
        if ($loanYear) {
            CashFlow::recalculateMoneyReleased($loanYear);
        }

        return redirect()->route('loans.index')
            ->with('success', 'Loan deleted successfully and capital restored.');
    }
}
