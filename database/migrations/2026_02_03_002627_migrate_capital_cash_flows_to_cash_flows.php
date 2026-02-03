<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if capital_cash_flows table exists
        if (!Schema::hasTable('capital_cash_flows')) {
            // Table doesn't exist, skip migration
            return;
        }

        // Get all years from capital_cash_flows
        $years = DB::table('capital_cash_flows')->pluck('year');

        foreach ($years as $year) {
            // Get capital from capital_cash_flows
            $capitalEntry = DB::table('capital_cash_flows')
                ->where('year', $year)
                ->first();
            
            $capital = $capitalEntry ? $capitalEntry->capital : 0;

            // Calculate interest_collected from MonthlyInterestPayment
            // (status='paid' and loan.year matches)
            $interestCollected = DB::table('monthly_interest_payments')
                ->join('loans', 'monthly_interest_payments.loan_id', '=', 'loans.id')
                ->where('monthly_interest_payments.status', 'paid')
                ->where('loans.year', $year)
                ->sum('monthly_interest_payments.interest_amount') ?? 0;

            // Calculate monthly_contributions_collected from MonthlyContribution
            // (status='paid' and year matches)
            $contributionsCollected = DB::table('monthly_contributions')
                ->where('year', $year)
                ->where('status', 'paid')
                ->sum('amount') ?? 0;

            // Calculate money_released from Loan (sum of amounts where year matches)
            $moneyReleased = DB::table('loans')
                ->where('year', $year)
                ->sum('amount') ?? 0;

            // Insert into cash_flows
            DB::table('cash_flows')->insert([
                'year' => $year,
                'capital' => $capital,
                'interest_collected' => $interestCollected,
                'monthly_contributions_collected' => $contributionsCollected,
                'money_released' => $moneyReleased,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Also handle any years that exist in loans/contributions but not in capital_cash_flows
        $loanYears = DB::table('loans')->distinct()->pluck('year');
        $contributionYears = DB::table('monthly_contributions')->distinct()->pluck('year');
        $allYears = $loanYears->merge($contributionYears)->unique();

        foreach ($allYears as $year) {
            // Skip if already migrated
            if (DB::table('cash_flows')->where('year', $year)->exists()) {
                continue;
            }

            // Get capital (default to 0 if not in capital_cash_flows)
            $capitalEntry = DB::table('capital_cash_flows')
                ->where('year', $year)
                ->first();
            $capital = $capitalEntry ? $capitalEntry->capital : 0;

            // Calculate all fields
            $interestCollected = DB::table('monthly_interest_payments')
                ->join('loans', 'monthly_interest_payments.loan_id', '=', 'loans.id')
                ->where('monthly_interest_payments.status', 'paid')
                ->where('loans.year', $year)
                ->sum('monthly_interest_payments.interest_amount') ?? 0;

            $contributionsCollected = DB::table('monthly_contributions')
                ->where('year', $year)
                ->where('status', 'paid')
                ->sum('amount') ?? 0;

            $moneyReleased = DB::table('loans')
                ->where('year', $year)
                ->sum('amount') ?? 0;

            // Insert into cash_flows
            DB::table('cash_flows')->insert([
                'year' => $year,
                'capital' => $capital,
                'interest_collected' => $interestCollected,
                'monthly_contributions_collected' => $contributionsCollected,
                'money_released' => $moneyReleased,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Drop capital_cash_flows table
        Schema::dropIfExists('capital_cash_flows');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate capital_cash_flows table
        Schema::create('capital_cash_flows', function (Blueprint $table) {
            $table->id();
            $table->integer('year')->unique();
            $table->decimal('capital', 15, 2)->default(0);
            $table->timestamps();
        });

        // Migrate capital data back
        $cashFlows = DB::table('cash_flows')->get();
        foreach ($cashFlows as $cashFlow) {
            DB::table('capital_cash_flows')->insert([
                'year' => $cashFlow->year,
                'capital' => $cashFlow->capital,
                'created_at' => $cashFlow->created_at,
                'updated_at' => $cashFlow->updated_at,
            ]);
        }
    }
};
