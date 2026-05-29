<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Actions\Records;

use Asawl\LaravelSqliteManager\Actions\Security\ConnectionManager;
use Asawl\LaravelSqliteManager\Actions\Security\InputValidator;
use RuntimeException;

class ManageRecordAction
{
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly InputValidator $inputValidator,
        private readonly ListTablesAction $listTablesAction,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(string $table, string $connection, array $attributes): ?string
    {
        $columns = $this->writableColumns($table, $connection, $attributes, skipEmptyIntegerPrimaryKey: true);
        $pdo = $this->connectionManager->pdo($connection);

        if ($columns === []) {
            $pdo->exec('INSERT INTO '.$this->inputValidator->quoteIdentifier($table).' DEFAULT VALUES');

            return $pdo->lastInsertId() ?: null;
        }

        $sql = 'INSERT INTO '.$this->inputValidator->quoteIdentifier($table)
            .' ('.$this->columnList(array_keys($columns)).') VALUES ('.$this->placeholderList(array_keys($columns)).')';

        $statement = $pdo->prepare($sql);
        $statement->execute($columns);

        return $pdo->lastInsertId() ?: null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $table, string $connection, string $key, array $attributes): void
    {
        $this->listTablesAction->columns($table, $connection);
        $primaryKey = $this->requiredPrimaryKey($table, $connection);
        $columns = $this->writableColumns($table, $connection, $attributes);
        unset($columns[$primaryKey]);

        if ($columns === []) {
            return;
        }

        $assignments = implode(', ', array_map(
            fn (string $column): string => $this->inputValidator->quoteIdentifier($column).' = :'.$column,
            array_keys($columns),
        ));

        $statement = $this->connectionManager->pdo($connection)->prepare(
            'UPDATE '.$this->inputValidator->quoteIdentifier($table).' SET '.$assignments.' WHERE '.$this->inputValidator->quoteIdentifier($primaryKey).' = :__key'
        );
        $statement->execute([...$columns, '__key' => $key]);
    }

    public function delete(string $table, string $connection, string $key): void
    {
        $primaryKey = $this->requiredPrimaryKey($table, $connection);
        $statement = $this->connectionManager->pdo($connection)->prepare(
            'DELETE FROM '.$this->inputValidator->quoteIdentifier($table).' WHERE '.$this->inputValidator->quoteIdentifier($primaryKey).' = :key'
        );
        $statement->execute(['key' => $key]);
    }

    /** @param list<string> $keys */
    public function deleteMany(string $table, string $connection, array $keys): int
    {
        $primaryKey = $this->requiredPrimaryKey($table, $connection);
        $keys = $this->inputValidator->validatePrimaryKeys($keys);

        if ($keys === []) {
            return 0;
        }

        if (count($keys) > $this->maxDeleteRows()) {
            throw new RuntimeException('Bulk delete exceeds the configured row limit.');
        }

        $placeholders = [];
        $bindings = [];

        foreach ($keys as $index => $key) {
            $placeholder = 'key_'.$index;
            $placeholders[] = ':'.$placeholder;
            $bindings[$placeholder] = $key;
        }

        $statement = $this->connectionManager->pdo($connection)->prepare(
            'DELETE FROM '.$this->inputValidator->quoteIdentifier($table).' WHERE '.$this->inputValidator->quoteIdentifier($primaryKey).' IN ('.implode(', ', $placeholders).')'
        );
        $statement->execute($bindings);

        return $statement->rowCount();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function writableColumns(string $table, string $connection, array $attributes, bool $skipEmptyIntegerPrimaryKey = false): array
    {
        $columns = [];

        foreach ($this->listTablesAction->columns($table, $connection) as $column) {
            $name = $column['name'];

            if (! array_key_exists($name, $attributes)) {
                continue;
            }

            if ($skipEmptyIntegerPrimaryKey && $column['primary'] && $attributes[$name] === '' && str_contains(mb_strtoupper((string) $column['type']), 'INT')) {
                continue;
            }

            $columns[$name] = $this->normalizeValue($attributes[$name], $column['nullable']);
        }

        return $columns;
    }

    private function normalizeValue(mixed $value, bool $nullable): mixed
    {
        return $nullable && $value === '' ? null : $value;
    }

    private function requiredPrimaryKey(string $table, string $connection): string
    {
        $primaryKey = $this->listTablesAction->columns($table, $connection);

        $pk = null;

        foreach ($primaryKey as $column) {
            if ($column['primary']) {
                $pk = $column['name'];
                break;
            }
        }

        if ($pk === null) {
            throw new RuntimeException("The SQLite table does not have a single-column primary key: {$table}");
        }

        return $pk;
    }

    /** @param list<string> $columns */
    private function columnList(array $columns): string
    {
        return implode(', ', array_map($this->inputValidator->quoteIdentifier(...), $columns));
    }

    /** @param list<string> $columns */
    private function placeholderList(array $columns): string
    {
        return implode(', ', array_map(fn (string $column): string => ':'.$column, $columns));
    }

    private function maxDeleteRows(): int
    {
        $limit = config('sqlite-manager.security.limits.max_delete_rows', 100);

        return is_numeric($limit) ? max(1, (int) $limit) : 100;
    }
}
