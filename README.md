# Laravel SQLite Manager

Livewire-powered SQLite database manager for Laravel 13 applications.

This package adds a small web UI for browsing and editing records in a configured SQLite database. It is intended for local development, internal tooling, and controlled admin environments.

## Requirements

- PHP 8.3 or higher
- Laravel 13
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
SQLITE_MANAGER_READ_ONLY=false
SQLITE_MANAGER_AUDIT_ENABLED=false
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

    'security' => [
        'allowed_environments' => ['local', 'testing'],
        'authorization_gate' => null,
        'read_only' => env('SQLITE_MANAGER_READ_ONLY', false),
    ],

    'tables' => [
        'show_laravel_tables' => env('SQLITE_MANAGER_SHOW_LARAVEL_TABLES', false),
        'allow' => [],
        'deny' => [],
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

    'validation' => [
        'rules' => [],
    ],

    'audit' => [
        'enabled' => env('SQLITE_MANAGER_AUDIT_ENABLED', false),
        'table' => 'laravel_sqlite_manager_audit_log',
    ],

    'exports' => [
        'max_rows' => 5000,
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
- Search across table columns with advanced column filters and sortable headers.
- Create, edit, and delete records.
- Optional read-only mode for inspection-only access.
- Allowlist and denylist controls for exposed tables.
- Export filtered or selected rows to CSV or JSON.
- Bulk delete selected rows.
- Optional audit log for create, update, delete, and bulk delete operations.
- Configurable validation rules per table column.
- Conventional `*_id` relationship links to related tables.
- Edit and delete records when the table has a single-column primary key.
- Choose visible columns per table.
- Persist UI preferences in cookies.
- Hide Laravel framework tables by default.
- Toggle nullable fields in create and edit forms.
- Expanded JSON/TEXT editing and JSON previews.
- Responsive Livewire UI with packaged CSS.

## Security

This package exposes database management features through a web route. Protect the route before using it outside local development.

Example:

```php
'routes' => [
    'middleware' => ['web', 'auth'],
],

'security' => [
    'authorization_gate' => 'use-sqlite-manager',
    'read_only' => true,
],

'tables' => [
    'allow' => ['users', 'orders'],
    'deny' => ['password_reset_tokens'],
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
