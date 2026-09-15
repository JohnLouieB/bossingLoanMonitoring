<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedSmallInteger('joined_year')->nullable()->after('is_active');
        });

        // Backfill existing members using the year they were added to the system.
        // YEAR() is MySQL-only, so pick the equivalent for the active driver.
        $yearExpression = match (DB::getDriverName()) {
            'pgsql' => 'CAST(EXTRACT(YEAR FROM created_at) AS SMALLINT)',
            'sqlite' => "CAST(strftime('%Y', created_at) AS INTEGER)",
            default => 'YEAR(created_at)',
        };

        DB::statement("UPDATE members SET joined_year = {$yearExpression}");
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('joined_year');
        });
    }
};
