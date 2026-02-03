<?php

namespace App\Console\Commands;

use App\Models\CashFlow;
use App\Models\MonthlyInterestPayment;
use Illuminate\Console\Command;

class SetInterestCollected extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cashflow:set-interest {year : The year to update} {amount : The interest collected amount} {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set the interest_collected value for a specific year in the cash_flows table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $year = (int) $this->argument('year');
        $amount = (float) $this->argument('amount');

        // Validate year
        if ($year < 2000 || $year > 2100) {
            $this->error('Invalid year. Please provide a year between 2000 and 2100.');
            return Command::FAILURE;
        }

        // Validate amount
        if ($amount < 0) {
            $this->error('Amount must be a positive number.');
            return Command::FAILURE;
        }

        // Get or create cash flow entry for the year
        $cashFlow = CashFlow::getOrCreate($year);

        // Show current values
        $currentBase = $cashFlow->interest_collected_base ?? 0;
        $currentPaidPayments = MonthlyInterestPayment::join('loans', 'monthly_interest_payments.loan_id', '=', 'loans.id')
            ->where('monthly_interest_payments.status', 'paid')
            ->where('loans.year', $year)
            ->where('monthly_interest_payments.year', $year)
            ->sum('monthly_interest_payments.interest_amount') ?? 0;
        $currentTotal = $cashFlow->interest_collected;
        
        $this->info("Current interest_collected_base for year {$year}: " . number_format($currentBase, 2));
        $this->info("Current paid payments total: " . number_format($currentPaidPayments, 2));
        $this->info("Current total interest_collected: " . number_format($currentTotal, 2));
        $this->info("New interest_collected_base for year {$year}: " . number_format($amount, 2));
        $this->info("New total will be: " . number_format($amount + $currentPaidPayments, 2));
        $this->newLine();

        // Confirm if not forced
        if (!$this->option('force')) {
            $newTotal = number_format($amount + $currentPaidPayments, 2);
            $confirmMessage = "Do you want to set interest_collected_base to " . number_format($amount, 2) . " for year {$year}? Total will be {$newTotal}";
            if (!$this->confirm($confirmMessage)) {
                $this->info('Operation cancelled.');
                return Command::FAILURE;
            }
        }

        try {
            // Update the interest_collected_base value (this is the manually set base amount)
            $cashFlow->interest_collected_base = $amount;
            $cashFlow->save();

            // Recalculate to update the total (base + paid payments)
            CashFlow::recalculateInterestCollected($year);
            
            // Refresh to get updated total
            $cashFlow->refresh();

            $this->info("✓ Successfully set interest_collected_base to " . number_format($amount, 2) . " for year {$year}");
            $this->info("✓ Total interest_collected is now: " . number_format($cashFlow->interest_collected, 2) . " (base: " . number_format($amount, 2) . " + payments: " . number_format($currentPaidPayments, 2) . ")");
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
