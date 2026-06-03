<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Actions\Schema;

use Asawl\LaravelSqliteManager\Actions\Records\ListTablesAction;
use Asawl\LaravelSqliteManager\Actions\Security\ConnectionManager;
use Asawl\LaravelSqliteManager\Actions\Security\InputValidator;
use PDO;

class InspectTableAction
{
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly InputValidator $inputValidator,
        private readonly ListTablesAction $listTablesAction,
    ) {}

    /**
     * @return array{columns: list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>, indexes: list<array{name: string, unique: bool, columns: list<string>}>, foreign_keys: list<array{column: string, table: string, foreign_column: string}>}
     */
    public function inspect(string $table, string $connection): array
    {
        return [
            'columns' => $this->columns($table, $connection),
            'indexes' => $this->indexes($table, $connection),
            'foreign_keys' => $this->foreignKeys($table, $connection),
        ];
    }

    /** @return list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}> */
    public function columns(string $table, string $connection): array
    {
        return $this->listTablesAction->columns($table, $connection);
    }

    /** @return list<array{name: string, unique: bool, columns: list<string>}> */
    public function indexes(string $table, string $connection): array
    {
        $this->connectionManager->assertTableExists($table, $connection);

        $statement = $this->connectionManager->pdo($connection)->query('PRAGMA index_list('.$this->inputValidator->quoteIdentifier($table).')');

        if ($statement === false) {
            return [];
        }

        $indexes = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $index) {
            if (! is_array($index)) {
                continue;
            }
            if (! is_string($index['name'] ?? null)) {
                continue;
            }
            $columns = [];
            $columnStatement = $this->connectionManager->pdo($connection)->query('PRAGMA index_info('.$this->inputValidator->quoteIdentifier($index['name']).')');

            if ($columnStatement !== false) {
                foreach ($columnStatement->fetchAll(PDO::FETCH_ASSOC) as $column) {
                    if (is_array($column) && is_string($column['name'] ?? null)) {
                        $columns[] = $column['name'];
                    }
                }
            }

            $indexes[] = [
                'name' => $index['name'],
                'unique' => is_numeric($index['unique'] ?? null) && (int) $index['unique'] === 1,
                'columns' => $columns,
            ];
        }

        return $indexes;
    }

    /** @return list<array{column: string, table: string, foreign_column: string}> */
    public function foreignKeys(string $table, string $connection): array
    {
        $this->connectionManager->assertTableExists($table, $connection);

        $statement = $this->connectionManager->pdo($connection)->query('PRAGMA foreign_key_list('.$this->inputValidator->quoteIdentifier($table).')');

        if ($statement === false) {
            return [];
        }

        $foreignKeys = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $foreignKey) {
            if (! is_array($foreignKey)) {
                continue;
            }
            if (! is_string($foreignKey['from'] ?? null)) {
                continue;
            }
            if (! is_string($foreignKey['table'] ?? null)) {
                continue;
            }
            if (! is_string($foreignKey['to'] ?? null)) {
                continue;
            }
            $foreignKeys[] = [
                'column' => $foreignKey['from'],
                'table' => $foreignKey['table'],
                'foreign_column' => $foreignKey['to'],
            ];
        }

        return $foreignKeys;
    }

    /** @return list<array{key: string, label: string}> */
    public function relationOptions(string $table, string $column, string $connection, int $limit = 100): array
    {
        $targetTable = $this->relationTable($table, $column, $connection);

        if ($targetTable === null) {
            return [];
        }

        $columns = $this->columns($targetTable, $connection);
        $primaryKey = null;

        foreach ($columns as $col) {
            if ($col['primary']) {
                $primaryKey = $col['name'];
                break;
            }
        }

        if ($primaryKey === null) {
            return [];
        }

        $statement = $this->connectionManager->pdo($connection)->prepare('SELECT * FROM '.$this->inputValidator->quoteIdentifier($targetTable).' ORDER BY '.$this->inputValidator->quoteIdentifier($primaryKey).' ASC LIMIT :limit');
        $statement->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $fetched */
        $fetched = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(
            fn (array $row): array => [
                'key' => is_scalar($row[$primaryKey] ?? null) ? (string) $row[$primaryKey] : '',
                'label' => $this->relationLabel($row, $primaryKey),
            ],
            $fetched,
        );
    }

    /** @return array{table: string, key: string}|null */
    public function relationTarget(string $table, string $column, string $connection, mixed $value): ?array
    {
        if (! is_scalar($value) || ! str_ends_with($column, '_id')) {
            return null;
        }

        $targetTable = $this->relationTable($table, $column, $connection);

        return $targetTable === null ? null : ['table' => $targetTable, 'key' => (string) $value];
    }

    private function relationTable(string $table, string $column, string $connection): ?string
    {
        if (! str_ends_with($column, '_id')) {
            return null;
        }

        foreach ($this->foreignKeys($table, $connection) as $foreignKey) {
            if ($foreignKey['column'] === $column && $foreignKey['table'] !== $table) {
                return $foreignKey['table'];
            }
        }

        $base = mb_substr($column, 0, -3);
        $candidates = array_values(array_unique([$base.'s', $base.'es', $base]));
        $allTables = $this->connectionManager->fetchTableNames($connection);

        foreach ($candidates as $candidate) {
            if ($candidate === $table) {
                continue;
            }
            if (! in_array($candidate, $allTables, true)) {
                continue;
            }
            $cols = $this->columns($candidate, $connection);
            $hasPrimary = false;

            foreach ($cols as $col) {
                if ($col['primary']) {
                    $hasPrimary = true;
                    break;
                }
            }

            if (! $hasPrimary) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /** @param array<string, mixed> $row */
    private function relationLabel(array $row, string $primaryKey): string
    {
        $key = is_scalar($row[$primaryKey] ?? null) ? (string) $row[$primaryKey] : '';

        foreach (['name', 'title', 'email', 'label', 'display_name', 'slug'] as $column) {
            $value = $row[$column] ?? null;

            if (is_scalar($value) && (string) $value !== '') {
                return '#'.$key.' - '.$value;
            }
        }

        return '#'.$key;
    }
}
