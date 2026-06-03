<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>{{ $title ?? 'SQLite Database Manager' }}</title>
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
            rel="stylesheet"
        />
        <link rel="stylesheet" href="{{ route('sqlite-manager.assets.stylesheet') }}" />
        @livewireStyles
    </head>
    <body data-theme="{{ config('sqlite-manager.ui.theme', 'auto') }}">
        <main>{{ $slot }}</main>
        @livewireScripts
    </body>
</html>
