<div>
    <x-sqlite-manager::flash />

    <x-sqlite-manager::studio-shell :tables="$tables" :active-table="'_lsm_audit_log'">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('sqlite-manager.index') }}">Database</a></li>
                <li class="breadcrumb-item active" aria-current="page">Audit Log</li>
            </ol>
        </nav>

        <section
            class="panel data-panel card shadow-sm"
            x-data="{ detailRecord: null }"
            @keydown.escape.window="detailRecord = null"
        >
            <div class="toolbar card-header">
                <div>
                    <p class="eyebrow">Activity log</p>
                    <h1>Audit Log</h1>
                    <p class="text-body-secondary mb-0">Track all create, update, delete, and import operations.</p>
                </div>
                <div class="toolbar-actions">
                    <span class="badge text-bg-secondary">{{ $total }} entries</span>
                </div>
            </div>

            <form class="search-form">
                <div class="audit-filters">
                    <label class="audit-filter-label">
                        <span>Action</span>
                        <select class="form-select form-select-sm" wire:model.live="actionFilter">
                            <option value="">All actions</option>
                            @foreach ($actionTypes as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="audit-filter-label">
                        <span>Table</span>
                        <select class="form-select form-select-sm" wire:model.live="tableFilter">
                            <option value="">All tables</option>
                            @foreach ($tableNames as $name)
                                <option value="{{ $name }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if ($actionFilter !== '' || $tableFilter !== '')
                        <button class="btn btn-outline-secondary btn-sm" type="button" wire:click="clearFilters">Clear filters</button>
                    @endif
                </div>
            </form>

            <div class="grid-frame table-responsive">
                <table class="table table-sm table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Table</th>
                            <th>Record Key</th>
                            <th>Timestamp</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr wire:key="audit-{{ $entry['id'] }}">
                                <td>
                                    @php
                                        $badgeStyles = [
                                            'create' => '--badge-bg: #ecfdf5; --badge-fg: #047857;',
                                            'update' => '--badge-bg: #eff6ff; --badge-fg: #1d4ed8;',
                                            'delete' => '--badge-bg: #fef2f2; --badge-fg: #b91c1c;',
                                            'bulk_delete' => '--badge-bg: #fff7ed; --badge-fg: #c2410c;',
                                            'import' => '--badge-bg: #f5f3ff; --badge-fg: #6d28d9;',
                                        ];
                                        $action = $entry['action'] ?? '';
                                        $style = $badgeStyles[mb_strtolower((string) $action)] ?? '--badge-bg: #f3f4f6; --badge-fg: #374151;';
                                    @endphp
                                    <span class="badge audit-badge" style="{{ $style }}">{{ $action }}</span>
                                </td>
                                <td><code>{{ $entry['table_name'] ?? '' }}</code></td>
                                <td>{{ $entry['record_key'] ?? 'N/A' }}</td>
                                <td class="audit-timestamp">{{ $entry['created_at'] ?? '' }}</td>
                                <td class="actions">
                                    <button
                                        class="btn btn-sm btn-outline-secondary"
                                        type="button"
                                        @click="detailRecord = @js($entry)"
                                    >
                                        View
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty">No audit entries found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($lastPage > 1)
                <div class="pagination-shell">
                    <span>Page {{ $page }} of {{ $lastPage }}</span>
                    <div class="pagination-controls">
                        <button
                            class="btn btn-outline-secondary btn-sm"
                            type="button"
                            wire:click="goToPage({{ $page - 1 }})"
                            @disabled($page <= 1)
                        >
                            &laquo; Previous
                        </button>
                        <button
                            class="btn btn-outline-secondary btn-sm"
                            type="button"
                            wire:click="goToPage({{ $page + 1 }})"
                            @disabled($page >= $lastPage)
                        >
                            Next &raquo;
                        </button>
                    </div>
                </div>
            @endif

            <div
                class="modal-backdrop audit-modal-backdrop"
                x-show="detailRecord !== null"
                x-cloak
                @click="detailRecord = null"
            ></div>
            <div
                class="modal audit-modal"
                role="dialog"
                tabindex="-1"
                x-show="detailRecord !== null"
                x-cloak
                @click.outside="detailRecord = null"
            >
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" x-text="'Audit Log — ' + (detailRecord?.action || '')"></h5>
                            <button type="button" class="btn-close" @click="detailRecord = null"></button>
                        </div>
                        <div class="modal-body">
                            <template x-if="detailRecord">
                                <div>
                                    <dl class="audit-detail-grid">
                                        <dt>Action</dt>
                                        <dd>
                                            <span class="badge audit-badge" x-text="detailRecord.action"
                                                :style="{
                                                    '--badge-bg': detailRecord.action === 'create' ? '#ecfdf5' : detailRecord.action === 'update' ? '#eff6ff' : detailRecord.action === 'delete' ? '#fef2f2' : detailRecord.action === 'bulk_delete' ? '#fff7ed' : detailRecord.action === 'import' ? '#f5f3ff' : '#f3f4f6',
                                                    '--badge-fg': detailRecord.action === 'create' ? '#047857' : detailRecord.action === 'update' ? '#1d4ed8' : detailRecord.action === 'delete' ? '#b91c1c' : detailRecord.action === 'bulk_delete' ? '#c2410c' : detailRecord.action === 'import' ? '#6d28d9' : '#374151'
                                                }"
                                            ></span>
                                        </dd>
                                        <dt>Table</dt>
                                        <dd x-text="detailRecord.table_name"></dd>
                                        <dt>Record Key</dt>
                                        <dd x-text="detailRecord.record_key ?? 'N/A'"></dd>
                                        <dt>Timestamp</dt>
                                        <dd x-text="detailRecord.created_at"></dd>
                                    </dl>
                                    <div class="audit-compare">
                                        <div class="audit-compare-col">
                                            <strong class="audit-compare-label">Before</strong>
                                            <pre class="json-preview audit-json-block" x-text="detailRecord.before_values ?? '(empty)'"></pre>
                                        </div>
                                        <div class="audit-compare-arrow">&rarr;</div>
                                        <div class="audit-compare-col">
                                            <strong class="audit-compare-label">After</strong>
                                            <pre class="json-preview audit-json-block" x-text="detailRecord.after_values ?? '(empty)'"></pre>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="detailRecord = null">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </x-sqlite-manager::studio-shell>
</div>
