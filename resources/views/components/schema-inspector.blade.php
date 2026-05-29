@props(['schema' => null])

@if (is_array($schema))
    <details class="schema-inspector">
        <summary>Schema inspector</summary>
        <div class="schema-grid">
            <section>
                <h2>Columns</h2>
                <ul>
                    @foreach ($schema['columns'] as $column)
                        <li>
                            <strong>{{ $column['name'] }}</strong>
                            <span>{{ mb_strtoupper($column['type'] ?: 'TEXT') }}</span>
                            @if ($column['primary']) <span>PRIMARY</span> @endif
                            @if ($column['nullable']) <span>NULLABLE</span> @endif
                        </li>
                    @endforeach
                </ul>
            </section>
            <section>
                <h2>Indexes</h2>
                <ul>
                    @forelse ($schema['indexes'] as $index)
                        <li><strong>{{ $index['name'] }}</strong> {{ $index['unique'] ? 'UNIQUE' : 'INDEX' }} ({{ implode(', ', $index['columns']) }})</li>
                    @empty
                        <li>No indexes</li>
                    @endforelse
                </ul>
            </section>
            <section>
                <h2>Foreign keys</h2>
                <ul>
                    @forelse ($schema['foreign_keys'] as $foreignKey)
                        <li>{{ $foreignKey['column'] }} -> {{ $foreignKey['table'] }}.{{ $foreignKey['foreign_column'] }}</li>
                    @empty
                        <li>No foreign keys</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </details>
@endif
