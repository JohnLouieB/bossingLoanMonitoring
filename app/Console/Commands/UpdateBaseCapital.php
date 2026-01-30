<?php

namespace App\Console\Commands;

use App\Models\CapitalCashFlow;
use App\Models\CapitalTransaction;
use App\Models\Loan;
use App\Models\MonthlyContribution;
use App\Models\MonthlyInterestPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateBaseCapital extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'capital:update-base {year : The year to update} {baseCapital : The target base capital amount} {--available-capital= : The target available capital amount (optional)} {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the base capital for a specific year by adjusting the initial capital amount';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $year = (int) $this->argument('year');
        $targetBaseCapital = (float) $this->argument('baseCapital');
        $targetAvailableCapital = $this->option('available-capital') ? (float) $this->option('available-capital') : null;

        // Validate year
        if ($year < 2000 || $year > 2100) {
            $this->error('Invalid year. Please provide a year between 2000 and 2100.');

            return Command::FAILURE;
        }

        // Validate base capital
        if ($targetBaseCapital < 0) {
            $this->error('Base capital must be a positive number.');

            return Command::FAILURE;
        }

        try {
            // Calculate total money collected (interest + contributions + advance payments)
            $totalInterestCollected = MonthlyInterestPayment::where('status', 'paid')
                ->whereHas('loan', function ($query) use ($year) {
                    $query->where('year', $year);
                })
                ->sum('interest_amount');

            $totalContributionsCollected = MonthlyContribution::where('year', $year)
                ->where('status', 'paid')
                ->sum('amount');

            $totalAdvancePayments = CapitalTransaction::where('year', $year)
                ->where('type', 'addition')
                ->where('description', 'like', '%Advance payment%')
                ->sum('amount');

            $totalMoneyCollected = $totalInterestCollected + $totalContributionsCollected + $totalAdvancePayments;

            // Calculate total loan balances
            $totalLoanBalances = Loan::where('year', $year)
                ->get()
                ->sum(function ($loan) {
                    $totalAdvancePayments = $loan->advancePayments()->sum('amount');
                    return max(0, $loan->amount - $totalAdvancePayments);
                });

            // Get current capital entry
            $capitalEntry = CapitalCashFlow::firstOrCreate(
                ['year' => $year],
                ['capital' => 0]
            );

            $currentInitialCapital = $capitalEntry->capital;
            $currentBaseCapital = $currentInitialCapital + $totalMoneyCollected;
            $currentAvailableCapital = max(0, $currentBaseCapital - $totalLoanBalances);

            // Calculate required initial capital to achieve target base capital
            // baseCapital = initialCapital + totalMoneyCollected
            // Therefore: initialCapital = baseCapital - totalMoneyCollected
            $requiredInitialCapital = $targetBaseCapital - $totalMoneyCollected;

            // Calculate what the available capital would be with the new base capital
            $newAvailableCapital = max(0, $targetBaseCapital - $totalLoanBalances);

            // Display current state
            $this->info("=== Current State (Year {$year}) ===");
            $this->info("Initial Capital: " . number_format($currentInitialCapital, 2));
            $this->info("Total Money Collected: " . number_format($totalMoneyCollected, 2));
            $this->info("  - Interest Collected: " . number_format($totalInterestCollected, 2));
            $this->info("  - Contributions Collected: " . number_format($totalContributionsCollected, 2));
            $this->info("  - Advance Payments: " . number_format($totalAdvancePayments, 2));
            $this->info("Base Capital: " . number_format($currentBaseCapital, 2));
            $this->info("Total Loan Balances: " . number_format($totalLoanBalances, 2));
            $this->info("Available Capital: " . number_format($currentAvailableCapital, 2));

            $this->newLine();
            $this->info("=== Target State ===");
            $this->info("Target Base Capital: " . number_format($targetBaseCapital, 2));
            $this->info("Required Initial Capital: " . number_format($requiredInitialCapital, 2));
            $this->info("New Available Capital (calculated): " . number_format($newAvailableCapital, 2));

            // Check if target available capital is specified and if it's achievable
            if ($targetAvailableCapital !== null) {
                if ($targetAvailableCapital > $targetBaseCapital) {
                    $this->error("ERROR: Target available capital ({$targetAvailableCapital}) cannot exceed base capital ({$targetBaseCapital}).");
                    $this->error("Available capital = Base capital - Total loan balances");
                    $this->error("Maximum possible available capital: " . number_format($targetBaseCapital, 2));

                    return Command::FAILURE;
                }

                if (abs($newAvailableCapital - $targetAvailableCapital) > 0.01) {
                    $this->warn("WARNING: Calculated available capital (" . number_format($newAvailableCapital, 2) . ") does not match target (" . number_format($targetAvailableCapital, 2) . ").");
                    $this->warn("This would require total loan balances to be: " . number_format($targetBaseCapital - $targetAvailableCapital, 2));
                    $this->warn("Current total loan balances: " . number_format($totalLoanBalances, 2));
                } else {
                    $this->info("✓ Target available capital matches calculated value.");
                }
            }

            if (!$this->option('force')) {
                if (!$this->confirm('Do you want to proceed with this update?')) {
                    $this->info('Update cancelled.');

                    return Command::SUCCESS;
                }
            }

            DB::beginTransaction();

            // Update initial capital
            $capitalEntry->capital = $requiredInitialCapital;
            $capitalEntry->save();

            DB::commit();

            $this->newLine();
            $this->info("✓ Successfully updated base capital for year {$year}");
            $this->info("✓ Initial capital set to: " . number_format($requiredInitialCapital, 2));
            $this->info("✓ Base capital is now: " . number_format($targetBaseCapital, 2));
            $this->info("✓ Available capital is now: " . number_format($newAvailableCapital, 2));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error updating base capital: ' . $e->getMessage());
            $this->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
