<?php

namespace App\Console\Commands;

use App\Models\CapitalDeduction;
use App\Models\SystemSetting;
use Illuminate\Console\Command;

class AutoDeductServerFee extends Command
{
    protected $signature = 'capital:auto-deduct-server-fee
                            {--force : Skip duplicate check}';

    protected $description = 'Auto-deduct the monthly server fee from capital on the 30th of each month.';

    public function handle(): int
    {
        $settings = SystemSetting::current();
        $amount = (float) $settings->monthly_server_amount;

        if ($amount <= 0) {
            $this->info('Monthly server amount is not set or zero. Skipping auto-deduction.');
            return Command::SUCCESS;
        }

        $year  = (int) now()->format('Y');
        $month = (int) now()->format('n');
        $monthName = now()->format('F');

        if (! $this->option('force')) {
            $exists = CapitalDeduction::where('year', $year)->where('month', $month)->exists();
            if ($exists) {
                $this->info("Server fee deduction for {$monthName} {$year} already exists. Skipping.");
                return Command::SUCCESS;
            }
        }

        CapitalDeduction::create([
            'year'        => $year,
            'amount'      => $amount,
            'month'       => $month,
            'description' => "Auto-deducted monthly server fee of P" . number_format($amount, 2) . " for {$monthName} {$year}.",
            'user_id'     => null,
        ]);

        $this->info("✓ Auto-deducted server fee of P" . number_format($amount, 2) . " for {$monthName} {$year}.");

        return Command::SUCCESS;
    }
}
