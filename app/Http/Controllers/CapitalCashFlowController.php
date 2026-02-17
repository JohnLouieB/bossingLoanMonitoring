<?php

namespace App\Http\Controllers;

use App\Models\CapitalDeduction;
use App\Models\CapitalTransaction;
use App\Models\CashFlow;
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

        // Calculate total money collected (interest + contributions only, excluding advance payments)
        $totalMoneyCollected = $totalInterestCollected + $totalContributionsCollected;

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

        // Calculate available capital: (interest + contributions) - total loan balances - total deductions
        $totalDeductions = CapitalDeduction::where('year', $currentYear)->sum('amount');
        $availableCapital = max(0, ($totalInterestCollected + $totalContributionsCollected) - $totalLoanBalances - $totalDeductions);

        // Deductions for the selected year (for the Deductions card list)
        $deductions = CapitalDeduction::where('year', $currentYear)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($d) {
                $monthName = date('F', mktime(0, 0, 0, $d->month, 1));
                $who = $d->user ? $d->user->name : 'An admin';

                return [
                    'id' => $d->id,
                    'amount' => $d->amount,
                    'month' => $d->month,
                    'month_name' => $monthName,
                    'description' => $d->description ?? "{$who} has deducted {$d->amount} pesos for fee of the month of {$monthName}.",
                    'created_at' => $d->created_at,
                ];
            });

        // Activity table: single list with tab filter (transactions | interest | contributions), search, pagination
        $activityTab = $request->get('activity_tab', 'transactions');
        $activitySearch = (string) ($request->get('activity_search') ?? '');
        $activityPage = max(1, (int) $request->get('activity_page', 1));
        $perPage = 10;

        $activityData = match ($activityTab) {
            'interest' => $this->getInterestActivity($currentYear, $activitySearch, $activityPage, $perPage),
            'contributions' => $this->getContributionsActivity($currentYear, $activitySearch, $activityPage, $perPage),
            default => $this->getTransactionsActivity($currentYear, $activitySearch, $activityPage, $perPage),
        };

        return Inertia::render('CapitalCashFlow/Index', [
            'initialCapital' => $initialCapital,
            'baseCapital' => $baseCapital,
            'availableCapital' => $availableCapital,
            'totalLoanBalances' => $totalLoanBalances,
            'totalInterestCollected' => $totalInterestCollected,
            'totalContributionsCollected' => $totalContributionsCollected,
            'totalAdvancePayments' => $totalAdvancePayments,
            'totalDeductions' => (float) $totalDeductions,
            'deductions' => $deductions,
            'moneyReleased' => $moneyReleased,
            'currentYear' => (int) $currentYear,
            'filters' => $request->only(['year']),
            'activityTab' => $activityTab,
            'activitySearch' => $activitySearch,
            'activityPage' => $activityPage,
            'activityData' => $activityData,
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

    /**
     * Store a deduction (e.g. monthly fee) for the current or selected year. Deduction reduces Available Capital.
     */
    public function storeDeduction(Request $request)
    {
        $request->validate([
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $year = (int) ($request->input('year') ?? date('Y'));
        $month = (int) date('n');
        $amount = 15;

        // Prevent duplicate deduction for the same year+month
        $exists = CapitalDeduction::where('year', $year)->where('month', $month)->exists();
        if ($exists) {
            return redirect()->route('capital-cash-flow.index', ['year' => $year])
                ->with('error', 'A deduction for this month has already been recorded. You can deduct again next month.');
        }

        $monthName = date('F', mktime(0, 0, 0, $month, 1)); // e.g. February
        $description = 'An admin has deducted a ' . $amount . ' pesos for fee of the month of ' . $monthName;

        CapitalDeduction::create([
            'year' => $year,
            'amount' => $amount,
            'month' => $month,
            'description' => $description,
            'user_id' => $request->user()->id,
        ]);

        return redirect()->route('capital-cash-flow.index', ['year' => $year])
            ->with('success', 'Deduction recorded.');
    }

    /**
     * Undo (delete) a capital deduction. Re-enables the deduct button for that month.
     */
    public function destroyDeduction(CapitalDeduction $capitalDeduction)
    {
        $year = $capitalDeduction->year;
        $capitalDeduction->delete();

        return redirect()->route('capital-cash-flow.index', ['year' => $year])
            ->with('success', 'Deduction undone.');
    }

    /**
     * Paginated transactions (loan disbursements, advance/interest/contribution additions) with optional member search.
     */
    private function getTransactionsActivity(int $year, string $search, int $page, int $perPage): array
    {
        $query = CapitalTransaction::where('year', $year)
            ->with('loan.member');

        if ($search !== '') {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('description', 'like', $term)
                    ->orWhereHas('loan', function ($loanQuery) use ($term) {
                        $loanQuery->where('non_member_name', 'like', $term)
                            ->orWhereHas('member', function ($memberQuery) use ($term) {
                                $memberQuery->where('first_name', 'like', $term)
                                    ->orWhere('last_name', 'like', $term)
                                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', [$term])
                                    ->orWhereRaw('CONCAT(last_name, " ", first_name) LIKE ?', [$term]);
                            });
                    });
            });
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'activity_page');

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /**
     * Paginated interest collected with optional borrower search.
     */
    private function getInterestActivity(int $year, string $search, int $page, int $perPage): array
    {
        $query = MonthlyInterestPayment::where('status', 'paid')
            ->whereHas('loan', function ($q) use ($year) {
                $q->where('year', $year);
            })
            ->with(['loan.member']);

        if ($search !== '') {
            $term = '%' . trim($search) . '%';
            $query->whereHas('loan', function ($loanQuery) use ($term) {
                $loanQuery->where('non_member_name', 'like', $term)
                    ->orWhereHas('member', function ($memberQuery) use ($term) {
                        $memberQuery->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', [$term])
                            ->orWhereRaw('CONCAT(last_name, " ", first_name) LIKE ?', [$term]);
                    });
            });
        }

        $paginator = $query->orderBy('created_at', 'desc')
            ->orderBy('payment_date', 'desc')
            ->paginate($perPage, ['*'], 'activity_page');

        $items = collect($paginator->items())->map(function ($payment) {
            $loan = $payment->loan;
            if (! $loan || ! $loan->year) {
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
        })->filter()->values()->all();

        return [
            'data' => $items,
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /**
     * Paginated monthly contributions collected with optional member search.
     */
    private function getContributionsActivity(int $year, string $search, int $page, int $perPage): array
    {
        $query = MonthlyContribution::where('year', $year)
            ->where('status', 'paid')
            ->with('member');

        if ($search !== '') {
            $term = '%' . trim($search) . '%';
            $query->whereHas('member', function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', [$term])
                    ->orWhereRaw('CONCAT(last_name, " ", first_name) LIKE ?', [$term]);
            });
        }

        $paginator = $query->orderBy('created_at', 'desc')
            ->orderBy('payment_date', 'desc')
            ->paginate($perPage, ['*'], 'activity_page');

        $items = collect($paginator->items())->map(function ($contribution) {
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
        })->all();

        return [
            'data' => $items,
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ];
    }
}
