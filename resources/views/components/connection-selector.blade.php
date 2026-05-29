@props(['connections' => [], 'connection' => 'default'])

@if (count($connections) > 1)
    <label class="connection-selector">
        <span>Connection</span>
        <select class="form-select form-select-sm" wire:model.live="connection" aria-label="SQLite connection">
            @foreach ($connections as $connectionName)
                <option value="{{ $connectionName }}" @selected($connectionName === $connection)>{{ $connectionName }}</option>
            @endforeach
        </select>
    </label>
@endif
