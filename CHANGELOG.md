# Changelog

All notable changes to `asawl/laravel-sqlite-manager` will be documented in this file.

## 2.0.0 - 2026-05-28

### Added

- Added environment and gate based access controls, read-only mode, and table allow/deny lists.
- Added CSV and JSON exports for current filters and selected rows.
- Added bulk delete actions for selected records.
- Added optional audit logging for create, update, delete, and bulk delete operations.
- Added configurable validation rules for table forms.
- Added advanced column filters and sortable table headers.
- Added conventional `*_id` relationship links to related tables.
- Added expanded JSON/TEXT editing and JSON previews.
- Added GitHub Actions test matrix for Laravel 13 on PHP 8.3 and 8.4.
- Added configurable SQLite connections with an in-UI connection selector.
- Added a schema inspector for table columns, indexes, and foreign keys.
- Added CSV import with a configurable row limit.
- Added soft delete awareness for tables with `deleted_at` columns.
- Added per-action authorization gates and operation row limits.
- Added package-level Testbench scaffolding for standalone package tests.
- Added a publishable audit log migration stub.
- Added `sqlite-manager:test` to copy relationship test migrations into a Laravel project and run them.
- Added dark mode configuration, improved pagination controls, persisted filters, and edit diff hints.

### Changed

- Updated root development dependencies and package dependency constraints from Laravel 12 to Laravel 13.
- Raised the minimum PHP requirement to 8.3 to match Laravel 13.
- Updated package metadata and README references for Laravel 13 support.
- Removed third-party theme wording from the package UI and stylesheet internals.
- Added nullable field visibility control to the edit record form.
- Moved package `config`, `resources`, `routes`, and `tests` directories to the package root.
- Split access control, validation, audit logging, exports, imports, and schema inspection into support services.

## 1.0.0 - 2026-05-28

Initial release.

### Added

- Laravel 12 package auto-discovery support.
- Livewire-powered SQLite manager UI at `/sqlite-manager`.
- Install command: `php artisan sqlite-manager:install`.
- Publishable config file: `config/sqlite-manager.php`.
- Automatic registration of package `.env` variables during installation.
- SQLite table browsing with search, pagination, and visible column selection.
- Record creation, editing, and deletion for tables with a single-column primary key.
- Column-aware create/edit form controls for text, numeric, date, datetime, time, and textarea fields.
- Nullable field visibility toggle for the create form.
- Cookie-backed preferences for page size, Laravel table visibility, visible columns, and nullable field visibility.
- Laravel framework table filtering with configurable table patterns.
- Responsive packaged UI styles served by the package.
