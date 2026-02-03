<?php

namespace App\Console\Commands;

use App\Models\CashFlow;
use App\Models\CapitalTransaction;
use App\Models\Member;
use App\Models\MonthlyContribution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkContributionsPaid extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contributions:mark-paid {year : The year to process} {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark all pending monthly contributions as paid for a specific year';

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

        // Get all pending contributions for the year
        $pendingContributions = MonthlyContribution::where('year', $year)
            ->where('status', 'pending')
            ->with('member')
            ->get();

        if ($pendingContributions->isEmpty()) {
            $this->info("No pending contributions found for year {$year}.");
            return Command::SUCCESS;
        }

        $totalAmount = $pendingContributions->sum('amount');
        $count = $pendingContributions->count();

        $this->info("=== Year: {$year} ===");
        $this->info("Found {$count} pending contribution(s)");
        $this->info("Total amount: " . number_format($totalAmount, 2));
        $this->newLine();

        // Show breakdown by member
        $this->info("Breakdown by member:");
        $memberBreakdown = $pendingContributions->groupBy('member_id');
        foreach ($memberBreakdown as $memberId => $contributions) {
            $member = $contributions->first()->member;
            $memberTotal = $contributions->sum('amount');
            $memberCount = $contributions->count();
            $this->line("  - {$member->first_name} {$member->last_name}: {$memberCount} contribution(s), " . number_format($memberTotal, 2));
        }
        $this->newLine();

        // Confirm if not forced
        if (!$this->option('force')) {
            if (!$this->confirm("Do you want to mark all {$count} pending contribution(s) as paid for year {$year}?")) {
                $this->info('Operation cancelled.');
                return Command::FAILURE;
            }
        }

        try {
            DB::beginTransaction();

            $cashFlow = CashFlow::getOrCreate($year);
            $updatedCount = 0;
            $totalCapitalAdded = 0;

            foreach ($pendingContributions as $contribution) {
                $member = $contribution->member;
                $contributionAmount = $contribution->amount;
                $month = $contribution->month;
                $contributionYear = $contribution->year ?? $year;

                // Update contribution status
                $contribution->status = 'paid';
                $contribution->payment_date = $contribution->payment_date ?? now();
                $contribution->year = $contributionYear; // Ensure year is set
                $contribution->save();

                // Add contribution to capital
                $cashFlow->capital += $contributionAmount;
                $totalCapitalAdded += $contributionAmount;

                // Create capital transaction
                $memberName = $member->first_name . ' ' . $member->last_name;
                $monthName = date('F', mktime(0, 0, 0, $month, 1));

                CapitalTransaction::create([
                    'year' => $contributionYear,
                    'loan_id' => null,
                    'type' => 'addition',
                    'amount' => $contributionAmount,
                    'description' => 'Monthly contribution from ' . $memberName . ' - ' . $monthName . ' ' . $contributionYear,
                ]);

                $updatedCount++;
            }

            // Save cash flow with updated capital
            $cashFlow->save();

            // Recalculate monthly_contributions_collected for the year
            CashFlow::recalculateContributionsCollected($year);

            DB::commit();

            $this->newLine();
            $this->info("✓ Successfully marked {$updatedCount} contribution(s) as paid");
            $this->info("✓ Added " . number_format($totalCapitalAdded, 2) . " to capital for year {$year}");
            $this->info("✓ Created {$updatedCount} capital transaction(s)");
            $this->info("✓ Recalculated monthly_contributions_collected for year {$year}");
            
            // Show updated values
            $cashFlow->refresh();
            $this->newLine();
            $this->info("Updated cash flow values for year {$year}:");
            $this->line("  - Capital: " . number_format($cashFlow->capital, 2));
            $this->line("  - Monthly Contributions Collected: " . number_format($cashFlow->monthly_contributions_collected, 2));

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            
            $this->error('An error occurred: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
