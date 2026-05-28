# Laravel SQLite Manager

Livewire-powered SQLite database manager for Laravel 12 applications.

This package adds a small web UI for browsing and editing records in a configured SQLite database. It is intended for local development, internal tooling, and controlled admin environments.

## Requirements

- PHP 8.2 or higher
- Laravel 12
- Livewire 3
- PHP extensions: `mbstring`, `pdo`, `pdo_sqlite`

## Installation

Install the package with Composer:

```bash
composer require asawl/laravel-sqlite-manager
```

Laravel package auto-discovery registers the service provider automatically.

Run the installer:

```bash
php artisan sqlite-manager:install
```

The installer publishes `config/sqlite-manager.php` and adds missing package variables to `.env`.

Use `--force` if you need to overwrite the published config file:

```bash
php artisan sqlite-manager:install --force
```

## Environment

The installer adds these variables when they are missing:

```dotenv
SQLITE_MANAGER_DATABASE_PATH="database/database.sqlite"
SQLITE_MANAGER_ROUTES_ENABLED=true
SQLITE_MANAGER_ROUTE_PREFIX=sqlite-manager
SQLITE_MANAGER_SHOW_LARAVEL_TABLES=false
```

Existing values are preserved and are not duplicated.

## Configuration

Published config file:

```php
return [
    'database_path' => env('SQLITE_MANAGER_DATABASE_PATH', database_path('database.sqlite')),

    'routes' => [
        'enabled' => env('SQLITE_MANAGER_ROUTES_ENABLED', true),
        'prefix' => env('SQLITE_MANAGER_ROUTE_PREFIX', 'sqlite-manager'),
        'middleware' => ['web'],
    ],

    'tables' => [
        'show_laravel_tables' => env('SQLITE_MANAGER_SHOW_LARAVEL_TABLES', false),
        'laravel_table_patterns' => [
            'cache',
            'cache_locks',
            'failed_jobs',
            'job_batches',
            'jobs',
            'migrations',
            'password_reset_tokens',
            'sessions',
            'telescope_*',
        ],
    ],

    'pagination' => [
        'per_page_options' => [5, 10, 25, 50, 100],
        'default_per_page' => 10,
    ],
];
```

## Usage

Open the manager in your browser:

```text
/sqlite-manager
```

If you changed `SQLITE_MANAGER_ROUTE_PREFIX`, use that path instead.

## Features

- Browse SQLite tables and records.
- Search across table columns.
- Create, edit, and delete records.
- Edit and delete records when the table has a single-column primary key.
- Choose visible columns per table.
- Persist UI preferences in cookies.
- Hide Laravel framework tables by default.
- Toggle nullable fields in the create form.
- Responsive Livewire UI with packaged CSS.

## Security

This package exposes database management features through a web route. Protect the route before using it outside local development.

Example:

```php
'routes' => [
    'middleware' => ['web', 'auth'],
],
```

You can disable package routes entirely:

```dotenv
SQLITE_MANAGER_ROUTES_ENABLED=false
```

## Packagist

If this package is published from the `src/` directory, Packagist should use this directory as the package root because it contains `composer.json` and this `README.md`.

## License

The MIT License.
