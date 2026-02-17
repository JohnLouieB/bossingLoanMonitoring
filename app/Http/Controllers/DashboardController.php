<?php

namespace App\Http\Controllers;

use App\Models\CashFlow;
use App\Models\Member;
use App\Models\MonthlyContribution;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with metrics and charts.
     */
    public function index(): Response
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

        return Inertia::render('Dashboard', [
            'availableBalanceByYear' => $availableBalanceByYear,
            'moneyReleasedByYear' => $moneyReleasedByYear,
            'interestCollectedByYear' => $interestCollectedByYear,
            'membersWithUnpaidContributions' => $membersWithUnpaidContributions,
            'topLoaners' => $topLoaners,
        ]);
    }
}
