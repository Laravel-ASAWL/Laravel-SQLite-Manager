<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Livewire;

use Asawl\LaravelSqliteManager\Actions\Security\ConnectionManager;
use Asawl\LaravelSqliteManager\SQLiteManagerRepository;
use Asawl\LaravelSqliteManager\Support\AccessPolicy;
use Asawl\LaravelSqliteManager\Support\AuditLogger;
use Asawl\LaravelSqliteManager\Support\CsvImporter;
use Asawl\LaravelSqliteManager\Support\DataExporter;
use Asawl\LaravelSqliteManager\Support\FormValidator;
use Asawl\LaravelSqliteManager\Support\SchemaInspector;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Filesystem\Filesystem;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

#[Layout('sqlite-manager::layouts.app')]
class SQLiteManagerLivewire extends Component
{
    use WithFilters;
    use WithFormHelpers;
    use WithPreferences;

    public ?string $table = null;

    public ?string $mode = null;

    public ?string $key = null;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 1)]
    public int $page = 1;

    #[Url(as: 'per_page')]
    public int $perPage = 0;

    #[Url(as: 'connection', except: 'default')]
    public string $connection = 'default';

    /** @var list<string> */
    #[Url(as: 'cols', except: [])]
    public array $selectedColumns = [];

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $showNullableFields = true;

    public bool $showSoftDeleted = false;

    public string $pageJump = '';

    public string $csvImport = '';

    /** @var array<string, mixed> */
    public array $originalForm = [];

    /** @var list<array{column: string, operator: string, value: string}> */
    public array $filters = [];

    public string $sortColumn = '';

    public string $sortDirection = 'asc';

    /** @var list<string> */
    public array $selectedRows = [];

    #[Url(as: 'laravel_tables', except: false)]
    public bool $showLaravelTables = false;

    public ?string $status = null;

    public ?string $error = null;

    private SQLiteManagerRepository $repository;

    private ConnectionManager $connectionManager;

    private AccessPolicy $accessPolicy;

    private AuditLogger $auditLogger;

    private CsvImporter $csvImporter;

    private DataExporter $dataExporter;

    private FormValidator $formValidator;

    private SchemaInspector $schemaInspector;

    private Filesystem $filesystem;

    public function mount(?string $table = null, ?string $mode = null, ?string $key = null): void
    {
        abort_unless($this->accessPolicy->canAccess(), 403);

        $this->table = $table;
        $this->mode = $mode;
        $this->key = $key;
        $this->connection = $this->validConnection($this->connection);
        $this->applySelectedConnection();
        $this->showLaravelTables = $this->defaultShowLaravelTables();
        $this->showSoftDeleted = $this->defaultShowSoftDeleted();
        $this->perPage = $this->perPage > 0 ? $this->perPage : $this->defaultPerPage();
        $this->hydrateCookiePreferences();
        $this->hydrateQueryParameters();
        $this->perPage = $this->validPerPage($this->perPage);

        if ($this->table === null) {
            return;
        }

        if ($this->mode === 'create') {
            $this->fillCreateForm();
        }

        if ($this->mode === 'edit' && $this->key !== null) {
            $this->fillEditForm($this->key);
        }
    }

    public function render(): Factory|View
    {
        $this->connection = $this->validConnection($this->connection);
        $this->applySelectedConnection();
        $databasePath = $this->repository->databasePath();
        $exists = $this->filesystem->exists($databasePath);
        $tables = [];
        $error = $this->error;
        $records = null;
        $columns = [];
        $schema = null;
        $selectedColumns = [];
        $visibleColumns = [];

        if ($exists) {
            try {
                $tables = $this->repository->tableSummaries($this->showLaravelTables);
            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }

        if ($this->table !== null && $exists) {
            try {
                if ($this->isFormMode()) {
                    $columns = $this->repository->columns($this->table);
                } else {
                    $records = $this->repository->records($this->table, $this->page, $this->perPage, $this->normalizedSearch(), $this->activeFilters(), $this->sortColumnOrNull(), $this->sortDirection, $this->showSoftDeleted);
                    $schema = $this->schemaInspector->inspect($this->table);
                    $selectedColumns = $this->normalizeSelectedColumns($records['columns']);
                    $visibleColumns = array_values(array_filter(
                        $records['columns'],
                        fn (array $column): bool => in_array($column['name'], $selectedColumns, true),
                    ));
                }
            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }

        return view('sqlite-manager::livewire.manager', [
            'columns' => $columns,
            'connections' => $this->connectionNames(),
            'databasePath' => $databasePath,
            'error' => $error,
            'exists' => $exists,
            'currentMode' => $this->currentMode(),
            'perPageOptions' => $this->perPageOptionValues(),
            'records' => $records,
            'readOnly' => $this->accessPolicy->readOnly(),
            'schema' => $schema,
            'selectedColumnsForDisplay' => $selectedColumns,
            'tables' => $tables,
            'visibleColumns' => $visibleColumns,
        ]);
    }

    public function save(): mixed
    {
        $this->applySelectedConnection();
        $this->resetMessages();

        if ($this->table === null) {
            $this->error = 'No SQLite table selected.';

            return null;
        }

        try {
            $action = $this->mode === 'edit' && $this->key !== null ? 'update' : 'create';

            if (! $this->accessPolicy->can($action)) {
                $this->error = $this->actionForbiddenMessage($action);

                return null;
            }

            $rules = $this->formValidator->rules($this->table);

            if ($rules !== []) {
                $this->validate($rules);
            }

            $this->formValidator->validateJson($this->form, $this->repository->columns($this->table));

            if ($this->mode === 'edit' && $this->key !== null) {
                $before = $this->repository->find($this->table, $this->key);
                $this->repository->update($this->table, $this->key, $this->form);
                $this->auditLogger->log('update', $this->table, $this->key, $before, $this->form);
                session()->flash('sqlite_manager_status', 'Record updated.');
            } else {
                $attributes = $this->createFormAttributes();
                $key = $this->repository->insert($this->table, $attributes);
                $this->auditLogger->log('create', $this->table, $key, null, $attributes);
                session()->flash('sqlite_manager_status', 'Record created.');
            }
        } catch (RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return null;
        }

        return $this->redirectRoute('sqlite-manager.tables.show', ['table' => $this->table]);
    }

    public function deleteRecord(string $key): void
    {
        $this->applySelectedConnection();
        $this->resetMessages();

        if ($this->table === null) {
            $this->error = 'No SQLite table selected.';

            return;
        }

        try {
            if (! $this->accessPolicy->can('delete')) {
                $this->error = $this->actionForbiddenMessage('delete');

                return;
            }

            $before = $this->repository->find($this->table, $key);
            $this->repository->delete($this->table, $key);
            $this->auditLogger->log('delete', $this->table, $key, $before);
        } catch (RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->status = 'Record deleted.';
    }

    public function bulkDelete(): void
    {
        $this->applySelectedConnection();
        $this->resetMessages();

        if ($this->table === null) {
            $this->error = 'No SQLite table selected.';

            return;
        }

        if (! $this->accessPolicy->can('bulk_delete')) {
            $this->error = $this->actionForbiddenMessage('bulk_delete');

            return;
        }

        try {
            $deleted = $this->repository->deleteMany($this->table, $this->selectedRows);
            $this->auditLogger->log('bulk_delete', $this->table, null, ['keys' => $this->selectedRows]);
        } catch (RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->selectedRows = [];
        $this->status = $deleted.' records deleted.';
    }

    public function exportCurrent(string $format): mixed
    {
        $this->applySelectedConnection();
        if (! $this->accessPolicy->can('export')) {
            $this->error = $this->actionForbiddenMessage('export');

            return null;
        }

        return $this->downloadExport($format, []);
    }

    public function exportSelected(string $format): mixed
    {
        $this->applySelectedConnection();
        if (! $this->accessPolicy->can('export')) {
            $this->error = $this->actionForbiddenMessage('export');

            return null;
        }

        return $this->downloadExport($format, $this->selectedRows);
    }

    public function importCsv(): void
    {
        $this->applySelectedConnection();
        $this->resetMessages();

        if ($this->table === null) {
            $this->error = 'No SQLite table selected.';

            return;
        }

        if (! $this->accessPolicy->can('import')) {
            $this->error = $this->actionForbiddenMessage('import');

            return;
        }

        try {
            $rows = $this->csvImporter->rows($this->csvImport);
            $inserted = $this->repository->importRows($this->tableOrFail(), $rows);

            $this->auditLogger->logBatch('import', $this->tableOrFail(), $inserted);
        } catch (RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->csvImport = '';
        $this->status = count($rows).' records imported.';
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function updatedFilters(): void
    {
        $this->page = 1;
        $this->rememberPreference($this->filtersCookieName(), json_encode($this->filters, JSON_THROW_ON_ERROR));
    }

    public function updatedShowSoftDeleted(): void
    {
        $this->page = 1;
        $this->rememberPreference($this->showSoftDeletedCookieName(), $this->showSoftDeleted ? '1' : '0');
    }

    public function updatedConnection(): void
    {
        $this->connection = $this->validConnection($this->connection);
        $this->applySelectedConnection();
        $this->page = 1;
    }

    public function goToPageInput(): void
    {
        $this->goToPage(is_numeric($this->pageJump) ? (int) $this->pageJump : 1);
        $this->pageJump = '';
    }

    public function updatedSortColumn(): void
    {
        $this->page = 1;
    }

    public function updatedSortDirection(): void
    {
        $this->sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';
        $this->page = 1;
    }

    public function sortBy(string $column): void
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }

        $this->page = 1;
    }

    public function updatedPerPage(): void
    {
        $this->perPage = $this->validPerPage($this->perPage);
        $this->page = 1;
        $this->rememberPreference($this->perPageCookieName(), (string) $this->perPage);
    }

    public function updatedSelectedColumns(): void
    {
        $this->page = 1;
        $this->rememberPreference($this->selectedColumnsCookieName(), json_encode($this->selectedColumns, JSON_THROW_ON_ERROR));
    }

    public function updatedShowLaravelTables(): void
    {
        $this->page = 1;
        $this->rememberPreference($this->showLaravelTablesCookieName(), $this->showLaravelTables ? '1' : '0');
    }

    public function updatedShowNullableFields(): void
    {
        $this->rememberPreference($this->showNullableFieldsCookieName(), $this->showNullableFields ? '1' : '0');
    }

    public function relationshipUrl(string $column, mixed $value): ?string
    {
        if ($this->table === null) {
            return null;
        }

        $target = $this->repository->relationTarget($this->table, $column, $value);

        return $target === null ? null : route('sqlite-manager.tables.edit', $target);
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function relationshipOptionsFor(string $column): array
    {
        if ($this->table === null) {
            return [];
        }

        return $this->repository->relationOptions($this->table, $column);
    }

    public function canAction(string $action): bool
    {
        return $this->accessPolicy->can($action);
    }

    public function hasChanged(string $column): bool
    {
        return array_key_exists($column, $this->originalForm) && ($this->originalForm[$column] ?? null) !== ($this->form[$column] ?? null);
    }

    public function originalValue(string $column): mixed
    {
        return $this->originalForm[$column] ?? null;
    }

    public function boot(
        SQLiteManagerRepository $sqLiteManagerRepository,
        ConnectionManager $connectionManager,
        AccessPolicy $accessPolicy,
        AuditLogger $auditLogger,
        CsvImporter $csvImporter,
        DataExporter $dataExporter,
        FormValidator $formValidator,
        SchemaInspector $schemaInspector,
        Filesystem $filesystem,
    ): void {
        $this->repository = $sqLiteManagerRepository;
        $this->connectionManager = $connectionManager;
        $this->accessPolicy = $accessPolicy;
        $this->auditLogger = $auditLogger;
        $this->csvImporter = $csvImporter;
        $this->dataExporter = $dataExporter;
        $this->formValidator = $formValidator;
        $this->schemaInspector = $schemaInspector;
        $this->filesystem = $filesystem;
    }

    private function fillCreateForm(): void
    {
        $this->resetMessages();

        try {
            $this->form = $this->emptyForm($this->repository->columns($this->tableOrFail()));
        } catch (RuntimeException $exception) {
            $this->error = $exception->getMessage();
        }
    }

    private function fillEditForm(string $key): void
    {
        $this->resetMessages();

        try {
            $record = $this->repository->find($this->tableOrFail(), $key);
            $columns = $this->repository->columns($this->tableOrFail());
        } catch (RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        if ($record === null) {
            $this->error = 'Record not found.';

            return;
        }

        $this->form = [];

        foreach ($columns as $column) {
            $name = $column['name'];
            $this->form[$name] = $record[$name] ?? null;
        }

        $this->originalForm = $this->form;
    }

    /**
     * @param  list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>  $columns
     * @return array<string, mixed>
     */
    private function emptyForm(array $columns): array
    {
        $form = [];

        foreach ($columns as $column) {
            $form[$column['name']] = '';
        }

        return $form;
    }

    /** @return array<string, mixed> */
    private function createFormAttributes(): array
    {
        if ($this->showNullableFields) {
            return $this->form;
        }

        $attributes = $this->form;

        foreach ($this->repository->columns($this->tableOrFail()) as $column) {
            if ($this->isNullableFormColumn($column)) {
                unset($attributes[$column['name']]);
            }
        }

        return $attributes;
    }

    private function currentMode(): string
    {
        return $this->isFormMode() ? (string) $this->mode : ($this->table === null ? 'dashboard' : 'table');
    }

    private function isFormMode(): bool
    {
        return in_array($this->mode, ['create', 'edit'], true);
    }

    private function tableOrFail(): string
    {
        if ($this->table === null) {
            throw new RuntimeException('No SQLite table selected.');
        }

        return $this->table;
    }

    private function defaultShowLaravelTables(): bool
    {
        return (bool) config('sqlite-manager.tables.show_laravel_tables', false);
    }

    private function defaultShowSoftDeleted(): bool
    {
        return (bool) config('sqlite-manager.tables.show_soft_deleted', false);
    }

    private function normalizedSearch(): ?string
    {
        $search = trim($this->search);

        return $search === '' ? null : $search;
    }

    private function sortColumnOrNull(): ?string
    {
        return $this->sortColumn === '' ? null : $this->sortColumn;
    }

    private function actionForbiddenMessage(string $action): string
    {
        if ($this->accessPolicy->readOnly() && in_array($action, ['create', 'update', 'delete', 'bulk_delete', 'import'], true)) {
            return 'SQLite Manager is running in read-only mode.';
        }

        return 'This SQLite Manager action is not allowed.';
    }

    private function validConnection(string $connection): string
    {
        return $this->connectionManager->validConnection($connection);
    }

    /** @return list<string> */
    private function connectionNames(): array
    {
        return $this->connectionManager->connectionNames();
    }

    private function applySelectedConnection(): void
    {
        config(['sqlite-manager.active_connection' => $this->connection]);
    }

    /** @param list<string> $selectedKeys */
    private function downloadExport(string $format, array $selectedKeys): mixed
    {
        if ($this->table === null) {
            $this->error = 'No SQLite table selected.';

            return null;
        }

        return $this->dataExporter->download($this->table, $format, $this->normalizedSearch(), $this->activeFilters(), $selectedKeys, $this->sortColumnOrNull(), $this->sortDirection, $this->showSoftDeleted);
    }

    /** @return list<int> */
    private function perPageOptionValues(): array
    {
        $options = config('sqlite-manager.pagination.per_page_options', [5, 10, 25, 50, 100]);

        if (! is_array($options)) {
            return [5, 10, 25, 50, 100];
        }

        $options = array_values(array_unique(array_filter(array_map(
            fn (mixed $option): int => is_numeric($option) ? (int) $option : 0,
            $options,
        ), fn (int $option): bool => $option > 0)));

        sort($options);

        return $options === [] ? [5, 10, 25, 50, 100] : $options;
    }

    private function defaultPerPage(): int
    {
        $default = config('sqlite-manager.pagination.default_per_page', 25);
        $default = is_numeric($default) ? (int) $default : 25;

        return in_array($default, $this->perPageOptionValues(), true) ? $default : 25;
    }

    private function validPerPage(int $perPage): int
    {
        return in_array($perPage, $this->perPageOptionValues(), true) ? $perPage : $this->defaultPerPage();
    }

    /**
     * @param  list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>  $columns
     * @return list<string>
     */
    private function normalizeSelectedColumns(array $columns): array
    {
        $available = array_map(fn (array $column): string => $column['name'], $columns);

        if ($this->selectedColumns === []) {
            $this->selectedColumns = $available;

            return $available;
        }

        $selected = array_values(array_intersect($available, $this->selectedColumns));

        if ($selected === []) {
            $this->selectedColumns = $available;

            return $available;
        }

        $this->selectedColumns = $selected;

        return $selected;
    }

    private function hydrateQueryParameters(): void
    {
        $search = request()->query('q');
        $page = request()->query('page');
        $perPage = request()->query('per_page');
        $showLaravelTables = request()->query('laravel_tables');
        $selectedColumns = request()->query('cols');

        if (is_string($search)) {
            $this->search = trim($search);
        }

        if (is_numeric($page)) {
            $this->page = max(1, (int) $page);
        }

        if (is_numeric($perPage)) {
            $this->perPage = (int) $perPage;
        }

        if ($showLaravelTables !== null) {
            $this->showLaravelTables = filter_var($showLaravelTables, FILTER_VALIDATE_BOOL);
        }

        if (is_array($selectedColumns)) {
            $this->selectedColumns = array_values(array_filter($selectedColumns, is_string(...)));
        }
    }

    private function resetMessages(): void
    {
        $this->status = null;
        $this->error = null;
    }
}
