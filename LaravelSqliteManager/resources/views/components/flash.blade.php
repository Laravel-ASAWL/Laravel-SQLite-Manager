@php
    $message = session('sqlite_manager_status');
    $message = is_string($message) ? $message : null;
    $error = session('sqlite_manager_error');
    $error = is_string($error) ? $error : null;
@endphp

@if ($message)
    <div class="alert alert-success">{{ $message }}</div>
@endif

@if ($error)
    <div class="alert alert-danger">{{ $error }}</div>
@endif
