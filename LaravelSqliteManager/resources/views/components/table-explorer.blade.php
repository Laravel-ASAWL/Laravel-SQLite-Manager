@props(['tables' => [], 'activeTable' => null])

@php
    $tableCount = count($tables);
    $activeTableName = is_string($activeTable) ? $activeTable : null;
@endphp

<aside class="explorer border-0 rounded-0">
    <div class="explorer-head">
        <a class="brand" href="{{ route('sqlite-manager.index') }}">SQLite Manager</a>
        <span>Database pulse</span>
    </div>
    <details class="explorer-menu" open>
        <summary class="explorer-summary">
            <span>Tables</span>
            <strong>{{ $activeTableName ?? $tableCount.' tables' }}</strong>
        </summary>
        <div class="explorer-section">
            <div class="explorer-title">Tables</div>
            @forelse ($tables as $table)
                <a
                    @class(['explorer-item list-group-item list-group-item-action', 'active' => $activeTableName === $table['name']])
                    href="{{ route('sqlite-manager.tables.show', ['table' => $table['name']]) }}"
                >
                    <span class="object-icon">T</span>
                    <span class="explorer-name">{{ $table['name'] }}</span>
                    <span class="explorer-count">{{ $table['rows'] }}</span>
                </a>
            @empty
                <div class="explorer-empty">No tables</div>
            @endforelse
        </div>
    </details>
</aside>
