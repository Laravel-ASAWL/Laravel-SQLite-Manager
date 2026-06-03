<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Actions\Records;

use Asawl\LaravelSqliteManager\Actions\Security\ConnectionManager;
use Asawl\LaravelSqliteManager\Actions\Security\InputValidator;
use PDO;
use RuntimeException;

class BrowseRecordsAction
{
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly InputValidator $inputValidator,
        private readonly ListTablesAction $listTablesAction,
    ) {}

    /**
     * @param  list<array{column?: string, operator?: string, value?: mixed}>  $filters
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int, from: int, to: int, columns: list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>, primary_key: string|null}
     */
    public function browse(
        string $table,
        string $connection,
        int $page = 1,
        int $perPage = 25,
        ?string $search = null,
        array $filters = [],
        ?string $sortColumn = null,
        string $sortDirection = 'asc',
        bool $includeSoftDeleted = false,
    ): array {
        $columns = $this->listTablesAction->columns($table, $connection);
        $primaryKey = $this->primaryKeyFromColumns($columns);
        $search = $this->sanitizeSearch($search);
        $where = $this->buildWhereClause($columns, $search, $filters);
        $where = $this->applySoftDeleteWhere($columns, $includeSoftDeleted, $where);
        $page = max(1, $page);
        $perPage = max(1, min($this->maxPageSize(), $perPage));
        $total = $this->countMatching($table, $connection, $where['sql'], $where['bindings']);
        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT * FROM '.$this->inputValidator->quoteIdentifier($table).$where['sql'];
        $sql .= $this->buildOrderBy($columns, $primaryKey, $sortColumn, $this->inputValidator->validateSortDirection($sortDirection));
        $sql .= ' LIMIT :limit OFFSET :offset';

        $statement = $this->connectionManager->pdo($connection)->prepare($sql);

        foreach ($where['bindings'] as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $fetched */
        $fetched = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'rows' => $this->sanitizeRows($fetched),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $total === 0 ? 0 : max(1, (int) ceil($total / $perPage)),
            'from' => $total === 0 ? 0 : $offset + 1,
            'to' => min($total, $offset + count($fetched)),
            'columns' => $columns,
            'primary_key' => $primaryKey,
        ];
    }

    /**
     * @param  list<array{column?: string, operator?: string, value?: mixed}>  $filters
     * @param  list<string>  $selectedKeys
     * @return list<array<string, mixed>>
     */
    public function exportRows(
        string $table,
        string $connection,
        ?string $search = null,
        array $filters = [],
        array $selectedKeys = [],
        ?string $sortColumn = null,
        string $sortDirection = 'asc',
        bool $includeSoftDeleted = false,
    ): array {
        $columns = $this->listTablesAction->columns($table, $connection);
        $primaryKey = $this->primaryKeyFromColumns($columns);
        $where = $this->applySoftDeleteWhere($columns, $includeSoftDeleted, $this->buildWhereClause($columns, $this->sanitizeSearch($search), $filters));
        $bindings = $where['bindings'];
        $clauses = $where['clauses'];

        if ($selectedKeys !== [] && $primaryKey !== null) {
            $placeholders = [];

            foreach (array_values($selectedKeys) as $index => $key) {
                $placeholder = 'selected_'.$index;
                $placeholders[] = ':'.$placeholder;
                $bindings[$placeholder] = $key;
            }

            $clauses[] = $this->inputValidator->quoteIdentifier($primaryKey).' IN ('.implode(', ', $placeholders).')';
        }

        $limit = $this->exportLimit();
        $sql = 'SELECT * FROM '.$this->inputValidator->quoteIdentifier($table).$this->whereSql($clauses)
            .$this->buildOrderBy($columns, $primaryKey, $sortColumn, $this->inputValidator->validateSortDirection($sortDirection)).' LIMIT :limit';
        $statement = $this->connectionManager->pdo($connection)->prepare($sql);

        foreach ($bindings as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        /** @var list<array<string, mixed>> $fetched */
        $fetched = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $this->sanitizeRows($fetched);
    }

    public function primaryKey(string $table, string $connection): ?string
    {
        $primaryColumns = array_values(array_filter(
            $this->listTablesAction->columns($table, $connection),
            fn (array $column): bool => $column['primary'] === true,
        ));

        return count($primaryColumns) === 1 ? $primaryColumns[0]['name'] : null;
    }

    /** @return array<string, mixed>|null */
    public function find(string $table, string $connection, string $key): ?array
    {
        $pk = $this->primaryKey($table, $connection);

        if ($pk === null) {
            throw new RuntimeException("The SQLite table does not have a single-column primary key: {$table}");
        }

        $statement = $this->connectionManager->pdo($connection)->prepare(
            'SELECT * FROM '.$this->inputValidator->quoteIdentifier($table).' WHERE '.$this->inputValidator->quoteIdentifier($pk).' = :key LIMIT 1'
        );
        $statement->execute(['key' => $key]);

        /** @var array<string, mixed>|false $record */
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($record) ? $this->sanitizeRow($record) : null;
    }

    /**
     * @param  list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>  $columns
     */
    private function primaryKeyFromColumns(array $columns): ?string
    {
        $primaryColumns = array_values(array_filter(
            $columns,
            fn (array $column): bool => $column['primary'] === true,
        ));

        return count($primaryColumns) === 1 ? $primaryColumns[0]['name'] : null;
    }

    private function sanitizeSearch(?string $search): ?string
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
    private function buildWhereClause(array $columns, ?string $search, array $filters): array
    {
        $clauses = [];
        $bindings = [];

        if ($search !== null) {
            $searchResult = $this->searchWhere($columns, $search);
            $clauses = [...$clauses, ...$searchResult['clauses']];
            $bindings = [...$bindings, ...$searchResult['bindings']];
        }

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
        $needle = '%'.$this->inputValidator->escapeLike($search).'%';

        foreach ($columns as $index => $column) {
            $placeholder = 'search_'.$index;
            $conditions[] = 'CAST('.$this->inputValidator->quoteIdentifier($column['name']).' AS TEXT) LIKE :'.$placeholder." ESCAPE '\\'";
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

        foreach ($filters as $index => $filter) {
            $column = $filter['column'] ?? null;
            $operator = $filter['operator'] ?? 'contains';
            $value = $filter['value'] ?? null;
            if (! is_string($column)) {
                continue;
            }
            if (! in_array($column, $available, true)) {
                continue;
            }
            if (! is_string($operator)) {
                continue;
            }

            $quotedColumn = $this->inputValidator->quoteIdentifier($column);
            $placeholder = 'filter_'.$index;

            if (in_array($operator, ['is_null', 'is_not_null'], true)) {
                $where[] = ['sql' => $quotedColumn.' IS '.($operator === 'is_null' ? '' : 'NOT ').'NULL', 'bindings' => []];

                continue;
            }
            if (! is_scalar($value)) {
                continue;
            }
            if ((string) $value === '') {
                continue;
            }

            $value = (string) $value;
            $bindings = [$placeholder => $value];
            $castColumn = 'CAST('.$quotedColumn.' AS TEXT)';

            $where[] = match ($operator) {
                'equals' => ['sql' => $quotedColumn.' = :'.$placeholder, 'bindings' => $bindings],
                'not_equals' => ['sql' => $quotedColumn.' != :'.$placeholder, 'bindings' => $bindings],
                'gt' => ['sql' => $quotedColumn.' > :'.$placeholder, 'bindings' => $bindings],
                'gte' => ['sql' => $quotedColumn.' >= :'.$placeholder, 'bindings' => $bindings],
                'lt' => ['sql' => $quotedColumn.' < :'.$placeholder, 'bindings' => $bindings],
                'lte' => ['sql' => $quotedColumn.' <= :'.$placeholder, 'bindings' => $bindings],
                'starts_with' => ['sql' => $castColumn." LIKE :{$placeholder} ESCAPE '\\'", 'bindings' => [$placeholder => $this->inputValidator->escapeLike($value).'%']],
                'ends_with' => ['sql' => $castColumn." LIKE :{$placeholder} ESCAPE '\\'", 'bindings' => [$placeholder => '%'.$this->inputValidator->escapeLike($value)]],
                default => ['sql' => $castColumn." LIKE :{$placeholder} ESCAPE '\\'", 'bindings' => [$placeholder => '%'.$this->inputValidator->escapeLike($value).'%']],
            };
        }

        return $where;
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

        $where['clauses'][] = $this->inputValidator->quoteIdentifier('deleted_at').' IS NULL';
        $where['sql'] = $this->whereSql($where['clauses']);

        return $where;
    }

    /** @param list<string> $clauses */
    private function whereSql(array $clauses): string
    {
        return $clauses === [] ? '' : ' WHERE '.implode(' AND ', $clauses);
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
    private function buildOrderBy(array $columns, ?string $primaryKey, ?string $sortColumn, string $sortDirection): string
    {
        $available = array_map(fn (array $column): string => $column['name'], $columns);
        $column = is_string($sortColumn) && in_array($sortColumn, $available, true) ? $sortColumn : $primaryKey;

        if ($column === null) {
            return '';
        }

        return ' ORDER BY '.$this->inputValidator->quoteIdentifier($column).' '.$sortDirection;
    }

    /**
     * @param  array<string, mixed>  $bindings
     */
    private function countMatching(string $table, string $connection, string $whereSql, array $bindings): int
    {
        $this->connectionManager->assertTableExists($table, $connection);

        $statement = $this->connectionManager->pdo($connection)->prepare('SELECT COUNT(*) FROM '.$this->inputValidator->quoteIdentifier($table).$whereSql);

        foreach ($bindings as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    private function exportLimit(): int
    {
        $limit = config('sqlite-manager.exports.max_rows', 5000);

        return is_numeric($limit) ? max(1, (int) $limit) : 5000;
    }

    private function maxPageSize(): int
    {
        $limit = config('sqlite-manager.security.limits.max_page_size', 100);

        return is_numeric($limit) ? max(1, (int) $limit) : 100;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function sanitizeRow(array $row): array
    {
        $record = [];

        foreach ($row as $key => $value) {
            if (is_string($key)) {
                $record[$key] = $value;
            }
        }

        return $record;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sanitizeRows(array $rows): array
    {
        return array_map($this->sanitizeRow(...), $rows);
    }
}
