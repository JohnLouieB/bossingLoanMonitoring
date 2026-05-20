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

        // Backfill existing members using the year they were added to the system
        DB::statement('UPDATE members SET joined_year = YEAR(created_at)');
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('joined_year');
        });
    }
};
