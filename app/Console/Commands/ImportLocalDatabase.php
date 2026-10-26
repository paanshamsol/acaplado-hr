<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Throwable;

class ImportLocalDatabase extends Command
{
    protected $signature = 'hr:import-local-db
                            {--source= : Path to the SQLite snapshot (defaults to database/database.sqlite)}
                            {--connection= : Target connection name (defaults to the app default connection)}
                            {--truncate : Empty every target table before inserting}
                            {--chunk=200 : Rows per insert statement}';

    protected $description = 'Copy all application data from a local SQLite snapshot into the target database';

    /**
     * Transient tables that must never be copied to another environment.
     */
    private const SKIP = [
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
    ];

    /**
     * Fallback insert order used when foreign keys cannot be inspected.
     */
    private const ORDER = [
        'users',
        'roles',
        'permissions',
        'company_settings',
        'company_operational_times',
        'leave_types',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
        'leave_balances',
        'leave_requests',
        'attendances',
        'salary_adjustments',
        'payslips',
        'notifications',
        'audit_logs',
    ];

    public function handle(): int
    {
        $sourcePath = $this->option('source') ?: database_path('database.sqlite');

        if (! is_file($sourcePath)) {
            $this->components->error("Source database not found at [{$sourcePath}].");

            return self::FAILURE;
        }

        $connectionName = $this->option('connection') ?: config('database.default');
        $target = DB::connection($connectionName);

        try {
            $target->getPdo();
        } catch (Throwable $e) {
            $this->components->error("Cannot connect to [{$connectionName}]: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->components->info("Source: {$sourcePath}");
        $this->components->info(sprintf(
            'Target: %s (%s)',
            $connectionName,
            $target->getDriverName(),
        ));

        $source = new PDO('sqlite:'.$sourcePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => true,
        ]);

        $tables = array_values(array_filter(
            $this->sourceTables($source),
            fn (string $table): bool => ! in_array($table, self::SKIP, true),
        ));

        $missing = array_values(array_filter(
            $tables,
            fn (string $table): bool => ! Schema::connection($connectionName)->hasTable($table),
        ));

        if ($missing !== []) {
            $this->components->error(sprintf(
                'The target database is missing %d table(s): %s',
                count($missing),
                implode(', ', $missing),
            ));
            $this->components->warn('Run `php artisan migrate --force` against the target first.');

            return self::FAILURE;
        }

        $order = $this->insertionOrder($connectionName, $tables);
        $chunk = max(1, (int) $this->option('chunk'));

        if ($this->option('truncate')) {
            foreach (array_reverse($order) as $table) {
                $target->table($table)->delete();
            }
        }

        $summary = [];

        $target->transaction(function () use ($source, $target, $order, $chunk, &$summary): void {
            foreach ($order as $table) {
                $rows = $source->query('select * from "'.$table.'"')->fetchAll();
                $summary[$table] = 0;

                foreach (array_chunk($rows, $chunk) as $batch) {
                    $target->table($table)->insert(array_map($this->normalize(...), $batch));
                    $summary[$table] += count($batch);
                }
            }
        });

        $this->resetSequences($target, $order);

        $this->table(
            ['Table', 'Rows copied'],
            collect($summary)->map(fn (int $rows, string $table): array => [$table, $rows])->values()->all(),
        );

        $this->components->info(sprintf(
            'Imported %d rows across %d tables.',
            array_sum($summary),
            count($summary),
        ));
        $this->components->warn('Skipped transient tables: '.implode(', ', self::SKIP));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function sourceTables(PDO $source): array
    {
        $statement = $source->query(
            "select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name"
        );

        return array_map(strval(...), array_column($statement->fetchAll(), 'name'));
    }

    /**
     * Order tables so that referenced rows exist before referencing rows.
     *
     * @param  array<int, string>  $tables
     * @return array<int, string>
     */
    private function insertionOrder(string $connectionName, array $tables): array
    {
        $schema = Schema::connection($connectionName);
        $dependencies = [];

        foreach ($tables as $table) {
            $dependencies[$table] = [];

            try {
                foreach ($schema->getForeignKeys($table) as $foreign) {
                    $parent = $foreign['foreign_table'] ?? null;

                    if ($parent !== null && $parent !== $table && in_array($parent, $tables, true)) {
                        $dependencies[$table][] = $parent;
                    }
                }
            } catch (Throwable) {
                return $this->fallbackOrder($tables);
            }
        }

        if (array_sum(array_map(count(...), $dependencies)) === 0) {
            return $this->fallbackOrder($tables);
        }

        $ordered = [];
        $remaining = $dependencies;

        while ($remaining !== []) {
            $ready = array_keys(array_filter($remaining, fn (array $deps): bool => $deps === []));

            if ($ready === []) {
                $ordered = array_merge($ordered, array_keys($remaining));

                break;
            }

            sort($ready);

            foreach ($ready as $table) {
                $ordered[] = $table;
                unset($remaining[$table]);
            }

            foreach (array_keys($remaining) as $table) {
                $remaining[$table] = array_values(array_diff($remaining[$table], $ready));
            }
        }

        return $ordered;
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<int, string>
     */
    private function fallbackOrder(array $tables): array
    {
        $rank = array_flip(self::ORDER);

        usort($tables, function (string $a, string $b) use ($rank): int {
            $position = [$rank[$a] ?? PHP_INT_MAX, $rank[$b] ?? PHP_INT_MAX];

            return $position[0] <=> $position[1] ?: strcmp($a, $b);
        });

        return $tables;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        foreach ($row as $column => $value) {
            if ($value === null || is_string($value)) {
                continue;
            }

            $row[$column] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }

        return $row;
    }

    /**
     * Inserting explicit primary keys leaves PostgreSQL identity sequences behind.
     *
     * @param  array<int, string>  $tables
     */
    private function resetSequences(ConnectionInterface $target, array $tables): void
    {
        if ($target->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tables as $table) {
            $sequence = $target->selectOne(
                "select pg_get_serial_sequence(?, 'id') as sequence",
                [$table],
            );

            if (empty($sequence->sequence)) {
                continue;
            }

            $target->statement(sprintf(
                'select setval(%s, coalesce((select max(id) from "%s"), 1), (select count(*) > 0 from "%s"))',
                $target->getPdo()->quote($sequence->sequence),
                $table,
                $table,
            ));
        }
    }
}
