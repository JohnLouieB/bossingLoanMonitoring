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
        Schema::table('loans', function (Blueprint $table) {
            $table->decimal('balance', 10, 2)->default(0)->after('amount');
        });

        // Initialize balance for existing loans (balance = amount - sum of advance payments)
        DB::statement('
            UPDATE loans 
            SET balance = amount - COALESCE((
                SELECT SUM(amount) 
                FROM advance_payments 
                WHERE advance_payments.loan_id = loans.id
            ), 0)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
