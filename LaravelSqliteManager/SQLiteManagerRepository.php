<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager;

use Asawl\LaravelSqliteManager\Actions\Audit\LogAction;
use Asawl\LaravelSqliteManager\Actions\Records\BrowseRecordsAction;
use Asawl\LaravelSqliteManager\Actions\Records\ImportRecordsAction;
use Asawl\LaravelSqliteManager\Actions\Records\ListTablesAction;
use Asawl\LaravelSqliteManager\Actions\Records\ManageRecordAction;
use Asawl\LaravelSqliteManager\Actions\Schema\InspectTableAction;
use Asawl\LaravelSqliteManager\Actions\Security\ConnectionManager;
use Asawl\LaravelSqliteManager\Actions\Security\InputValidator;

class SQLiteManagerRepository
{
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly ListTablesAction $listTablesAction,
        private readonly BrowseRecordsAction $browseRecordsAction,
        private readonly ManageRecordAction $manageRecordAction,
        private readonly ImportRecordsAction $importRecordsAction,
        private readonly InspectTableAction $inspectTableAction,
        private readonly LogAction $logAction,
        private readonly InputValidator $inputValidator,
    ) {}

    public function databasePath(): string
    {
        return $this->connectionManager->databasePath();
    }

    /** @return list<array{name: string, rows: int, columns: int}> */
    public function tableSummaries(bool $includeLaravelTables = false): array
    {
        return $this->listTablesAction->summaries($this->connection(), $includeLaravelTables);
    }

    /** @return list<string> */
    public function tables(bool $includeLaravelTables = true): array
    {
        return $this->listTablesAction->all($this->connection(), $includeLaravelTables);
    }

    /** @return list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}> */
    public function columns(string $table): array
    {
        $this->inputValidator->validateTableName($table);

        return $this->listTablesAction->columns($table, $this->connection());
    }

    /**
     * @param  list<array{column?: string, operator?: string, value?: mixed}>  $filters
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int, from: int, to: int, columns: list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>, primary_key: string|null}
     */
    public function records(string $table, int $page = 1, int $perPage = 25, ?string $search = null, array $filters = [], ?string $sortColumn = null, string $sortDirection = 'asc', bool $includeSoftDeleted = false): array
    {
        $this->inputValidator->validateTableName($table);

        return $this->browseRecordsAction->browse(
            $table, $this->connection(), $page, $perPage,
            $search, $filters, $sortColumn, $sortDirection, $includeSoftDeleted,
        );
    }

    /**
     * @param  list<array{column?: string, operator?: string, value?: mixed}>  $filters
     * @param  list<string>  $selectedKeys
     * @return list<array<string, mixed>>
     */
    public function exportRows(string $table, ?string $search = null, array $filters = [], array $selectedKeys = [], ?string $sortColumn = null, string $sortDirection = 'asc', bool $includeSoftDeleted = false): array
    {
        $this->inputValidator->validateTableName($table);

        return $this->browseRecordsAction->exportRows(
            $table, $this->connection(), $search, $filters,
            $selectedKeys, $sortColumn, $sortDirection, $includeSoftDeleted,
        );
    }

    /** @return array<string, mixed>|null */
    public function find(string $table, string $key): ?array
    {
        $this->inputValidator->validateTableName($table);
        $this->inputValidator->validatePrimaryKey($key);

        return $this->browseRecordsAction->find($table, $this->connection(), $key);
    }

    /** @param array<string, mixed> $attributes */
    public function insert(string $table, array $attributes): ?string
    {
        $this->inputValidator->validateTableName($table);

        return $this->manageRecordAction->create($table, $this->connection(), $attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function update(string $table, string $key, array $attributes): void
    {
        $this->inputValidator->validateTableName($table);
        $this->inputValidator->validatePrimaryKey($key);

        $this->manageRecordAction->update($table, $this->connection(), $key, $attributes);
    }

    public function delete(string $table, string $key): void
    {
        $this->inputValidator->validateTableName($table);
        $this->inputValidator->validatePrimaryKey($key);

        $this->manageRecordAction->delete($table, $this->connection(), $key);
    }

    /** @param list<string> $keys */
    public function deleteMany(string $table, array $keys): int
    {
        $this->inputValidator->validateTableName($table);

        return $this->manageRecordAction->deleteMany($table, $this->connection(), $keys);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function audit(string $action, string $table, ?string $recordKey = null, ?array $before = null, ?array $after = null): void
    {
        $this->logAction->log($action, $table, $this->connection(), $recordKey, $before, $after);
    }

    /** @param list<array<string, mixed>> $rows */
    public function auditBatch(string $action, string $table, array $rows): void
    {
        $this->logAction->logBatch($action, $table, $this->connection(), $rows);
    }

    public function count(string $table): int
    {
        $this->inputValidator->validateTableName($table);

        return $this->listTablesAction->count($table, $this->connection());
    }

    /** @return array{table: string, key: string}|null */
    public function relationTarget(string $table, string $column, mixed $value): ?array
    {
        return $this->inspectTableAction->relationTarget($table, $column, $this->connection(), $value);
    }

    /** @return list<array{key: string, label: string}> */
    public function relationOptions(string $table, string $column, int $limit = 100): array
    {
        $this->inputValidator->validateTableName($table);

        return $this->inspectTableAction->relationOptions($table, $column, $this->connection(), $limit);
    }

    /**
     * @return array{columns: list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>, indexes: list<array{name: string, unique: bool, columns: list<string>}>, foreign_keys: list<array{column: string, table: string, foreign_column: string}>}
     */
    public function schema(string $table): array
    {
        $this->inputValidator->validateTableName($table);

        return $this->inspectTableAction->inspect($table, $this->connection());
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @return list<array<string, mixed>>
     */
    public function importRows(string $table, array $rows): array
    {
        $this->inputValidator->validateTableName($table);

        return $this->importRecordsAction->import($table, $this->connection(), $rows);
    }

    private function connection(): string
    {
        $connection = config('sqlite-manager.active_connection', 'default');

        return is_string($connection) ? $this->connectionManager->validConnection($connection) : 'default';
    }
}
