<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Livewire;

use Asawl\LaravelSqliteManager\Actions\Security\ConnectionManager;
use Asawl\LaravelSqliteManager\Actions\Security\InputValidator;
use Asawl\LaravelSqliteManager\SQLiteManagerRepository;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use PDO;
use RuntimeException;

#[Layout('sqlite-manager::layouts.app')]
class AuditLogLivewire extends Component
{
    #[Url(except: '')]
    public string $actionFilter = '';

    #[Url(except: '')]
    public string $tableFilter = '';

    #[Url(except: 1)]
    public int $page = 1;

    public int $perPage = 25;

    private ConnectionManager $connectionManager;

    private InputValidator $inputValidator;

    private SQLiteManagerRepository $repository;

    public function boot(
        ConnectionManager $connectionManager,
        InputValidator $inputValidator,
        SQLiteManagerRepository $repository,
    ): void {
        $this->connectionManager = $connectionManager;
        $this->inputValidator = $inputValidator;
        $this->repository = $repository;
    }

    public function render(): Factory|View
    {
        $auditTable = $this->auditTable();
        $connection = config('sqlite-manager.active_connection', 'default');
        $connection = is_string($connection) ? $connection : 'default';
        $databasePath = $this->connectionManager->databasePath($connection);

        $entries = [];
        $total = 0;
        $lastPage = 1;
        $actionTypes = [];
        $tableNames = [];

        try {
            $pdo = $this->connectionManager->pdo($connection);

            $actionTypes = $this->fetchActionTypes($pdo, $auditTable);
            $tableNames = $this->fetchTableNames($pdo, $auditTable);
            $total = $this->countEntries($pdo, $auditTable);
            $lastPage = max(1, (int) ceil($total / $this->perPage));
            $this->page = min(max(1, $this->page), $lastPage);
            $entries = $this->fetchEntries($pdo, $auditTable);
        } catch (RuntimeException) {
        }

        $tables = [];
        try {
            $tables = $this->repository->tableSummaries(false);
        } catch (RuntimeException) {
        }

        return view('sqlite-manager::livewire.audit-log', [
            'entries' => $entries,
            'total' => $total,
            'lastPage' => $lastPage,
            'actionTypes' => $actionTypes,
            'tableNames' => $tableNames,
            'databasePath' => $databasePath,
            'tables' => $tables,
        ]);
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function updatedActionFilter(): void
    {
        $this->page = 1;
    }

    public function updatedTableFilter(): void
    {
        $this->page = 1;
    }

    public function clearFilters(): void
    {
        $this->actionFilter = '';
        $this->tableFilter = '';
        $this->page = 1;
    }

    /** @return list<string> */
    private function fetchActionTypes(PDO $pdo, string $table): array
    {
        $quotedTable = $this->inputValidator->quoteIdentifier($table);
        $result = $pdo->query("SELECT DISTINCT action FROM {$quotedTable} ORDER BY action");

        if ($result === false) {
            return [];
        }

        $types = $result->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter($types, is_string(...)));
    }

    /** @return list<string> */
    private function fetchTableNames(PDO $pdo, string $table): array
    {
        $quotedTable = $this->inputValidator->quoteIdentifier($table);
        $result = $pdo->query("SELECT DISTINCT table_name FROM {$quotedTable} ORDER BY table_name");

        if ($result === false) {
            return [];
        }

        $names = $result->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter($names, is_string(...)));
    }

    private function countEntries(PDO $pdo, string $table): int
    {
        $quotedTable = $this->inputValidator->quoteIdentifier($table);
        $conditions = $this->buildWhereClause();
        $sql = "SELECT COUNT(*) FROM {$quotedTable}{$conditions}";
        $statement = $pdo->prepare($sql);
        $statement->execute($this->buildBindings());

        $count = $statement->fetchColumn();

        return is_numeric($count) ? (int) $count : 0;
    }

    /** @return list<array<string, string|null>> */
    private function fetchEntries(PDO $pdo, string $table): array
    {
        $quotedTable = $this->inputValidator->quoteIdentifier($table);
        $conditions = $this->buildWhereClause();
        $offset = ($this->page - 1) * $this->perPage;
        $sql = "SELECT * FROM {$quotedTable}{$conditions} ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $statement = $pdo->prepare($sql);
        $statement->bindValue(':limit', $this->perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);

        foreach ($this->buildBindings() as $key => $value) {
            $statement->bindValue($key, $value);
        }

        $statement->execute();

        /** @var list<array<string, string|null>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_values(array_filter($rows, is_array(...)));
    }

    private function buildWhereClause(): string
    {
        $clauses = [];

        if ($this->actionFilter !== '') {
            $clauses[] = 'action = :action_filter';
        }

        if ($this->tableFilter !== '') {
            $clauses[] = 'table_name = :table_filter';
        }

        return $clauses !== [] ? ' WHERE '.implode(' AND ', $clauses) : '';
    }

    /** @return array<string, mixed> */
    private function buildBindings(): array
    {
        $bindings = [];

        if ($this->actionFilter !== '') {
            $bindings[':action_filter'] = $this->actionFilter;
        }

        if ($this->tableFilter !== '') {
            $bindings[':table_filter'] = $this->tableFilter;
        }

        return $bindings;
    }

    private function auditTable(): string
    {
        $table = config('sqlite-manager.audit.table', '_lsm_audit_log');

        return is_string($table) && $table !== '' ? $table : '_lsm_audit_log';
    }
}
