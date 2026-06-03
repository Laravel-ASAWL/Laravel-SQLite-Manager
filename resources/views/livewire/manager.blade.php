<div>
    <x-sqlite-manager::flash />

    @if ($status)
        <div class="alert alert-success">{{ $status }}</div>
    @endif

    <x-sqlite-manager::studio-shell :tables="$tables" :active-table="$table">
        @if ($error)
            <div class="alert alert-danger">{{ $error }}</div>
        @endif

        @if ($currentMode === 'dashboard')
            <div class="workspace-header card shadow-sm">
                <div>
                    <p class="eyebrow">Database</p>
                    <h1>SQLite Manager</h1>
                    <p class="text-body-secondary mb-0">Monitor SQLite tables with a Livewire-powered interface.</p>
                </div>
                <span @class(['badge', 'text-bg-success' => $exists, 'text-bg-danger' => ! $exists])>
                    {{ $exists ? 'Ready' : 'Missing' }}
                </span>
                <x-sqlite-manager::connection-selector :connections="$connections" :connection="$connection" />
            </div>

            <div class="inspector-grid row g-3">
                <section class="panel card shadow-sm col-12 col-xl">
                    <div class="card-body">
                        <h2 class="card-title">Connection</h2>
                        <dl class="mb-0">
                            <dt>Driver</dt>
                            <dd>SQLite</dd>
                            <dt>Status</dt>
                            <dd>{{ $exists ? 'Ready' : 'Missing' }}</dd>
                            <dt>Path</dt>
                            <dd><code>{{ $databasePath }}</code></dd>
                        </dl>
                    </div>
                </section>

                <section class="panel tables-panel card shadow-sm col-12 col-xl">
                    <div class="card-body">
                        <div class="section-heading d-flex align-items-center justify-content-between gap-2">
                            <h2 class="card-title mb-0">Manager tools</h2>
                            <span class="badge text-bg-secondary">Livewire</span>
                        </div>
                        <label class="laravel-tables-toggle form-check form-switch mt-3">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                role="switch"
                                wire:model.live="showLaravelTables"
                            />
                            <span class="form-check-label">Show Laravel tables</span>
                        </label>
                        <div class="object-grid tables-list mt-3">
                            <div class="object-tile list-group-item">
                                <span class="object-icon">B</span>
                                <span>
                                    <strong>Browse records</strong>
                                    <small>Inspect, search, paginate, create, edit, and delete rows from SQLite tables.</small>
                                </span>
                            </div>
                            <div class="object-tile list-group-item">
                                <span class="object-icon">C</span>
                                <span>
                                    <strong>Choose columns</strong>
                                    <small>Persist visible columns and page size preferences between visits.</small>
                                </span>
                            </div>
                            <div class="object-tile list-group-item">
                                <span class="object-icon">L</span>
                                <span>
                                    <strong>Filter framework tables</strong>
                                    <small>Keep Laravel internals hidden by default, with quick access when needed.</small>
                                </span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        @elseif ($currentMode === 'create' || $currentMode === 'edit')
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('sqlite-manager.index') }}">Database</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('sqlite-manager.tables.show', ['table' => $table]) }}">{{ $table }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $currentMode === 'edit' ? 'Edit' : 'Create' }}</li>
                </ol>
            </nav>

            <section class="panel form-panel card shadow-sm">
                <div class="toolbar card-header">
                    <div>
                        <p class="eyebrow">{{ $currentMode === 'edit' ? 'Edit record' : 'Create record' }}</p>
                        <h1>{{ $table }}</h1>
                    </div>
                    <a class="btn btn-outline-secondary" href="{{ route('sqlite-manager.tables.show', ['table' => $table]) }}">
                        Back to table
                    </a>
                </div>

                <form class="card-body" wire:submit.prevent="save">
                    @if ($readOnly)
                        <div class="alert alert-warning">SQLite Manager is running in read-only mode. Changes cannot be saved.</div>
                    @endif

                    <label class="laravel-tables-toggle form-check form-switch mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            role="switch"
                            wire:model.live="showNullableFields"
                        />
                        <span class="form-check-label">Show nullable fields</span>
                    </label>

                    <div class="field-grid">
                        @foreach ($columns as $column)
                            @if ($this->shouldShowFormColumn($column))
                                @php
                                    $relationshipOptions = $this->relationshipOptionsFor($column['name']);
                                @endphp
                                <label class="form-label">
                                    <span class="fw-semibold">{{ $column['name'] }}</span>
                                    @if ($relationshipOptions !== [] && ! $column['primary'])
                                        <select
                                            class="form-select"
                                            wire:model="form.{{ $column['name'] }}"
                                        >
                                            @if ($column['nullable'])
                                                <option value="">No related record</option>
                                            @else
                                                <option value="">Select related record</option>
                                            @endif
                                            @foreach ($relationshipOptions as $option)
                                                <option value="{{ $option['key'] }}">{{ $option['label'] }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($this->usesTextareaFor($column['type']))
                                        <textarea
                                            @class(['form-control', 'json-editor' => $this->usesJsonEditorFor($column)])
                                            rows="{{ $this->usesJsonEditorFor($column) ? 8 : 3 }}"
                                            @if ($this->usesJsonEditorFor($column)) spellcheck="false" @endif
                                            wire:model="form.{{ $column['name'] }}"
                                            @readonly($currentMode === 'edit' && $column['primary'])
                                        ></textarea>
                                    @else
                                        <input
                                            class="form-control"
                                            type="{{ $this->inputTypeFor($column['type']) }}"
                                            @if ($this->inputStepFor($column['type']) !== null) step="{{ $this->inputStepFor($column['type']) }}" @endif
                                            wire:model="form.{{ $column['name'] }}"
                                            @readonly($currentMode === 'edit' && $column['primary'])
                                        />
                                    @endif
                                    <small class="form-text text-body-secondary">
                                        {{ mb_strtoupper($column['type'] ?: 'TEXT') }}
                                        @if ($column['primary'])
                                            PRIMARY KEY
                                        @elseif ($column['nullable'])
                                            NULLABLE
                                        @endif
                                    </small>
                                    @if ($currentMode === 'edit' && $this->hasChanged($column['name']))
                                        <small class="form-text text-body-secondary">
                                            Original: {{ is_scalar($this->originalValue($column['name'])) || $this->originalValue($column['name']) === null ? (string) $this->originalValue($column['name']) : json_encode($this->originalValue($column['name']), JSON_THROW_ON_ERROR) }}
                                        </small>
                                    @endif
                                </label>
                            @endif
                        @endforeach
                    </div>

                    <div class="form-actions">
                        @if ($this->canAction($currentMode === 'edit' ? 'update' : 'create'))
                            <button class="btn btn-primary" type="submit">{{ $currentMode === 'edit' ? 'Update record' : 'Create record' }}</button>
                        @endif
                        <a class="btn btn-outline-secondary" href="{{ route('sqlite-manager.tables.show', ['table' => $table]) }}">
                            Cancel
                        </a>
                    </div>
                </form>
            </section>
        @elseif ($records !== null)
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('sqlite-manager.index') }}">Database</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $table }}</li>
                </ol>
            </nav>

            <section class="panel data-panel card shadow-sm">
                <div class="toolbar card-header">
                    <div>
                        <p class="eyebrow">Table data</p>
                        <h1>{{ $table }}</h1>
                    </div>
                    <div class="toolbar-actions">
                        <span class="badge text-bg-secondary">{{ $records['total'] }} rows</span>
                        <x-sqlite-manager::connection-selector :connections="$connections" :connection="$connection" />
                        @if ($this->canAction('export'))
                            <button class="btn btn-outline-secondary" type="button" wire:click="exportCurrent('csv')">Export CSV</button>
                            <button class="btn btn-outline-secondary" type="button" wire:click="exportCurrent('json')">Export JSON</button>
                        @endif
                        @if ($this->canAction('create'))
                            <a class="btn btn-primary" href="{{ route('sqlite-manager.tables.create', ['table' => $table]) }}">
                                Create record
                            </a>
                        @endif
                    </div>
                </div>

                <form class="search-form">
                    <details class="column-picker">
                        <summary>Columns</summary>
                        <div class="column-options">
                            @foreach ($records['columns'] as $column)
                                <label>
                                    <input
                                        type="checkbox"
                                        wire:model.live="selectedColumns"
                                        value="{{ $column['name'] }}"
                                        @checked(in_array($column['name'], $selectedColumnsForDisplay, true))
                                    />
                                    <span>{{ $column['name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </details>

                    <label class="per-page-control">
                        <span>Show</span>
                        <select class="form-select form-select-sm" wire:model.live="perPage">
                            @foreach ($perPageOptions as $option)
                                <option value="{{ $option }}" @selected($option === $perPage)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                    <input class="form-control" type="search" wire:model.live.debounce.300ms="search" placeholder="Search records in this table" />
                    <button class="btn btn-primary" type="button" wire:click="$refresh">Search</button>
                    @if ($search !== '')
                        <button class="btn btn-outline-secondary" type="button" wire:click="$set('search', '')">Clear</button>
                    @endif
                </form>

                <div class="advanced-filters">
                    <div class="advanced-filters-head">
                        <strong>Advanced filters</strong>
                        <div class="advanced-filters-actions">
                            <button class="btn btn-outline-secondary btn-sm" type="button" wire:click="addFilter">Add filter</button>
                            @if ($filters !== [])
                                <button class="btn btn-primary btn-sm" type="button" wire:click="applyFilters">Apply filters</button>
                                <button class="btn btn-outline-secondary btn-sm" type="button" wire:click="clearFilters">Clear filters</button>
                            @endif
                        </div>
                    </div>
                    @foreach ($filters as $index => $filter)
                        <div class="filter-row" wire:key="filter-{{ $table }}-{{ $index }}">
                            <select class="form-select form-select-sm" wire:model.live="filters.{{ $index }}.column" aria-label="Filter column">
                                <option value="">Column</option>
                                @foreach ($records['columns'] as $column)
                                    <option value="{{ $column['name'] }}">{{ $column['name'] }}</option>
                                @endforeach
                            </select>
                            <select class="form-select form-select-sm" wire:model.live="filters.{{ $index }}.operator" aria-label="Filter operator">
                                <option value="contains">Contains</option>
                                <option value="equals">Equals</option>
                                <option value="not_equals">Not equals</option>
                                <option value="starts_with">Starts with</option>
                                <option value="ends_with">Ends with</option>
                                <option value="gt">Greater than</option>
                                <option value="gte">Greater or equal</option>
                                <option value="lt">Less than</option>
                                <option value="lte">Less or equal</option>
                                <option value="is_null">Is null</option>
                                <option value="is_not_null">Is not null</option>
                            </select>
                            <input class="form-control form-control-sm" type="text" wire:model.live="filters.{{ $index }}.value" placeholder="Value" />
                            <button class="btn btn-outline-secondary btn-sm" type="button" wire:click="removeFilter({{ $index }})">Remove</button>
                        </div>
                    @endforeach
                </div>

                @if (collect($records['columns'])->contains(fn ($column) => $column['name'] === 'deleted_at'))
                    <label class="laravel-tables-toggle form-check form-switch mx-3 mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" wire:model.live="showSoftDeleted" />
                        <span class="form-check-label">Show soft-deleted records</span>
                    </label>
                @endif

                <x-sqlite-manager::schema-inspector :schema="$schema" />
                @if ($this->canAction('import'))
                    <x-sqlite-manager::import-panel :read-only="$readOnly" />
                @endif

                @if ($records['primary_key'] === null)
                    <div class="alert alert-warning">Edit and delete require a single-column primary key.</div>
                @elseif ($selectedRows !== [])
                    <div class="bulk-actions">
                        <span>{{ count($selectedRows) }} selected</span>
                        @if ($this->canAction('export'))
                            <button class="btn btn-outline-secondary btn-sm" type="button" wire:click="exportSelected('csv')">Export selected CSV</button>
                            <button class="btn btn-outline-secondary btn-sm" type="button" wire:click="exportSelected('json')">Export selected JSON</button>
                        @endif
                        @if ($this->canAction('bulk_delete'))
                            <button class="btn btn-outline-danger btn-sm" type="button" wire:click="bulkDelete" wire:confirm="Delete selected records?">Delete selected</button>
                        @endif
                    </div>
                @endif

                <div class="grid-frame table-responsive">
                    <table class="table table-sm table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                @if ($records['primary_key'] !== null)
                                    <th>Select</th>
                                @endif

                                @foreach ($visibleColumns as $column)
                                    <th>
                                        <button class="table-sort" type="button" wire:click="sortBy(@js($column['name']))">
                                            {{ $column['name'] }}
                                            @if ($sortColumn === $column['name'])
                                                {{ $sortDirection === 'desc' ? 'DESC' : 'ASC' }}
                                            @endif
                                        </button>
                                    </th>
                                @endforeach

                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($records['rows'] as $row)
                                <tr wire:key="row-{{ $table }}-{{ $loop->index }}">
                                    @php
                                        $recordKey = '';

                                        if ($records['primary_key'] !== null && array_key_exists($records['primary_key'], $row)) {
                                            $keyValue = $row[$records['primary_key']];
                                            $recordKey = is_scalar($keyValue) || $keyValue === null ? (string) $keyValue : '';
                                        }
                                    @endphp

                                    @if ($records['primary_key'] !== null)
                                        <td>
                                            <input type="checkbox" wire:model.live="selectedRows" value="{{ $recordKey }}" aria-label="Select record {{ $recordKey }}" />
                                        </td>
                                    @endif

                                    @foreach ($visibleColumns as $column)
                                        <td>
                                            @php
                                                $cellValue = $row[$column['name']] ?? null;
                                                $relationshipUrl = $this->relationshipUrl($column['name'], $cellValue);
                                            @endphp

                                            @if ($relationshipUrl !== null)
                                                <a href="{{ $relationshipUrl }}">
                                                    <x-sqlite-manager::display-value :value="$cellValue" :type="$column['type']" />
                                                </a>
                                            @else
                                                <x-sqlite-manager::display-value :value="$cellValue" :type="$column['type']" />
                                            @endif
                                        </td>
                                    @endforeach

                                    <td class="actions">
                                        @if ($records['primary_key'] !== null && $recordKey !== '')
                                            @if ($this->canAction('update') || $this->canAction('delete'))
                                                @if ($this->canAction('update'))
                                                <a
                                                    href="{{ route('sqlite-manager.tables.edit', ['table' => $table, 'key' => $recordKey]) }}"
                                                >
                                                    Edit
                                                </a>
                                                @endif
                                                @if ($this->canAction('delete'))
                                                <button
                                                    class="btn btn-link link-danger p-0 align-baseline"
                                                    type="button"
                                                    wire:click="deleteRecord(@js($recordKey))"
                                                    wire:confirm="Delete this record?"
                                                >
                                                    Delete
                                                </button>
                                                @endif
                                            @else
                                                <span class="muted">Read only</span>
                                            @endif
                                        @else
                                            <span class="muted">Unavailable</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($visibleColumns) + ($records['primary_key'] !== null ? 2 : 1) }}" class="empty">No records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($records['last_page'] > 1)
                    <x-sqlite-manager::pagination-controls :records="$records" />
                @endif
            </section>
        @endif
    </x-sqlite-manager::studio-shell>
</div>
