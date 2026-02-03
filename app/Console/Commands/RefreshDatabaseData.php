<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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

            // Disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            // Truncate each table
            $truncatedCount = 0;
            foreach ($tablesToTruncate as $table) {
                try {
                    DB::table($table)->truncate();
                    $truncatedCount++;
                    $this->info("✓ Truncated: {$table}");
                } catch (\Exception $e) {
                    $this->error("✗ Failed to truncate {$table}: " . $e->getMessage());
                }
            }

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');

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
            // Re-enable foreign key checks in case of error
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            $this->error('An error occurred: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    /**
     * Get all table names from the database.
     *
     * @return array
     */
    protected function getAllTables(): array
    {
        $databaseName = DB::getDatabaseName();
        
        // For MySQL/MariaDB
        $tables = DB::select("SHOW TABLES");
        
        $tableNames = [];
        $key = "Tables_in_{$databaseName}";
        
        foreach ($tables as $table) {
            $tableNames[] = $table->$key;
        }

        return $tableNames;
    }
}
