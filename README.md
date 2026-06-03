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
SQLITE_MANAGER_AUDIT_ENABLED=true
```

Existing values are preserved and are not duplicated.

## Audit Log

Audit logging is **enabled by default** (since v2.0.0). Every create, update, delete, and bulk-delete operation performed through the web UI is recorded in the audit log table, including the before and after values. Batch imports are also logged.

### How It Works

When an auditable action occurs, the system:

1. Checks `config('sqlite-manager.audit.enabled')` — returns immediately if disabled.
2. Auto-creates the audit table if it does not exist (`CREATE TABLE IF NOT EXISTS`).
3. Inserts a row with the action type, affected table, record key, before/after values (JSON-encoded), and a timestamp.

No separate migration step is required for the table to be created — it is created on first use. However, you can also create a proper Laravel migration for it:

### Migration (Optional)

```bash
php artisan sqlite-manager:create-audit-log-table
```

This copies `create_audit_log_table.php.stub` into `database/migrations` and runs `php artisan migrate`. Use `--force` to overwrite the existing migration file and force the migration run, or `--no-migrate` to copy the file without running migrations.

### Table Schema

| Column | Type | Description |
|---|---|---|
| `id` | INTEGER (PK, autoincrement) | Auto-incrementing identifier |
| `action` | TEXT | `create`, `update`, `delete`, `bulk_delete`, `import` |
| `table_name` | TEXT | The SQLite table that was modified |
| `record_key` | TEXT (nullable) | Primary key value of the affected record (null for bulk operations) |
| `before_values` | TEXT / JSON (nullable) | Record state before the change |
| `after_values` | TEXT / JSON (nullable) | Record state after the change |
| `created_at` | TEXT | Timestamp of the operation |

### Configuration

```php
'audit' => [
    'enabled' => env('SQLITE_MANAGER_AUDIT_ENABLED', true),
    'table' => '_lsm_audit_log',
],
```

| Key | Default | Description |
|---|---|---|
| `enabled` | `true` | Set to `false` to disable all audit logging |
| `table` | `_lsm_audit_log` | The name of the audit log table |

Disable audit logging via `.env`:

```dotenv
SQLITE_MANAGER_AUDIT_ENABLED=false
```

### Logged Operations

| Operation | Action Value | `record_key` | `before_values` | `after_values` |
|---|---|---|---|---|
| Create record | `create` | New record key | `null` | Created attributes |
| Edit record | `update` | Edited record key | Previous values | New values |
| Delete record | `delete` | Deleted record key | Deleted values | `null` |
| Bulk delete | `bulk_delete` | `null` | Keys of deleted records | `null` |
| CSV import | `import` | Per-row key | `null` | Imported row data |

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
        'enabled' => env('SQLITE_MANAGER_AUDIT_ENABLED', true),
        'table' => '_lsm_audit_log',
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
- Search across table columns with advanced column filters (`equals`, `not_equals`, `gt`, `gte`, `lt`, `lte`, `contains`, `starts_with`, `ends_with`, `is_null`, `is_not_null`) and sortable headers.
- Create, edit, and delete records.
- Import rows from CSV input with key trimming, value truncation, and `_extra_N` columns for extra CSV data.
- Optional read-only mode for inspection-only access.
- Per-action Gate authorization for access, view, create, update, delete, bulk delete, export, and import.
- Allowlist and denylist controls for exposed tables.
- Export filtered or selected rows to CSV or JSON.
- Bulk delete selected rows.
- Audit log for create, update, delete, bulk delete, and CSV import operations (enabled by default, configurable).
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
