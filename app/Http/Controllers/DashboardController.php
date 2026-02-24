<?php

namespace App\Http\Controllers;

use App\Models\CashFlow;
use App\Models\Loan;
use App\Models\Member;
use App\Models\MonthlyContribution;
use App\Models\MonthlyInterestPayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with metrics and charts.
     */
    public function index(Request $request): Response
    {
        $years = CashFlow::orderBy('year')->pluck('year')->toArray();

        // Total available balance per year (interest + contributions - loan balances)
        $availableBalanceByYear = [];
        foreach ($years as $year) {
            $availableBalanceByYear[] = [
                'year' => $year,
                'value' => (float) CashFlow::calculateAvailableCapital($year),
            ];
        }

        // Total money released per year (sum of loan amounts for that year)
        $moneyReleasedByYear = CashFlow::whereIn('year', $years)
            ->orderBy('year')
            ->get(['year', 'money_released'])
            ->map(fn ($row) => [
                'year' => $row->year,
                'value' => (float) $row->money_released,
            ])
            ->toArray();

        // Total interest collected per year
        $interestCollectedByYear = CashFlow::whereIn('year', $years)
            ->orderBy('year')
            ->get(['year', 'interest_collected'])
            ->map(fn ($row) => [
                'year' => $row->year,
                'value' => (float) $row->interest_collected,
            ])
            ->toArray();

        // Members with unpaid monthly contributions (pending)
        $membersWithUnpaidContributions = MonthlyContribution::with('member')
            ->where('status', 'pending')
            ->get()
            ->groupBy('member_id')
            ->map(function ($contributions, $memberId) {
                $member = $contributions->first()->member;
                return [
                    'id' => $member?->id,
                    'name' => $member ? trim($member->first_name . ' ' . $member->last_name) : 'Unknown',
                    'email' => $member?->email,
                    'unpaid_count' => $contributions->count(),
                    'unpaid_periods' => $contributions->map(fn ($c) => $c->year . '-' . str_pad($c->month, 2, '0', STR_PAD_LEFT))->values()->toArray(),
                ];
            })
            ->values()
            ->toArray();

        // Top loaners (members with most loans), limit 10
        $topLoaners = Member::withCount('loans')
            ->having('loans_count', '>', 0)
            ->orderByDesc('loans_count')
            ->limit(10)
            ->get()
            ->map(fn ($member) => [
                'id' => $member->id,
                'name' => trim($member->first_name . ' ' . $member->last_name),
                'email' => $member->email,
                'loans_count' => $member->loans_count,
            ])
            ->toArray();

        // Pending loan interest - filter by year (uses current month, only loans from selected year)
        $currentMonth = (int) date('n');
        $currentYear = (int) ($request->get('pending_year') ?? date('Y'));
        $loansWithUnpaidInterest = Loan::with(['member', 'advancePayments', 'monthlyInterestPayments'])
            ->where('year', $currentYear)
            ->whereDoesntHave('monthlyInterestPayments', function ($q) use ($currentMonth, $currentYear) {
                $q->where('month', $currentMonth)
                    ->where('year', $currentYear)
                    ->where('status', 'paid');
            })
            ->get()
            ->filter(function ($loan) use ($currentMonth, $currentYear) {
                $remainingBalance = max(0, $loan->amount - $loan->advancePayments->sum('amount'));
                if ($remainingBalance <= 0) {
                    return false;
                }
                // Interest starts the month after loan creation: loan made in Feb → first interest in March
                $createdAt = $loan->created_at;
                if ($createdAt) {
                    $createdMonth = (int) $createdAt->format('n');
                    $createdYear = (int) $createdAt->format('Y');
                    if ($createdYear === $currentYear && $createdMonth >= $currentMonth) {
                        return false; // Loan created this month or later - no interest due yet
                    }
                }
                return true;
            })
            ->map(function ($loan) use ($currentMonth, $currentYear) {
                $isMemberBorrower = empty($loan->non_member_name);
                $firstName = $isMemberBorrower
                    ? ($loan->member?->first_name ?? 'Unknown')
                    : (explode(' ', trim($loan->non_member_name ?? ''))[0] ?? $loan->non_member_name ?? 'Unknown');

                $paymentThisMonth = $loan->monthlyInterestPayments
                    ->where('month', $currentMonth)
                    ->where('year', $currentYear)
                    ->first();
                $interestToPay = $paymentThisMonth
                    ? (float) $paymentThisMonth->interest_amount
                    : (float) ($loan->amount * ($loan->interest_rate / 100));

                return [
                    'loan_id' => $loan->id,
                    'member_id' => $loan->member_id,
                    'borrower_type' => $isMemberBorrower ? 'member' : 'non-member',
                    'first_name' => $firstName,
                    'loan_amount' => (float) $loan->amount,
                    'interest_to_pay' => $interestToPay,
                ];
            })
            ->groupBy('borrower_type')
            ->map(fn ($items) => $items->values()->toArray())
            ->toArray();

        $pendingLoanInterest = [
            'member' => $loansWithUnpaidInterest['member'] ?? [],
            'non_member' => $loansWithUnpaidInterest['non-member'] ?? [],
        ];

        return Inertia::render('Dashboard', [
            'availableBalanceByYear' => $availableBalanceByYear,
            'moneyReleasedByYear' => $moneyReleasedByYear,
            'interestCollectedByYear' => $interestCollectedByYear,
            'membersWithUnpaidContributions' => $membersWithUnpaidContributions,
            'topLoaners' => $topLoaners,
            'pendingLoanInterest' => $pendingLoanInterest,
            'pendingInterestYear' => $currentYear,
        ]);
    }
}
