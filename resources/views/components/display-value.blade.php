@props([
    'value',
    'type' => null,
])

@php
    $columnType = is_string($type) ? mb_strtoupper($type) : '';
    $formattedDateTime = null;
    $formattedDate = null;
    $formattedJson = null;

    if (
        is_scalar($value)
        && (str_contains($columnType, 'DATETIME') || str_contains($columnType, 'TIMESTAMP'))
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
        && str_contains($columnType, 'DATE')
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
@endphp

@if ($value === null)
    NULL
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
