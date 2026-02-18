<?php

namespace App\Http\Controllers;

use App\Mail\MonthlyReportMail;
use App\Models\CashFlow;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ReportController extends Controller
{
    /**
     * Send the monthly report email to each member.
     * Each email is personalized: member's loans, interest, contribution status, plus org-wide totals.
     */
    public function sendReport(Request $request): RedirectResponse
    {
        $currentMonth = (int) now()->month;
        $currentYear = (int) now()->year;
        $monthName = now()->format('F');

        // Total available money across all years
        $years = CashFlow::orderBy('year')->pluck('year')->toArray();
        $totalAvailableMoneyAllYears = 0.0;
        foreach ($years as $year) {
            $totalAvailableMoneyAllYears += CashFlow::calculateAvailableCapital($year);
        }

        // Total money released across all years
        $totalMoneyReleasedAllYears = (float) CashFlow::sum('money_released');

        // Get all active members with email
        $members = Member::where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->with(['loans.monthlyInterestPayments', 'loans.advancePayments', 'monthlyContributions'])
            ->get();

        $sentCount = 0;
        $failedCount = 0;
        $firstError = null;

        foreach ($members as $member) {
            // Member's loans with interest this month and remaining balance
            $memberLoans = [];
            foreach ($member->loans as $loan) {
                $interestThisMonth = (float) $loan->monthlyInterestPayments
                    ->where('month', $currentMonth)
                    ->where('year', $currentYear)
                    ->sum('interest_amount');

                $totalAdvancePayments = $loan->advancePayments->sum('amount');
                $remainingBalance = max(0, $loan->amount - $totalAdvancePayments);

                $memberLoans[] = [
                    'loan_amount' => (float) $loan->amount,
                    'interest_this_month' => $interestThisMonth,
                    'remaining_balance' => $remainingBalance,
                    'description' => $loan->description,
                ];
            }

            // Member's monthly contribution status for current month
            $contribution = $member->monthlyContributions
                ->where('month', $currentMonth)
                ->where('year', $currentYear)
                ->first();
            $monthlyContributionStatus = $contribution && $contribution->status === 'paid' ? 'Paid' : 'Pending';
            $monthlyContributionAmount = (float) ($contribution?->amount ?? $member->monthlyContributions->first()?->amount ?? 300);

            try {
                Mail::to($member->email)->send(new MonthlyReportMail(
                    memberName: trim($member->first_name . ' ' . $member->last_name),
                    memberLoans: $memberLoans,
                    monthlyContributionStatus: $monthlyContributionStatus,
                    monthlyContributionAmount: $monthlyContributionAmount,
                    totalAvailableMoneyAllYears: $totalAvailableMoneyAllYears,
                    totalMoneyReleasedAllYears: $totalMoneyReleasedAllYears,
                    reportMonth: $monthName,
                    reportYear: $currentYear
                ));
                $sentCount++;
            } catch (\Throwable $e) {
                $failedCount++;
                if ($firstError === null) {
                    $firstError = $e->getMessage();
                }
                report($e);
            }
        }

        $message = "Report sent to {$sentCount} member(s).";
        if ($failedCount > 0) {
            $message .= " Failed to send to {$failedCount} member(s).";
            if ($firstError && str_contains($firstError, 'Username and Password not accepted')) {
                $message .= " Gmail rejected the credentials — please verify your App Password in .env (use a fresh one from Google Account → Security → App passwords).";
            } elseif ($firstError && strlen($firstError) < 120) {
                $message .= " Error: " . $firstError;
            }
        }
        if ($members->isEmpty()) {
            $message = 'No members with valid email addresses found. Report was not sent.';
        }

        return redirect()->route('members.index')->with('status', $message);
    }
}
