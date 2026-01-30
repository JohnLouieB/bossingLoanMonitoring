<?php

namespace App\Console\Commands;

use App\Models\CapitalCashFlow;
use App\Models\CapitalTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateInterestCollected extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'capital:update-interest {year : The year to update} {amount : The total interest amount} {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the interest collected amount for a specific year by replacing all interest-related capital transactions with a single aggregate transaction';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $year = (int) $this->argument('year');
        $newAmount = (float) $this->argument('amount');

        // Validate year
        if ($year < 2000 || $year > 2100) {
            $this->error('Invalid year. Please provide a year between 2000 and 2100.');

            return Command::FAILURE;
        }

        // Validate amount
        if ($newAmount < 0) {
            $this->error('Amount must be a positive number.');

            return Command::FAILURE;
        }

        // Find all interest-related capital transactions for the year
        // Interest transactions are additions with:
        // 1. loan_id not null and description containing "Interest payment" (individual payments)
        // 2. loan_id null and description containing "Interest collected" (aggregate transactions)
        $interestTransactions = CapitalTransaction::where('year', $year)
            ->where('type', 'addition')
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereNotNull('loan_id')
                      ->where('description', 'like', '%Interest payment%');
                })->orWhere(function ($q) {
                    $q->whereNull('loan_id')
                      ->where('description', 'like', '%Interest collected%');
                });
            })
            ->get();

        $currentTotal = $interestTransactions->sum('amount');

        $this->info("Year: {$year}");
        $this->info("Current interest collected: " . number_format($currentTotal, 2));
        $this->info("New interest collected: " . number_format($newAmount, 2));
        $this->info("Difference: " . number_format($newAmount - $currentTotal, 2));
        $this->info("Transactions to replace: {$interestTransactions->count()}");

        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to proceed with this update?')) {
                $this->info('Update cancelled.');

                return Command::SUCCESS;
            }
        }

        try {
            DB::beginTransaction();

            // Get or create capital entry for the year
            $capitalEntry = CapitalCashFlow::firstOrCreate(
                ['year' => $year],
                ['capital' => 0]
            );

            // Adjust capital: subtract old total, add new amount
            $capitalDifference = $newAmount - $currentTotal;
            $capitalEntry->capital += $capitalDifference;
            $capitalEntry->save();

            // Delete old interest transactions (both individual and aggregate)
            $deletedCount = CapitalTransaction::where('year', $year)
                ->where('type', 'addition')
                ->where(function ($query) {
                    $query->where(function ($q) {
                        $q->whereNotNull('loan_id')
                          ->where('description', 'like', '%Interest payment%');
                    })->orWhere(function ($q) {
                        $q->whereNull('loan_id')
                          ->where('description', 'like', '%Interest collected%');
                    });
                })
                ->delete();

            // Create new aggregate transaction
            if ($newAmount > 0) {
                CapitalTransaction::create([
                    'year' => $year,
                    'loan_id' => null,
                    'type' => 'addition',
                    'amount' => $newAmount,
                    'description' => "Interest collected for {$year} (aggregate)",
                ]);
            }

            DB::commit();

            $this->info("✓ Successfully updated interest collected for year {$year}");
            $this->info("✓ Deleted {$deletedCount} old transactions");
            $this->info("✓ Capital adjusted by: " . number_format($capitalDifference, 2));
            if ($newAmount > 0) {
                $this->info("✓ Created new aggregate transaction");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error updating interest collected: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
