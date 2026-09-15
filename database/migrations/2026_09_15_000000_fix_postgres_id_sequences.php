<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables whose "id" column is expected to auto-increment.
     */
    protected array $tables = [
        'advance_payments',
        'capital_deductions',
        'capital_transactions',
        'cash_flows',
        'failed_jobs',
        'jobs',
        'loans',
        'members',
        'migrations',
        'monthly_contributions',
        'monthly_interest_payments',
        'system_settings',
        'users',
    ];

    /**
     * Run the migrations.
     *
     * Environments whose Postgres schema was loaded from a MySQL dump (rather
     * than from these migrations) ended up with plain bigint/integer "id"
     * columns and no sequence, since AUTO_INCREMENT has no Postgres
     * equivalent in a raw dump. Attach a sequence to each one and fast-forward
     * it past the current max id so inserts that omit "id" keep working.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
                continue;
            }

            $hasDefault = DB::selectOne(
                "SELECT column_default FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = 'id'",
                [$table]
            )?->column_default;

            if ($hasDefault) {
                continue;
            }

            $sequence = "{$table}_id_seq";

            DB::statement("CREATE SEQUENCE IF NOT EXISTS \"{$sequence}\"");
            DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN id SET DEFAULT nextval('\"{$sequence}\"')");
            DB::statement("ALTER SEQUENCE \"{$sequence}\" OWNED BY \"{$table}\".id");
            DB::statement("SELECT setval('\"{$sequence}\"', COALESCE((SELECT MAX(id) FROM \"{$table}\"), 0) + 1, false)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id')) {
                continue;
            }

            DB::statement("ALTER TABLE \"{$table}\" ALTER COLUMN id DROP DEFAULT");
            DB::statement("DROP SEQUENCE IF EXISTS \"{$table}_id_seq\"");
        }
    }
};
