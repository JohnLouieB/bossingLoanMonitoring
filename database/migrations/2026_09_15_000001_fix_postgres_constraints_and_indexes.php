<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Primary keys the dump-imported schema is missing: [table, columns, constraint name].
     */
    protected array $primaryKeys = [
        ['users', ['id'], 'users_pkey'],
        ['password_reset_tokens', ['email'], 'password_reset_tokens_pkey'],
        ['sessions', ['id'], 'sessions_pkey'],
        ['cache', ['key'], 'cache_pkey'],
        ['cache_locks', ['key'], 'cache_locks_pkey'],
        ['jobs', ['id'], 'jobs_pkey'],
        ['job_batches', ['id'], 'job_batches_pkey'],
        ['failed_jobs', ['id'], 'failed_jobs_pkey'],
        ['members', ['id'], 'members_pkey'],
        ['loans', ['id'], 'loans_pkey'],
        ['monthly_interest_payments', ['id'], 'monthly_interest_payments_pkey'],
        ['advance_payments', ['id'], 'advance_payments_pkey'],
        ['monthly_contributions', ['id'], 'monthly_contributions_pkey'],
        ['capital_transactions', ['id'], 'capital_transactions_pkey'],
        ['cash_flows', ['id'], 'cash_flows_pkey'],
        ['capital_deductions', ['id'], 'capital_deductions_pkey'],
        ['system_settings', ['id'], 'system_settings_pkey'],
        ['migrations', ['id'], 'migrations_pkey'],
    ];

    /**
     * Unique constraints: [table, columns, constraint name].
     */
    protected array $uniques = [
        ['users', ['email'], 'users_email_unique'],
        ['failed_jobs', ['uuid'], 'failed_jobs_uuid_unique'],
        ['monthly_interest_payments', ['loan_id', 'month', 'year'], 'monthly_interest_payments_loan_id_month_year_unique'],
        ['monthly_contributions', ['member_id', 'month', 'year'], 'member_month_year_unique'],
        ['cash_flows', ['year'], 'cash_flows_year_unique'],
    ];

    /**
     * Foreign keys: [table, column, ref table, ref column, on delete, constraint name].
     */
    protected array $foreignKeys = [
        ['loans', 'member_id', 'members', 'id', 'CASCADE', 'loans_member_id_foreign'],
        ['monthly_interest_payments', 'loan_id', 'loans', 'id', 'CASCADE', 'monthly_interest_payments_loan_id_foreign'],
        ['advance_payments', 'loan_id', 'loans', 'id', 'CASCADE', 'advance_payments_loan_id_foreign'],
        ['monthly_contributions', 'member_id', 'members', 'id', 'CASCADE', 'monthly_contributions_member_id_foreign'],
        ['capital_transactions', 'loan_id', 'loans', 'id', 'SET NULL', 'capital_transactions_loan_id_foreign'],
        ['capital_deductions', 'user_id', 'users', 'id', 'SET NULL', 'capital_deductions_user_id_foreign'],
    ];

    /**
     * Plain (non-unique) indexes: [table, columns, index name].
     */
    protected array $indexes = [
        ['sessions', ['user_id'], 'sessions_user_id_index'],
        ['sessions', ['last_activity'], 'sessions_last_activity_index'],
        ['cache', ['expiration'], 'cache_expiration_index'],
        ['cache_locks', ['expiration'], 'cache_locks_expiration_index'],
        ['jobs', ['queue'], 'jobs_queue_index'],
        ['cash_flows', ['year'], 'cash_flows_year_index'],
        ['capital_transactions', ['year'], 'capital_transactions_year_index'],
        ['capital_deductions', ['year', 'month'], 'capital_deductions_year_month_index'],
    ];

    /**
     * Run the migrations.
     *
     * The Postgres schema loaded from a MySQL dump came in with no primary
     * keys, foreign keys, unique constraints, or indexes at all - a plain
     * dump/restore doesn't carry those over the way a proper migration does.
     * Everything here is guarded by an existence check against the catalog,
     * so this is a no-op on a database that already has them (e.g. one built
     * by running the migrations directly).
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->primaryKeys as [$table, $columns, $name]) {
            if (! Schema::hasTable($table) || $this->constraintExists($name)) {
                continue;
            }

            $cols = $this->quoteList($columns);
            DB::statement("ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$name}\" PRIMARY KEY ({$cols})");
        }

        foreach ($this->uniques as [$table, $columns, $name]) {
            if (! Schema::hasTable($table) || $this->constraintExists($name)) {
                continue;
            }

            $cols = $this->quoteList($columns);
            DB::statement("ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$name}\" UNIQUE ({$cols})");
        }

        foreach ($this->foreignKeys as [$table, $column, $refTable, $refColumn, $onDelete, $name]) {
            if (! Schema::hasTable($table) || ! Schema::hasTable($refTable) || $this->constraintExists($name)) {
                continue;
            }

            DB::statement("ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$name}\" FOREIGN KEY (\"{$column}\") REFERENCES \"{$refTable}\" (\"{$refColumn}\") ON DELETE {$onDelete}");
        }

        foreach ($this->indexes as [$table, $columns, $name]) {
            if (! Schema::hasTable($table) || $this->indexExists($name)) {
                continue;
            }

            $cols = $this->quoteList($columns);
            DB::statement("CREATE INDEX \"{$name}\" ON \"{$table}\" ({$cols})");
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

        foreach ($this->indexes as [$table, $columns, $name]) {
            DB::statement("DROP INDEX IF EXISTS \"{$name}\"");
        }

        foreach (array_merge($this->foreignKeys, $this->uniques, $this->primaryKeys) as $definition) {
            $table = $definition[0];
            $name = end($definition);
            DB::statement("ALTER TABLE IF EXISTS \"{$table}\" DROP CONSTRAINT IF EXISTS \"{$name}\"");
        }
    }

    protected function quoteList(array $columns): string
    {
        return collect($columns)->map(fn ($c) => "\"{$c}\"")->implode(', ');
    }

    protected function constraintExists(string $name): bool
    {
        return (bool) DB::selectOne(
            "SELECT 1 FROM pg_constraint WHERE conname = ?",
            [$name]
        );
    }

    protected function indexExists(string $name): bool
    {
        return (bool) DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE schemaname = 'public' AND indexname = ?",
            [$name]
        );
    }
};
