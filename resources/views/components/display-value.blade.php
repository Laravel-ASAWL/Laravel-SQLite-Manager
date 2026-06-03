@props([
    'value',
    'type' => null,
    'column' => null,
    'badge' => false,
])

@php
    $columnName = is_string($column) ? $column : '';
    $columnType = is_string($type) ? mb_strtoupper($type) : '';
    $formattedDateTime = null;
    $formattedDate = null;
    $formattedJson = null;

    if (
        is_scalar($value)
        && (str_contains($columnType, 'DATETIME') || str_contains($columnType, 'TIMESTAMP')
            || preg_match('/^(created_at|updated_at|deleted_at|_at)$/', $columnName))
    ) {
        try {
            $formattedDateTime = \Illuminate\Support\Carbon::parse((string) $value)->format('d/m/Y H:i:s');
        } catch (Throwable) {
            $formattedDateTime = null;
        }
    }

    if (
        is_scalar($value)
        && $formattedDateTime === null
        && (str_contains($columnType, 'DATE') || preg_match('/^(created_date|_date)$/', $columnName))
    ) {
        try {
            $formattedDate = \Illuminate\Support\Carbon::parse((string) $value)->format('Y-m-d');
        } catch (Throwable) {
            $formattedDate = null;
        }
    }

    if (is_string($value) && (str_contains($columnType, 'JSON') || str_starts_with(trim($value), '{') || str_starts_with(trim($value), '['))) {
        try {
            $formattedJson = json_encode(json_decode($value, true, flags: JSON_THROW_ON_ERROR), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $formattedJson = null;
        }
    }

    $badgeStyles = [
        'create' => '--badge-bg: #ecfdf5; --badge-fg: #047857;',
        'update' => '--badge-bg: #eff6ff; --badge-fg: #1d4ed8;',
        'delete' => '--badge-bg: #fef2f2; --badge-fg: #b91c1c;',
        'bulk_delete' => '--badge-bg: #fff7ed; --badge-fg: #c2410c;',
        'import' => '--badge-bg: #f5f3ff; --badge-fg: #6d28d9;',
    ];

    $badgeStyle = '';
    $badgeLabel = '';

    if ($badge && is_string($value)) {
        $lower = mb_strtolower($value);

        if (array_key_exists($lower, $badgeStyles)) {
            $badgeStyle = $badgeStyles[$lower];
            $badgeLabel = $lower;
        } else {
            $hash = crc32($lower);
            $hue = abs($hash) % 360;
            $badgeStyle = "--badge-bg: hsl({$hue}, 60%, 92%); --badge-fg: hsl({$hue}, 60%, 25%);";
            $badgeLabel = $lower;
        }
    }
@endphp

@if ($value === null)
    NULL
@elseif ($badgeLabel !== '')
    <span class="badge audit-badge" style="{{ $badgeStyle }}">{{ $badgeLabel }}</span>
@elseif ($formattedDateTime !== null)
    {{ $formattedDateTime }}
@elseif ($formattedDate !== null)
    {{ $formattedDate }}
@elseif ($formattedJson !== null)
    <pre class="json-preview">{{ $formattedJson }}</pre>
@elseif (is_bool($value))
    {{ $value ? '1' : '0' }}
@elseif (is_scalar($value))
    {{ (string) $value }}
@else
    {{ json_encode($value, JSON_THROW_ON_ERROR) }}
@endif
