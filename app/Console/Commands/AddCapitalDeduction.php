<?php

namespace App\Console\Commands;

use App\Models\CapitalDeduction;
use Illuminate\Console\Command;

class AddCapitalDeduction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'capital:add-deduction
                            {year : The year (e.g. 2026)}
                            {month : The month (1-12 or jan,january,feb,...)}
                            {amount=15 : The amount to deduct in pesos}
                            {--force : Skip duplicate check and confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a capital deduction for a specific month. The amount is deducted from available capital and listed in the Deductions section.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $year = (int) $this->argument('year');
        $monthInput = $this->argument('month');
        $amount = (float) $this->argument('amount');

        // Parse month (supports 1-12 or jan, january, feb, etc.)
        $month = $this->parseMonth($monthInput);
        if ($month === null) {
            $this->error("Invalid month: {$monthInput}. Use 1-12 or month name (jan, january, feb, etc.).");

            return Command::FAILURE;
        }

        if ($year < 2000 || $year > 2100) {
            $this->error('Invalid year. Please provide a year between 2000 and 2100.');

            return Command::FAILURE;
        }

        if ($amount <= 0) {
            $this->error('Amount must be a positive number.');

            return Command::FAILURE;
        }

        $monthName = date('F', mktime(0, 0, 0, $month, 1));

        // Prevent duplicate deduction for the same year+month (unless --force)
        if (! $this->option('force')) {
            $exists = CapitalDeduction::where('year', $year)->where('month', $month)->exists();
            if ($exists) {
                $this->error("A deduction for {$monthName} {$year} already exists. Use --force to add another (not recommended).");

                return Command::FAILURE;
            }
        }

        if (! $this->option('force') && ! $this->confirm("Add deduction of P" . number_format($amount, 2) . " for {$monthName} {$year}? This will reduce available capital.")) {
            $this->info('Cancelled.');

            return Command::SUCCESS;
        }

        $description = "An admin has deducted a {$amount} pesos for fee of the month of {$monthName}.";

        CapitalDeduction::create([
            'year' => $year,
            'amount' => $amount,
            'month' => $month,
            'description' => $description,
            'user_id' => null,
        ]);

        $this->info("✓ Deduction of P" . number_format($amount, 2) . " for {$monthName} {$year} has been recorded.");
        $this->info('  It will appear in the Deductions section and reduce Available Capital.');

        return Command::SUCCESS;
    }

    /**
     * Parse month from string or number. Returns 1-12 or null if invalid.
     */
    private function parseMonth(string $input): ?int
    {
        $input = strtolower(trim($input));

        $months = [
            'jan' => 1, 'january' => 1,
            'feb' => 2, 'february' => 2,
            'mar' => 3, 'march' => 3,
            'apr' => 4, 'april' => 4,
            'may' => 5,
            'jun' => 6, 'june' => 6,
            'jul' => 7, 'july' => 7,
            'aug' => 8, 'august' => 8,
            'sep' => 9, 'sept' => 9, 'september' => 9,
            'oct' => 10, 'october' => 10,
            'nov' => 11, 'november' => 11,
            'dec' => 12, 'december' => 12,
        ];

        if (isset($months[$input])) {
            return $months[$input];
        }

        $num = (int) $input;
        if ($num >= 1 && $num <= 12) {
            return $num;
        }

        return null;
    }
}
