<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager;

use Illuminate\Filesystem\Filesystem;
use PDO;
use RuntimeException;

class SQLiteDatabaseRepository
{
    public function __construct(
        private readonly SQLiteDatabaseManager $sqLiteDatabaseManager,
        private readonly Filesystem $filesystem,
    ) {}

    public function databasePath(): string
    {
        $path = config('sqlite-manager.database_path', database_path('database.sqlite'));

        return $this->sqLiteDatabaseManager->resolvePath(is_string($path) ? $path : database_path('database.sqlite'));
    }

    public function databaseExists(): bool
    {
        return $this->filesystem->exists($this->databasePath());
    }

    /**
     * @return list<array{name: string, rows: int, columns: int}>
     */
    public function tableSummaries(bool $includeLaravelTables = false): array
    {
        return array_map(fn (string $table): array => [
            'name' => $table,
            'rows' => $this->count($table),
            'columns' => count($this->columns($table)),
        ], $this->tables($includeLaravelTables));
    }

    /**
     * @return list<string>
     */
    public function tables(bool $includeLaravelTables = true): array
    {
        $statement = $this->pdo()->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

        if ($statement === false) {
            return [];
        }

        $tables = $statement->fetchAll(PDO::FETCH_COLUMN);

        $tables = array_values(array_filter($tables, is_string(...)));

        if ($includeLaravelTables) {
            return $tables;
        }

        return array_values(array_filter(
            $tables,
            fn (string $table): bool => ! $this->isLaravelTable($table),
        ));
    }

    /**
     * @return list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>
     */
    public function columns(string $table): array
    {
        $this->assertTableExists($table);

        $statement = $this->pdo()->query('PRAGMA table_info('.$this->quoteIdentifier($table).')');

        if ($statement === false) {
            return [];
        }

        $columns = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $column) {
            if (! is_array($column)) {
                continue;
            }
            if (! isset($column['name'])) {
                continue;
            }
            if (! is_string($column['name'])) {
                continue;
            }
            $type = $column['type'] ?? '';
            $notNull = $this->integer($column['notnull'] ?? 0);
            $primary = $this->integer($column['pk'] ?? 0);

            $columns[] = [
                'name' => $column['name'],
                'type' => is_string($type) ? $type : '',
                'nullable' => $notNull === 0,
                'default' => $column['dflt_value'] ?? null,
                'primary' => $primary > 0,
            ];
        }

        return $columns;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int, columns: list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>, primary_key: string|null}
     */
    public function records(string $table, int $page = 1, int $perPage = 25, ?string $search = null): array
    {
        $columns = $this->columns($table);
        $primaryKey = $this->primaryKey($table);
        $search = $this->normalizeSearch($search);
        $where = $this->searchWhere($columns, $search);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $total = $this->countMatching($table, $where['sql'], $where['bindings']);
        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT * FROM '.$this->quoteIdentifier($table).$where['sql'];

        if ($primaryKey !== null) {
            $sql .= ' ORDER BY '.$this->quoteIdentifier($primaryKey);
        }

        $sql .= ' LIMIT :limit OFFSET :offset';

        $statement = $this->pdo()->prepare($sql);

        foreach ($where['bindings'] as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'rows' => $this->rows($statement->fetchAll(PDO::FETCH_ASSOC)),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'columns' => $columns,
            'primary_key' => $primaryKey,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function insert(string $table, array $attributes): void
    {
        $columns = $this->writableColumns($table, $attributes, skipEmptyIntegerPrimaryKey: true);

        if ($columns === []) {
            $this->pdo()->exec('INSERT INTO '.$this->quoteIdentifier($table).' DEFAULT VALUES');

            return;
        }

        $sql = 'INSERT INTO '.$this->quoteIdentifier($table)
            .' ('.$this->columnList(array_keys($columns)).') VALUES ('.$this->placeholderList(array_keys($columns)).')';

        $statement = $this->pdo()->prepare($sql);
        $statement->execute($columns);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $table, string $key): ?array
    {
        $primaryKey = $this->requiredPrimaryKey($table);
        $statement = $this->pdo()->prepare(
            'SELECT * FROM '.$this->quoteIdentifier($table).' WHERE '.$this->quoteIdentifier($primaryKey).' = :key LIMIT 1'
        );
        $statement->execute(['key' => $key]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $this->row($record);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(string $table, string $key, array $attributes): void
    {
        $primaryKey = $this->requiredPrimaryKey($table);
        $columns = $this->writableColumns($table, $attributes);
        unset($columns[$primaryKey]);

        if ($columns === []) {
            return;
        }

        $assignments = implode(', ', array_map(
            fn (string $column): string => $this->quoteIdentifier($column).' = :'.$column,
            array_keys($columns),
        ));

        $statement = $this->pdo()->prepare(
            'UPDATE '.$this->quoteIdentifier($table).' SET '.$assignments.' WHERE '.$this->quoteIdentifier($primaryKey).' = :__key'
        );
        $statement->execute([...$columns, '__key' => $key]);
    }

    public function delete(string $table, string $key): void
    {
        $primaryKey = $this->requiredPrimaryKey($table);
        $statement = $this->pdo()->prepare(
            'DELETE FROM '.$this->quoteIdentifier($table).' WHERE '.$this->quoteIdentifier($primaryKey).' = :key'
        );
        $statement->execute(['key' => $key]);
    }

    public function primaryKey(string $table): ?string
    {
        $primaryColumns = array_values(array_filter(
            $this->columns($table),
            fn (array $column): bool => $column['primary'] === true,
        ));

        return count($primaryColumns) === 1 ? $primaryColumns[0]['name'] : null;
    }

    public function count(string $table): int
    {
        $this->assertTableExists($table);
        $statement = $this->pdo()->query('SELECT COUNT(*) FROM '.$this->quoteIdentifier($table));

        if ($statement === false) {
            return 0;
        }

        return (int) $statement->fetchColumn();
    }

    /**
     * @param  array<string, string>  $bindings
     */
    private function countMatching(string $table, string $whereSql, array $bindings): int
    {
        $this->assertTableExists($table);
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM '.$this->quoteIdentifier($table).$whereSql);

        foreach ($bindings as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    private function pdo(): PDO
    {
        if (! $this->databaseExists()) {
            throw new RuntimeException('The SQLite database file does not exist.');
        }

        $pdo = new PDO('sqlite:'.$this->databasePath());
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    private function assertTableExists(string $table): void
    {
        if (! in_array($table, $this->tables(), true)) {
            throw new RuntimeException("The SQLite table does not exist: {$table}");
        }
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

    /** @return list<string> */
    private function laravelTablePatterns(): array
    {
        $patterns = config('sqlite-manager.tables.laravel_table_patterns', []);

        if (! is_array($patterns)) {
            return [];
        }

        return array_values(array_filter($patterns, is_string(...)));
    }

    private function requiredPrimaryKey(string $table): string
    {
        $primaryKey = $this->primaryKey($table);

        if ($primaryKey === null) {
            throw new RuntimeException("The SQLite table does not have a single-column primary key: {$table}");
        }

        return $primaryKey;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function writableColumns(string $table, array $attributes, bool $skipEmptyIntegerPrimaryKey = false): array
    {
        $columns = [];

        foreach ($this->columns($table) as $column) {
            $name = $column['name'];

            if (! array_key_exists($name, $attributes)) {
                continue;
            }

            if ($skipEmptyIntegerPrimaryKey && $column['primary'] && $attributes[$name] === '' && str_contains(mb_strtoupper($column['type']), 'INT')) {
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

    private function normalizeSearch(?string $search): ?string
    {
        if ($search === null) {
            return null;
        }

        $search = trim($search);

        return $search === '' ? null : $search;
    }

    /**
     * @param  list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>  $columns
     * @return array{sql: string, bindings: array<string, string>}
     */
    private function searchWhere(array $columns, ?string $search): array
    {
        if ($search === null) {
            return ['sql' => '', 'bindings' => []];
        }

        $conditions = [];
        $bindings = [];
        $needle = '%'.$this->escapeLike($search).'%';

        foreach ($columns as $index => $column) {
            $placeholder = 'search_'.$index;
            $conditions[] = 'CAST('.$this->quoteIdentifier($column['name']).' AS TEXT) LIKE :'.$placeholder." ESCAPE '\\'";
            $bindings[$placeholder] = $needle;
        }

        return [
            'sql' => ' WHERE ('.implode(' OR ', $conditions).')',
            'bindings' => $bindings,
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $records = [];

        foreach ($rows as $row) {
            $row = $this->row($row);

            if ($row !== null) {
                $records[] = $row;
            }
        }

        return $records;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(mixed $row): ?array
    {
        if (! is_array($row)) {
            return null;
        }

        $record = [];

        foreach ($row as $key => $value) {
            if (is_string($key)) {
                $record[$key] = $value;
            }
        }

        return $record;
    }

    private function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * @param  list<string>  $columns
     */
    private function columnList(array $columns): string
    {
        return implode(', ', array_map($this->quoteIdentifier(...), $columns));
    }

    /**
     * @param  list<string>  $columns
     */
    private function placeholderList(array $columns): string
    {
        return implode(', ', array_map(fn (string $column): string => ':'.$column, $columns));
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
