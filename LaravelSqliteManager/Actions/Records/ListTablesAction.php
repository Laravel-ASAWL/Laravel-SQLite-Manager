<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Actions\Records;

use Asawl\LaravelSqliteManager\Actions\Security\ConnectionManager;
use Asawl\LaravelSqliteManager\Actions\Security\InputValidator;
use PDO;

class ListTablesAction
{
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly InputValidator $inputValidator,
    ) {}

    /** @return list<array{name: string, rows: int, columns: int}> */
    public function summaries(string $connection, bool $includeLaravelTables = false): array
    {
        return array_map(fn (string $table): array => [
            'name' => $table,
            'rows' => $this->count($table, $connection),
            'columns' => count($this->columns($table, $connection)),
        ], $this->all($connection, $includeLaravelTables));
    }

    /** @return list<string> */
    public function all(string $connection, bool $includeLaravelTables = false): array
    {
        $tables = $this->connectionManager->fetchTableNames($connection);

        $tables = array_values(array_filter($tables, $this->isAllowedTable(...)));

        if ($includeLaravelTables) {
            return $tables;
        }

        return array_values(array_filter(
            $tables,
            fn (string $table): bool => ! $this->isLaravelTable($table),
        ));
    }

    public function count(string $table, string $connection): int
    {
        $this->connectionManager->assertTableExists($table, $connection);

        $statement = $this->connectionManager->pdo($connection)->query('SELECT COUNT(*) FROM '.$this->inputValidator->quoteIdentifier($table));

        if ($statement === false) {
            return 0;
        }

        return (int) $statement->fetchColumn();
    }

    /** @return list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}> */
    public function columns(string $table, string $connection): array
    {
        $this->connectionManager->assertTableExists($table, $connection);

        $statement = $this->connectionManager->pdo($connection)->query('PRAGMA table_info('.$this->inputValidator->quoteIdentifier($table).')');

        if ($statement === false) {
            return [];
        }

        $columns = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if (! is_array($column)) {
                continue;
            }
            if (! is_string($column['name'] ?? null)) {
                continue;
            }
            $notnull = $column['notnull'] ?? 0;
            $pk = $column['pk'] ?? 0;

            $columns[] = [
                'name' => $column['name'],
                'type' => is_string($column['type'] ?? null) ? $column['type'] : '',
                'nullable' => is_numeric($notnull) ? ((int) $notnull) === 0 : true,
                'default' => $column['dflt_value'] ?? null,
                'primary' => is_numeric($pk) && (int) $pk > 0,
            ];
        }

        return $columns;
    }

    private function isLaravelTable(string $table): bool
    {
        foreach ($this->laravelTablePatterns() as $pattern) {
            $pattern = str_replace('\\*', '.*', preg_quote($pattern, '/'));

            if (preg_match('/^'.$pattern.'$/', $table) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isAllowedTable(string $table): bool
    {
        $allow = $this->configuredTablePatterns('allow');
        $deny = $this->configuredTablePatterns('deny');

        if ($allow !== [] && ! $this->matchesAnyPattern($table, $allow)) {
            return false;
        }

        return ! $this->matchesAnyPattern($table, $deny);
    }

    /** @return list<string> */
    private function configuredTablePatterns(string $key): array
    {
        $patterns = config('sqlite-manager.tables.'.$key, []);

        if (! is_array($patterns)) {
            return [];
        }

        return array_values(array_filter($patterns, is_string(...)));
    }

    /** @param list<string> $patterns */
    private function matchesAnyPattern(string $table, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = str_replace('\*', '.*', preg_quote($pattern, '/'));

            if (preg_match('/^'.$pattern.'$/', $table) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function laravelTablePatterns(): array
    {
        $patterns = config('sqlite-manager.tables.laravel_table_patterns', []);

        if (! is_array($patterns)) {
            return [];
        }

        return array_values(array_filter($patterns, is_string(...)));
    }
}
