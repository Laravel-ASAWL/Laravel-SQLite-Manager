# Laravel SQLite Manager

Livewire-powered SQLite database manager for Laravel 13 applications.

Administer SQLite databases from a Laravel web UI — browse tables, search with filters, create and edit records, import CSV data, export rows, inspect schemas, and toggle Laravel framework tables. Built with single-responsibility Action classes and Livewire 3.

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

## Audit Table

The audit log table can be created using the dedicated Artisan command:

```bash
php artisan sqlite-manager:create-audit-log-table
```

The command copies `create_audit_log_table.php.stub` into `database/migrations` and runs `php artisan migrate`. Use `--force` to overwrite the existing migration file and force the migration run, or `--no-migrate` to copy the file without running migrations.

The audit table is created with the name configured in `config('sqlite-manager.audit.table', '_lsm_audit_log')`.

## Test Data

You can publish and run relationship test migrations for validating `*_id` links and relationship selects:

```bash
php artisan sqlite-manager:create-tests-table
```

The command copies package test migrations into `database/migrations` and runs `php artisan migrate`. Use `--force` to overwrite existing migration files and force the migration run, or `--no-migrate` to only copy the files.

The test schema creates users, posts, and comments where `posts.user_id`, `comments.user_id`, and `comments.post_id` are real SQLite foreign keys.

## Configuration

Published config file:

```php
return [
    'database_path' => env('SQLITE_MANAGER_DATABASE_PATH', database_path('database.sqlite')),

    'connections' => [
        'default' => env('SQLITE_MANAGER_DATABASE_PATH', database_path('database.sqlite')),
    ],

    'routes' => [
        'enabled' => env('SQLITE_MANAGER_ROUTES_ENABLED', true),
        'prefix' => env('SQLITE_MANAGER_ROUTE_PREFIX', 'sqlite-manager'),
        'middleware' => ['web'],
    ],

    'security' => [
        'allowed_environments' => ['local', 'testing'],
        'authorization_gate' => null,
        'read_only' => env('SQLITE_MANAGER_READ_ONLY', false),
        'gates' => [
            'access' => null,
            'view' => null,
            'create' => null,
            'update' => null,
            'delete' => null,
            'bulk_delete' => null,
            'export' => null,
            'import' => null,
        ],
        'limits' => [
            'max_delete_rows' => 100,
            'max_export_rows' => 5000,
            'max_page_size' => 100,
        ],
    ],

    'tables' => [
        'show_laravel_tables' => env('SQLITE_MANAGER_SHOW_LARAVEL_TABLES', false),
        'show_soft_deleted' => false,
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
        'table' => '_lsm_audit_log',
        'migration' => true,
    ],

    'exports' => [
        'max_rows' => 5000,
    ],

    'imports' => [
        'max_rows' => 500,
    ],

    'ui' => [
        'theme' => 'auto',
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
- Switch between configured SQLite database files.
- Search across table columns with advanced column filters and sortable headers.
- Create, edit, and delete records.
- Import rows from CSV input.
- Optional read-only mode for inspection-only access.
- Per-action Gate authorization for access, view, create, update, delete, bulk delete, export, and import.
- Allowlist and denylist controls for exposed tables.
- Export filtered or selected rows to CSV or JSON.
- Bulk delete selected rows.
- Optional audit log for create, update, delete, and bulk delete operations with batch import support.
- Artisan command to create the audit log table (`sqlite-manager:create-audit-log-table`).
- Schema inspector for table columns, indexes, and foreign keys.
- Soft delete awareness for tables with `deleted_at` columns.
- Configurable validation rules per table column.
- Conventional `*_id` relationship links to related tables.
- Edit and delete records when the table has a single-column primary key.
- Choose visible columns per table.
- Persist UI preferences in cookies.
- Persist advanced filters and soft delete visibility in cookies.
- Hide Laravel framework tables by default.
- Toggle nullable fields in create and edit forms.
- View original field values while editing changed records.
- Expanded JSON/TEXT editing and JSON previews.
- Responsive Livewire UI with packaged CSS and configurable light/dark/auto theme.

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
