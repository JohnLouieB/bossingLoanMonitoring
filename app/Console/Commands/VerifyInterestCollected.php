<?php

namespace App\Console\Commands;

use App\Models\CashFlow;
use App\Models\Loan;
use App\Models\MonthlyInterestPayment;
use Illuminate\Console\Command;

class VerifyInterestCollected extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cashflow:verify-interest {year : The year to verify}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify and show detailed breakdown of interest_collected calculation for a specific year';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $year = (int) $this->argument('year');

        // Validate year
        if ($year < 2000 || $year > 2100) {
            $this->error('Invalid year. Please provide a year between 2000 and 2100.');
            return Command::FAILURE;
        }

        // Get current value from cash_flows table
        $cashFlow = CashFlow::getOrCreate($year);
        $currentValue = $cashFlow->interest_collected;

        // Get all loans for this year
        $loans = Loan::where('year', $year)->get();

        $this->info("=== Year: {$year} ===");
        $this->info("Current interest_collected in cash_flows: " . number_format($currentValue, 2));
        $this->newLine();

        // Calculate actual value from MonthlyInterestPayment records
        $paidPayments = MonthlyInterestPayment::join('loans', 'monthly_interest_payments.loan_id', '=', 'loans.id')
            ->where('monthly_interest_payments.status', 'paid')
            ->where('loans.year', $year)
            ->where('monthly_interest_payments.year', $year)
            ->select('monthly_interest_payments.*', 'loans.id as loan_id', 'loans.member_id', 'loans.non_member_name')
            ->get();

        $actualValue = $paidPayments->sum('interest_amount') ?? 0;
        $paymentCount = $paidPayments->count();

        $this->info("Actual paid interest payments: " . number_format($actualValue, 2));
        $this->info("Number of paid payments: {$paymentCount}");
        $this->newLine();

        if (abs($currentValue - $actualValue) < 0.01) {
            $this->info("✓ Values match correctly!");
        } else {
            $this->warn("⚠ Mismatch detected!");
            $this->warn("Difference: " . number_format(abs($currentValue - $actualValue), 2));
        }

        $this->newLine();

        // Show breakdown by loan
        if ($paymentCount > 0) {
            $this->info("Breakdown by loan:");
            $loanBreakdown = $paidPayments->groupBy('loan_id');
            
            foreach ($loanBreakdown as $loanId => $payments) {
                $loan = Loan::find($loanId);
                $loanTotal = $payments->sum('interest_amount');
                $loanCount = $payments->count();
                
                $borrowerName = $loan && $loan->member_id 
                    ? ($loan->member ? $loan->member->first_name . ' ' . $loan->member->last_name : 'Unknown')
                    : ($loan->non_member_name ?? 'Unknown');
                
                $this->line("  - Loan ID {$loanId} ({$borrowerName}): {$loanCount} payment(s), " . number_format($loanTotal, 2));
            }
            
            $this->newLine();
            
            // Show monthly breakdown
            $this->info("Monthly breakdown:");
            $monthlyBreakdown = $paidPayments->groupBy('month');
            $months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 
                      'July', 'August', 'September', 'October', 'November', 'December'];
            
            foreach ($monthlyBreakdown->sortKeys() as $month => $payments) {
                $monthTotal = $payments->sum('interest_amount');
                $monthCount = $payments->count();
                $monthName = $months[$month] ?? "Month {$month}";
                $this->line("  - {$monthName}: {$monthCount} payment(s), " . number_format($monthTotal, 2));
            }
        } else {
            $this->info("No paid interest payments found for year {$year}.");
        }

        $this->newLine();
        $this->info("To recalculate, run: php artisan cashflow:recalculate-interest {$year}");

        return Command::SUCCESS;
    }
}
