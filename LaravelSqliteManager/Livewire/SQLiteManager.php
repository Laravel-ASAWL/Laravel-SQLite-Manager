<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Livewire;

use Asawl\LaravelSqliteManager\SQLiteDatabaseManager;
use Asawl\LaravelSqliteManager\SQLiteDatabaseRepository;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Gate;
use JsonException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

#[Layout('sqlite-manager::layouts.app')]
class SQLiteManager extends Component
{
    public ?string $table = null;

    public ?string $mode = null;

    public ?string $key = null;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 1)]
    public int $page = 1;

    #[Url(as: 'per_page')]
    public int $perPage = 0;

    /** @var list<string> */
    #[Url(as: 'cols', except: [])]
    public array $selectedColumns = [];

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $showNullableFields = true;

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

    public function mount(?string $table = null, ?string $mode = null, ?string $key = null): void
    {
        abort_unless($this->canAccessManager(), 403);

        $this->table = $table;
        $this->mode = $mode;
        $this->key = $key;
        $this->showLaravelTables = $this->defaultShowLaravelTables();
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
        $databasePath = $this->manager()->resolvePath($this->configuredPath());
        $exists = $this->filesystem()->exists($databasePath);
        $tables = [];
        $error = $this->error;
        $records = null;
        $columns = [];
        $selectedColumns = [];
        $visibleColumns = [];

        if ($exists) {
            try {
                $tables = $this->repository()->tableSummaries($this->showLaravelTables);
            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }

        if ($this->table !== null && $exists) {
            try {
                if ($this->isFormMode()) {
                    $columns = $this->repository()->columns($this->table);
                } else {
                    $records = $this->repository()->records($this->table, $this->page, $this->perPage, $this->normalizedSearch(), $this->activeFilters(), $this->sortColumnOrNull(), $this->sortDirection);
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
            'databasePath' => $databasePath,
            'error' => $error,
            'exists' => $exists,
            'currentMode' => $this->currentMode(),
            'perPageOptions' => $this->perPageOptionValues(),
            'records' => $records,
            'readOnly' => $this->readOnly(),
            'selectedColumnsForDisplay' => $selectedColumns,
            'showLaravelTables' => $this->showLaravelTables,
            'tables' => $tables,
            'visibleColumns' => $visibleColumns,
        ]);
    }

    public function save(): mixed
    {
        $this->resetMessages();

        if ($this->table === null) {
            $this->error = 'No SQLite table selected.';

            return null;
        }

        try {
            if ($this->readOnly()) {
                $this->error = 'SQLite Manager is running in read-only mode.';

                return null;
            }

            $rules = $this->formValidationRules();

            if ($rules !== []) {
                $this->validate($rules);
            }

            if ($this->mode === 'edit' && $this->key !== null) {
                $before = $this->repository()->find($this->table, $this->key);
                $this->repository()->update($this->table, $this->key, $this->form);
                $this->repository()->audit('update', $this->table, $this->key, $before, $this->form);
                session()->flash('sqlite_manager_status', 'Record updated.');
            } else {
                $attributes = $this->createFormAttributes();
                $key = $this->repository()->insert($this->table, $attributes);
                $this->repository()->audit('create', $this->table, $key, null, $attributes);
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
        $this->resetMessages();

        if ($this->table === null) {
            $this->error = 'No SQLite table selected.';

            return;
        }

        try {
            if ($this->readOnly()) {
                $this->error = 'SQLite Manager is running in read-only mode.';

                return;
            }

            $before = $this->repository()->find($this->table, $key);
            $this->repository()->delete($this->table, $key);
            $this->repository()->audit('delete', $this->table, $key, $before, null);
        } catch (RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->status = 'Record deleted.';
    }

    public function bulkDelete(): void
    {
        $this->resetMessages();

        if ($this->table === null) {
            $this->error = 'No SQLite table selected.';

            return;
        }

        if ($this->readOnly()) {
            $this->error = 'SQLite Manager is running in read-only mode.';

            return;
        }

        try {
            $deleted = $this->repository()->deleteMany($this->table, $this->selectedRows);
            $this->repository()->audit('bulk_delete', $this->table, null, ['keys' => $this->selectedRows], null);
        } catch (RuntimeException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->selectedRows = [];
        $this->status = $deleted.' records deleted.';
    }

    public function exportCurrent(string $format): mixed
    {
        return $this->downloadExport($format, []);
    }

    public function exportSelected(string $format): mixed
    {
        return $this->downloadExport($format, $this->selectedRows);
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

    public function addFilter(): void
    {
        $this->filters[] = ['column' => '', 'operator' => 'contains', 'value' => ''];
    }

    public function removeFilter(int $index): void
    {
        unset($this->filters[$index]);
        $this->filters = array_values($this->filters);
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

    public function inputTypeFor(string $type): string
    {
        $type = mb_strtoupper($type);

        if (str_contains($type, 'INT')) {
            return 'number';
        }

        if (str_contains($type, 'REAL') || str_contains($type, 'FLOA') || str_contains($type, 'DOUB') || str_contains($type, 'DEC') || str_contains($type, 'NUM')) {
            return 'number';
        }

        if (str_contains($type, 'DATETIME') || str_contains($type, 'TIMESTAMP')) {
            return 'datetime-local';
        }

        if (str_contains($type, 'DATE')) {
            return 'date';
        }

        if (str_contains($type, 'TIME')) {
            return 'time';
        }

        return 'text';
    }

    public function usesTextareaFor(string $type): bool
    {
        $type = mb_strtoupper($type);

        return str_contains($type, 'TEXT') || str_contains($type, 'BLOB') || str_contains($type, 'BINARY') || str_contains($type, 'CLOB');
    }

    public function inputStepFor(string $type): ?string
    {
        $type = mb_strtoupper($type);

        if (str_contains($type, 'REAL') || str_contains($type, 'FLOA') || str_contains($type, 'DOUB') || str_contains($type, 'DEC') || str_contains($type, 'NUM')) {
            return 'any';
        }

        return null;
    }

    /** @param array{name: string, type: string, nullable: bool, default: mixed, primary: bool} $column */
    public function usesJsonEditorFor(array $column): bool
    {
        $type = mb_strtoupper($column['type']);

        return str_contains($type, 'JSON') || str_ends_with($column['name'], '_json');
    }

    public function relationshipUrl(string $column, mixed $value): ?string
    {
        if ($this->table === null) {
            return null;
        }

        $target = $this->repository()->relationTarget($this->table, $column, $value);

        return $target === null ? null : route('sqlite-manager.tables.edit', $target);
    }

    /**
     * @param  array{name: string, type: string, nullable: bool, default: mixed, primary: bool}  $column
     */
    public function shouldShowFormColumn(array $column): bool
    {
        return ! $this->isFormMode() || $this->showNullableFields || ! $this->isNullableFormColumn($column);
    }

    private function fillCreateForm(): void
    {
        $this->resetMessages();

        try {
            $this->form = $this->emptyForm($this->repository()->columns($this->tableOrFail()));
        } catch (RuntimeException $exception) {
            $this->error = $exception->getMessage();
        }
    }

    private function fillEditForm(string $key): void
    {
        $this->resetMessages();

        try {
            $record = $this->repository()->find($this->tableOrFail(), $key);
            $columns = $this->repository()->columns($this->tableOrFail());
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

        foreach ($this->repository()->columns($this->tableOrFail()) as $column) {
            if ($this->isNullableFormColumn($column)) {
                unset($attributes[$column['name']]);
            }
        }

        return $attributes;
    }

    /**
     * @param  array{name: string, type: string, nullable: bool, default: mixed, primary: bool}  $column
     */
    private function isNullableFormColumn(array $column): bool
    {
        return $column['nullable'] && ! $column['primary'];
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

    private function configuredPath(): string
    {
        $path = config('sqlite-manager.database_path', database_path('database.sqlite'));

        return is_string($path) ? $path : database_path('database.sqlite');
    }

    private function readOnly(): bool
    {
        return (bool) config('sqlite-manager.security.read_only', false);
    }

    private function canAccessManager(): bool
    {
        $environments = config('sqlite-manager.security.allowed_environments', ['local', 'testing']);

        if (is_array($environments) && $environments !== [] && ! in_array(app()->environment(), array_filter($environments, is_string(...)), true)) {
            return false;
        }

        $gate = config('sqlite-manager.security.authorization_gate');

        return ! is_string($gate) || $gate === '' || Gate::allows($gate);
    }

    private function defaultShowLaravelTables(): bool
    {
        return (bool) config('sqlite-manager.tables.show_laravel_tables', false);
    }

    private function normalizedSearch(): ?string
    {
        $search = trim($this->search);

        return $search === '' ? null : $search;
    }

    /** @return list<array{column: string, operator: string, value: string}> */
    private function activeFilters(): array
    {
        return array_values(array_filter(array_map(function (mixed $filter): ?array {
            if (! is_array($filter)) {
                return null;
            }

            $column = $filter['column'] ?? '';
            $operator = $filter['operator'] ?? 'contains';
            $value = $filter['value'] ?? '';

            if (! is_string($column) || ! is_string($operator) || (! is_string($value) && ! is_numeric($value))) {
                return null;
            }

            return ['column' => $column, 'operator' => $operator, 'value' => (string) $value];
        }, $this->filters)));
    }

    private function sortColumnOrNull(): ?string
    {
        return $this->sortColumn === '' ? null : $this->sortColumn;
    }

    /** @return array<string, string|list<string>> */
    private function formValidationRules(): array
    {
        if ($this->table === null) {
            return [];
        }

        $rules = config('sqlite-manager.validation.rules.'.$this->table, []);

        if (! is_array($rules)) {
            return [];
        }

        $validationRules = [];

        foreach ($rules as $column => $rule) {
            if (is_string($column) && (is_string($rule) || is_array($rule))) {
                $validationRules['form.'.$column] = $rule;
            }
        }

        return $validationRules;
    }

    /** @param list<string> $selectedKeys */
    private function downloadExport(string $format, array $selectedKeys): mixed
    {
        if ($this->table === null) {
            $this->error = 'No SQLite table selected.';

            return null;
        }

        $rows = $this->repository()->exportRows($this->table, $this->normalizedSearch(), $this->activeFilters(), $selectedKeys, $this->sortColumnOrNull(), $this->sortDirection);
        $format = $format === 'json' ? 'json' : 'csv';
        $filename = $this->table.'-'.now()->format('Ymd-His').'.'.$format;

        if ($format === 'json') {
            return response()->streamDownload(function () use ($rows): void {
                print json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            }, $filename, ['Content-Type' => 'application/json']);
        }

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            $headers = array_keys($rows[0] ?? []);

            if ($headers !== []) {
                fputcsv($output, $headers);
            }

            foreach ($rows as $row) {
                fputcsv($output, array_map(fn (mixed $value): mixed => is_scalar($value) || $value === null ? $value : json_encode($value, JSON_THROW_ON_ERROR), $row));
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
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

    private function hydrateCookiePreferences(): void
    {
        $perPage = $this->cookiePreference($this->perPageCookieName());
        $showLaravelTables = $this->cookiePreference($this->showLaravelTablesCookieName());
        $showNullableFields = $this->cookiePreference($this->showNullableFieldsCookieName());
        $selectedColumns = $this->cookiePreference($this->selectedColumnsCookieName());

        if (is_numeric($perPage)) {
            $this->perPage = (int) $perPage;
        }

        if (is_string($showLaravelTables)) {
            $this->showLaravelTables = filter_var($showLaravelTables, FILTER_VALIDATE_BOOL);
        }

        if (is_string($showNullableFields)) {
            $this->showNullableFields = filter_var($showNullableFields, FILTER_VALIDATE_BOOL);
        }

        if (is_string($selectedColumns)) {
            try {
                $selectedColumns = json_decode($selectedColumns, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $selectedColumns = null;
            }

            if (is_array($selectedColumns)) {
                $this->selectedColumns = array_values(array_filter($selectedColumns, is_string(...)));
            }
        }
    }

    private function rememberPreference(string $name, string $value): void
    {
        Cookie::queue($name, $value, 60 * 24 * 365);
    }

    private function cookiePreference(string $name): ?string
    {
        $value = request()->cookie($name);

        if (is_string($value)) {
            return $value;
        }

        return $this->rawCookiePreference($name);
    }

    private function rawCookiePreference(string $name): ?string
    {
        $cookies = request()->headers->get('cookie');

        if (! is_string($cookies)) {
            return null;
        }

        foreach (explode(';', $cookies) as $cookie) {
            $parts = explode('=', trim($cookie), 2);

            if (count($parts) === 2 && $parts[0] === $name) {
                return rawurldecode($parts[1]);
            }
        }

        return null;
    }

    private function perPageCookieName(): string
    {
        return 'sqlite_manager_per_page';
    }

    private function showLaravelTablesCookieName(): string
    {
        return 'sqlite_manager_show_laravel_tables';
    }

    private function showNullableFieldsCookieName(): string
    {
        return 'sqlite_manager_show_nullable_fields';
    }

    private function selectedColumnsCookieName(): string
    {
        return 'sqlite_manager_columns_'.sha1((string) $this->table);
    }

    private function resetMessages(): void
    {
        $this->status = null;
        $this->error = null;
    }

    private function manager(): SQLiteDatabaseManager
    {
        return resolve(SQLiteDatabaseManager::class);
    }

    private function repository(): SQLiteDatabaseRepository
    {
        return resolve(SQLiteDatabaseRepository::class);
    }

    private function filesystem(): Filesystem
    {
        return resolve(Filesystem::class);
    }
}
