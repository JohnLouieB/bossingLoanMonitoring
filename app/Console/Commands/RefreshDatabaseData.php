<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefreshDatabaseData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:refresh-data 
                            {--seed : Run database seeders after refreshing}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh all database data except users table (truncates all tables except users and system tables)';

    /**
     * Tables to preserve (not truncate)
     *
     * @var array
     */
    protected $preservedTables = [
        'users',
        'password_reset_tokens',
        'sessions',
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will delete ALL data except users. Are you sure you want to continue?')) {
                $this->info('Operation cancelled.');
                return Command::FAILURE;
            }
        }

        $this->info('Starting database refresh...');
        $this->newLine();

        try {
            // Get all table names from the database
            $tables = $this->getAllTables();
            
            // Filter out preserved tables
            $tablesToTruncate = array_diff($tables, $this->preservedTables);

            if (empty($tablesToTruncate)) {
                $this->warn('No tables to truncate.');
                return Command::SUCCESS;
            }

            $this->info('Tables to be truncated:');
            foreach ($tablesToTruncate as $table) {
                $this->line("  - {$table}");
            }
            $this->newLine();

            $truncatedCount = $this->truncateTables($tablesToTruncate);

            $this->newLine();
            $this->info("Successfully truncated {$truncatedCount} table(s).");
            $this->newLine();

            // Run seeders if requested
            if ($this->option('seed')) {
                $this->info('Running database seeders...');
                $this->call('db:seed', ['--force' => true]);
            }

            $this->info('Database refresh completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * Truncate the given tables, working around each driver's foreign key rules.
     *
     * @param  array  $tables
     * @return int  Number of tables truncated
     */
    protected function truncateTables(array $tables): int
    {
        // Postgres refuses to truncate a table that another table references
        // unless every referencing table is truncated in the same statement,
        // so send them all at once rather than looping one by one.
        if (DB::getDriverName() === 'pgsql') {
            $quoted = collect($tables)
                ->map(fn ($table) => '"' . str_replace('"', '""', $table) . '"')
                ->implode(', ');

            DB::statement("TRUNCATE TABLE {$quoted} RESTART IDENTITY");

            foreach ($tables as $table) {
                $this->info("✓ Truncated: {$table}");
            }

            return count($tables);
        }

        $truncatedCount = 0;

        // MySQL/SQLite: drop the constraint checks so order doesn't matter.
        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                try {
                    DB::table($table)->truncate();
                    $truncatedCount++;
                    $this->info("✓ Truncated: {$table}");
                } catch (\Exception $e) {
                    $this->error("✗ Failed to truncate {$table}: " . $e->getMessage());
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $truncatedCount;
    }

    /**
     * Get all table names from the database.
     *
     * @return array
     */
    protected function getAllTables(): array
    {
        // Driver-agnostic listing; schemaQualified: false keeps bare table
        // names so they match the preserved list on Postgres too.
        return Schema::getTableListing(schemaQualified: false);
    }
}
