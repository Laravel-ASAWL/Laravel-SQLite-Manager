<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager;

use Illuminate\Filesystem\Filesystem;
use PDO;
use RuntimeException;

class SQLiteManagerRepository
{
    public function __construct(
        private readonly SQLiteManager $SQLiteManager,
        private readonly Filesystem $filesystem,
    ) {}

    public function databasePath(): string
    {
        $connection = config('sqlite-manager.active_connection', 'default');
        $connections = config('sqlite-manager.connections', []);
        $path = is_string($connection) && $connection !== 'default' && is_array($connections) && array_key_exists($connection, $connections)
            ? $connections[$connection]
            : config('sqlite-manager.database_path', database_path('database.sqlite'));

        return $this->SQLiteManager->resolvePath(is_string($path) ? $path : database_path('database.sqlite'));
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
        $tables = array_values(array_filter($tables, $this->isAllowedTable(...)));

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
     * @param  list<array{column?: string, operator?: string, value?: mixed}>  $filters
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int, from: int, to: int, columns: list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>, primary_key: string|null}
     */
    public function records(string $table, int $page = 1, int $perPage = 25, ?string $search = null, array $filters = [], ?string $sortColumn = null, string $sortDirection = 'asc', bool $includeSoftDeleted = false): array
    {
        $columns = $this->columns($table);
        $primaryKey = $this->primaryKey($table);
        $search = $this->normalizeSearch($search);
        $where = $this->where($columns, $search, $filters);
        $where = $this->applySoftDeleteWhere($columns, $includeSoftDeleted, $where);
        $page = max(1, $page);
        $perPage = max(1, min($this->maxPageSize(), $perPage));
        $total = $this->countMatching($table, $where['sql'], $where['bindings']);
        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT * FROM '.$this->quoteIdentifier($table).$where['sql'];
        $sql .= $this->orderBy($columns, $primaryKey, $sortColumn, $sortDirection);

        $sql .= ' LIMIT :limit OFFSET :offset';

        $statement = $this->pdo()->prepare($sql);

        foreach ($where['bindings'] as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        $rows = $this->rows($statement->fetchAll(PDO::FETCH_ASSOC));

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'from' => $total === 0 ? 0 : $offset + 1,
            'to' => min($total, $offset + count($rows)),
            'columns' => $columns,
            'primary_key' => $primaryKey,
        ];
    }

    /**
     * @param  list<array{column?: string, operator?: string, value?: mixed}>  $filters
     * @param  list<string>  $selectedKeys
     * @return list<array<string, mixed>>
     */
    public function exportRows(string $table, ?string $search = null, array $filters = [], array $selectedKeys = [], ?string $sortColumn = null, string $sortDirection = 'asc', bool $includeSoftDeleted = false): array
    {
        $columns = $this->columns($table);
        $primaryKey = $this->primaryKey($table);
        $where = $this->applySoftDeleteWhere($columns, $includeSoftDeleted, $this->where($columns, $this->normalizeSearch($search), $filters));
        $bindings = $where['bindings'];
        $clauses = $where['clauses'];

        if ($selectedKeys !== [] && $primaryKey !== null) {
            $placeholders = [];

            foreach (array_values($selectedKeys) as $index => $key) {
                $placeholder = 'selected_'.$index;
                $placeholders[] = ':'.$placeholder;
                $bindings[$placeholder] = $key;
            }

            $clauses[] = $this->quoteIdentifier($primaryKey).' IN ('.implode(', ', $placeholders).')';
        }

        $limit = $this->exportLimit();
        $sql = 'SELECT * FROM '.$this->quoteIdentifier($table).$this->whereSql($clauses)
            .$this->orderBy($columns, $primaryKey, $sortColumn, $sortDirection).' LIMIT :limit';
        $statement = $this->pdo()->prepare($sql);

        foreach ($bindings as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $this->rows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function insert(string $table, array $attributes): ?string
    {
        $columns = $this->writableColumns($table, $attributes, skipEmptyIntegerPrimaryKey: true);
        $pdo = $this->pdo();

        if ($columns === []) {
            $pdo->exec('INSERT INTO '.$this->quoteIdentifier($table).' DEFAULT VALUES');

            return $pdo->lastInsertId() ?: null;
        }

        $sql = 'INSERT INTO '.$this->quoteIdentifier($table)
            .' ('.$this->columnList(array_keys($columns)).') VALUES ('.$this->placeholderList(array_keys($columns)).')';

        $statement = $pdo->prepare($sql);
        $statement->execute($columns);

        return $pdo->lastInsertId() ?: null;
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

    /** @param list<string> $keys */
    public function deleteMany(string $table, array $keys): int
    {
        $primaryKey = $this->requiredPrimaryKey($table);
        $keys = array_values(array_filter($keys, fn (string $key): bool => $key !== ''));

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

        $statement = $this->pdo()->prepare(
            'DELETE FROM '.$this->quoteIdentifier($table).' WHERE '.$this->quoteIdentifier($primaryKey).' IN ('.implode(', ', $placeholders).')'
        );
        $statement->execute($bindings);

        return $statement->rowCount();
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function audit(string $action, string $table, ?string $recordKey = null, ?array $before = null, ?array $after = null): void
    {
        if (! (bool) config('sqlite-manager.audit.enabled', false)) {
            return;
        }

        $auditTable = $this->auditTable();
        $pdo = $this->pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS '.$this->quoteIdentifier($auditTable).' (id INTEGER PRIMARY KEY AUTOINCREMENT, action TEXT NOT NULL, table_name TEXT NOT NULL, record_key TEXT NULL, before_values TEXT NULL, after_values TEXT NULL, created_at TEXT NOT NULL)');

        $statement = $pdo->prepare('INSERT INTO '.$this->quoteIdentifier($auditTable).' (action, table_name, record_key, before_values, after_values, created_at) VALUES (:action, :table_name, :record_key, :before_values, :after_values, :created_at)');
        $statement->execute([
            'action' => $action,
            'table_name' => $table,
            'record_key' => $recordKey,
            'before_values' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_values' => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    /** @return array{table: string, key: string}|null */
    public function relationTarget(string $table, string $column, mixed $value): ?array
    {
        if (! is_scalar($value) || ! str_ends_with($column, '_id')) {
            return null;
        }

        $targetTable = $this->relationTable($table, $column);

        return $targetTable === null ? null : ['table' => $targetTable, 'key' => (string) $value];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function relationOptions(string $table, string $column, int $limit = 100): array
    {
        $targetTable = $this->relationTable($table, $column);

        if ($targetTable === null) {
            return [];
        }

        $primaryKey = $this->primaryKey($targetTable);

        if ($primaryKey === null) {
            return [];
        }

        $statement = $this->pdo()->prepare('SELECT * FROM '.$this->quoteIdentifier($targetTable).' ORDER BY '.$this->quoteIdentifier($primaryKey).' ASC LIMIT :limit');
        $statement->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn (array $row): array => [
                'key' => (string) ($row[$primaryKey] ?? ''),
                'label' => $this->relationLabel($row, $primaryKey),
            ],
            $this->rows($statement->fetchAll(PDO::FETCH_ASSOC)),
        );
    }

    private function relationTable(string $table, string $column): ?string
    {
        if (! str_ends_with($column, '_id')) {
            return null;
        }

        foreach ($this->foreignKeys($table) as $foreignKey) {
            if ($foreignKey['column'] === $column && $foreignKey['table'] !== $table) {
                return $foreignKey['table'];
            }
        }

        $base = substr($column, 0, -3);
        $candidates = array_values(array_unique([$base.'s', $base.'es', $base]));

        foreach ($candidates as $candidate) {
            if ($candidate === $table || ! in_array($candidate, $this->tables(), true)) {
                continue;
            }

            if ($this->primaryKey($candidate) === null) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /** @param array<string, mixed> $row */
    private function relationLabel(array $row, string $primaryKey): string
    {
        $key = (string) ($row[$primaryKey] ?? '');

        foreach (['name', 'title', 'email', 'label', 'display_name', 'slug'] as $column) {
            $value = $row[$column] ?? null;

            if (is_scalar($value) && (string) $value !== '') {
                return '#'.$key.' - '.(string) $value;
            }
        }

        return '#'.$key;
    }

    /**
     * @return array{columns: list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>, indexes: list<array{name: string, unique: bool, columns: list<string>}>, foreign_keys: list<array{column: string, table: string, foreign_column: string}>}
     */
    public function schema(string $table): array
    {
        return [
            'columns' => $this->columns($table),
            'indexes' => $this->indexes($table),
            'foreign_keys' => $this->foreignKeys($table),
        ];
    }

    /** @return list<array{name: string, unique: bool, columns: list<string>}> */
    public function indexes(string $table): array
    {
        $this->assertTableExists($table);
        $statement = $this->pdo()->query('PRAGMA index_list('.$this->quoteIdentifier($table).')');

        if ($statement === false) {
            return [];
        }

        $indexes = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $index) {
            if (! is_array($index) || ! is_string($index['name'] ?? null)) {
                continue;
            }

            $columns = [];
            $columnStatement = $this->pdo()->query('PRAGMA index_info('.$this->quoteIdentifier($index['name']).')');

            if ($columnStatement !== false) {
                foreach ($columnStatement->fetchAll(PDO::FETCH_ASSOC) as $column) {
                    if (is_array($column) && is_string($column['name'] ?? null)) {
                        $columns[] = $column['name'];
                    }
                }
            }

            $indexes[] = [
                'name' => $index['name'],
                'unique' => $this->integer($index['unique'] ?? 0) === 1,
                'columns' => $columns,
            ];
        }

        return $indexes;
    }

    /** @return list<array{column: string, table: string, foreign_column: string}> */
    public function foreignKeys(string $table): array
    {
        $this->assertTableExists($table);
        $statement = $this->pdo()->query('PRAGMA foreign_key_list('.$this->quoteIdentifier($table).')');

        if ($statement === false) {
            return [];
        }

        $foreignKeys = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $foreignKey) {
            if (! is_array($foreignKey) || ! is_string($foreignKey['from'] ?? null) || ! is_string($foreignKey['table'] ?? null) || ! is_string($foreignKey['to'] ?? null)) {
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
     * @param  list<array{column?: string, operator?: string, value?: mixed}>  $filters
     * @return array{sql: string, clauses: list<string>, bindings: array<string, mixed>}
     */
    private function where(array $columns, ?string $search, array $filters): array
    {
        $clauses = [];
        $bindings = [];

        if ($search === null) {
            $searchWhere = ['clauses' => [], 'bindings' => []];
        } else {
            $searchWhere = $this->searchWhere($columns, $search);
        }

        $clauses = [...$clauses, ...$searchWhere['clauses']];
        $bindings = [...$bindings, ...$searchWhere['bindings']];

        foreach ($this->filterWhere($columns, $filters) as $filter) {
            $clauses[] = $filter['sql'];
            $bindings = [...$bindings, ...$filter['bindings']];
        }

        return ['sql' => $this->whereSql($clauses), 'clauses' => $clauses, 'bindings' => $bindings];
    }

    /**
     * @param  list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>  $columns
     * @return array{clauses: list<string>, bindings: array<string, string>}
     */
    private function searchWhere(array $columns, string $search): array
    {
        $conditions = [];
        $bindings = [];
        $needle = '%'.$this->escapeLike($search).'%';

        foreach ($columns as $index => $column) {
            $placeholder = 'search_'.$index;
            $conditions[] = 'CAST('.$this->quoteIdentifier($column['name']).' AS TEXT) LIKE :'.$placeholder." ESCAPE '\\'";
            $bindings[$placeholder] = $needle;
        }

        return [
            'clauses' => ['('.implode(' OR ', $conditions).')'],
            'bindings' => $bindings,
        ];
    }

    /**
     * @param  list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>  $columns
     * @param  list<array{column?: string, operator?: string, value?: mixed}>  $filters
     * @return list<array{sql: string, bindings: array<string, mixed>}>
     */
    private function filterWhere(array $columns, array $filters): array
    {
        $available = array_map(fn (array $column): string => $column['name'], $columns);
        $where = [];

        foreach (array_values($filters) as $index => $filter) {
            $column = $filter['column'] ?? null;
            $operator = $filter['operator'] ?? 'contains';
            $value = $filter['value'] ?? null;

            if (! is_string($column) || ! in_array($column, $available, true) || ! is_string($operator)) {
                continue;
            }

            $quotedColumn = $this->quoteIdentifier($column);
            $placeholder = 'filter_'.$index;

            if (in_array($operator, ['is_null', 'is_not_null'], true)) {
                $where[] = ['sql' => $quotedColumn.' IS '.($operator === 'is_null' ? '' : 'NOT ').'NULL', 'bindings' => []];

                continue;
            }

            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $value = (string) $value;

            if ($value === '') {
                continue;
            }

            $bindings = [$placeholder => $value];
            $castColumn = 'CAST('.$quotedColumn.' AS TEXT)';

            $where[] = match ($operator) {
                'equals' => ['sql' => $quotedColumn.' = :'.$placeholder, 'bindings' => $bindings],
                'not_equals' => ['sql' => $quotedColumn.' != :'.$placeholder, 'bindings' => $bindings],
                'gt' => ['sql' => $quotedColumn.' > :'.$placeholder, 'bindings' => $bindings],
                'gte' => ['sql' => $quotedColumn.' >= :'.$placeholder, 'bindings' => $bindings],
                'lt' => ['sql' => $quotedColumn.' < :'.$placeholder, 'bindings' => $bindings],
                'lte' => ['sql' => $quotedColumn.' <= :'.$placeholder, 'bindings' => $bindings],
                'starts_with' => ['sql' => $castColumn." LIKE :{$placeholder} ESCAPE '\\'", 'bindings' => [$placeholder => $this->escapeLike($value).'%']],
                'ends_with' => ['sql' => $castColumn." LIKE :{$placeholder} ESCAPE '\\'", 'bindings' => [$placeholder => '%'.$this->escapeLike($value)]],
                default => ['sql' => $castColumn." LIKE :{$placeholder} ESCAPE '\\'", 'bindings' => [$placeholder => '%'.$this->escapeLike($value).'%']],
            };
        }

        return $where;
    }

    /** @param list<string> $clauses */
    private function whereSql(array $clauses): string
    {
        return $clauses === [] ? '' : ' WHERE '.implode(' AND ', $clauses);
    }

    /**
     * @param  list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>  $columns
     * @param  array{sql: string, clauses: list<string>, bindings: array<string, mixed>}  $where
     * @return array{sql: string, clauses: list<string>, bindings: array<string, mixed>}
     */
    private function applySoftDeleteWhere(array $columns, bool $includeSoftDeleted, array $where): array
    {
        if ($includeSoftDeleted || ! $this->hasColumn($columns, 'deleted_at')) {
            return $where;
        }

        $where['clauses'][] = $this->quoteIdentifier('deleted_at').' IS NULL';
        $where['sql'] = $this->whereSql($where['clauses']);

        return $where;
    }

    /**
     * @param  list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>  $columns
     */
    private function hasColumn(array $columns, string $name): bool
    {
        foreach ($columns as $column) {
            if ($column['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>  $columns
     */
    private function orderBy(array $columns, ?string $primaryKey, ?string $sortColumn, string $sortDirection): string
    {
        $available = array_map(fn (array $column): string => $column['name'], $columns);
        $column = is_string($sortColumn) && in_array($sortColumn, $available, true) ? $sortColumn : $primaryKey;

        if ($column === null) {
            return '';
        }

        return ' ORDER BY '.$this->quoteIdentifier($column).' '.(mb_strtolower($sortDirection) === 'desc' ? 'DESC' : 'ASC');
    }

    private function exportLimit(): int
    {
        $limit = config('sqlite-manager.security.limits.max_export_rows', config('sqlite-manager.exports.max_rows', 5000));

        return is_numeric($limit) ? max(1, (int) $limit) : 5000;
    }

    private function maxDeleteRows(): int
    {
        $limit = config('sqlite-manager.security.limits.max_delete_rows', 100);

        return is_numeric($limit) ? max(1, (int) $limit) : 100;
    }

    private function maxPageSize(): int
    {
        $limit = config('sqlite-manager.security.limits.max_page_size', 100);

        return is_numeric($limit) ? max(1, (int) $limit) : 100;
    }

    private function auditTable(): string
    {
        $table = config('sqlite-manager.audit.table', '_lsm_audit_log');

        return is_string($table) && $table !== '' ? $table : '_lsm_audit_log';
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
