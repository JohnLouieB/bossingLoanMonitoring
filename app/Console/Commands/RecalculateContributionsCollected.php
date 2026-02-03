<?php

namespace App\Console\Commands;

use App\Models\CashFlow;
use App\Models\MonthlyContribution;
use Illuminate\Console\Command;

class RecalculateContributionsCollected extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cashflow:recalculate-contributions {year : The year to recalculate} {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate monthly_contributions_collected for a specific year based on actual paid contributions';

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
        $currentValue = $cashFlow->monthly_contributions_collected;

        // Calculate actual value from MonthlyContribution records
        $actualValue = MonthlyContribution::where('year', $year)
            ->where('status', 'paid')
            ->sum('amount') ?? 0;

        // Count contributions
        $paidContributionsCount = MonthlyContribution::where('year', $year)
            ->where('status', 'paid')
            ->count();

        $this->info("=== Year: {$year} ===");
        $this->info("Current monthly_contributions_collected: " . number_format($currentValue, 2));
        $this->info("Actual paid contributions: " . number_format($actualValue, 2));
        $this->info("Number of paid contributions: {$paidContributionsCount}");
        $this->newLine();

        if ($currentValue == $actualValue) {
            $this->info("✓ Values match. No update needed.");
            return Command::SUCCESS;
        }

        // Confirm if not forced
        if (!$this->option('force')) {
            if (!$this->confirm("Do you want to update monthly_contributions_collected to " . number_format($actualValue, 2) . " for year {$year}?")) {
                $this->info('Operation cancelled.');
                return Command::FAILURE;
            }
        }

        try {
            // Recalculate contributions collected
            CashFlow::recalculateContributionsCollected($year);

            $this->info("✓ Successfully recalculated monthly_contributions_collected to " . number_format($actualValue, 2) . " for year {$year}");
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
