<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capital_deductions', function (Blueprint $table) {
            $table->string('type', 30)->default('monthly_fee')->after('month');
        });
    }

    public function down(): void
    {
        Schema::table('capital_deductions', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
