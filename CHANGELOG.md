# Changelog

All notable changes to `asawl/laravel-sqlite-manager` will be documented in this file.

## 2.0.0 - 2026-05-28

### Added

- Added environment and gate based access controls, read-only mode, and table allow/deny lists.
- Added CSV and JSON exports for current filters and selected rows.
- Added bulk delete actions for selected records.
- Added optional audit logging for create, update, delete, and bulk delete operations.
- Added configurable validation rules for table forms.
- Added advanced column filters (`equals`, `not_equals`, `gt`, `gte`, `lt`, `lte`, `contains`, `starts_with`, `ends_with`, `is_null`, `is_not_null`) and sortable table headers.
- Added conventional `*_id` relationship links to related tables.
- Added expanded JSON/TEXT editing and JSON previews.
- Added GitHub Actions test matrix for Laravel 13 on PHP 8.3 and 8.4.
- Added configurable SQLite connections with an in-UI connection selector.
- Added a schema inspector for table columns, indexes, and foreign keys.
- Added CSV import with a configurable row limit, key trimming, value truncation at 65535 chars, and `_extra_N` columns for extra CSV data.
- Added soft delete awareness for tables with `deleted_at` columns.
- Added per-action authorization gates and operation row limits.
- Added package-level Testbench scaffolding for standalone package tests.
- Added `sqlite-manager:create-tests-table` command to copy relationship test migrations into a Laravel project and run them.
- Added `sqlite-manager:create-audit-log-table` command to copy the audit log migration stub and run `php artisan migrate`.
- Added dark mode configuration, improved pagination controls, persisted filters, and edit diff hints.
- Added 19 repository unit tests covering CRUD, pagination, filters, sorting, export, and batch audit.
- Added 8 single-responsibility Action classes: `ConnectionManager`, `InputValidator`, `ListTablesAction`, `BrowseRecordsAction`, `ManageRecordAction`, `ImportRecordsAction`, `InspectTableAction`, `LogAction`.
- Added PRAGMA query result caching per database path within the same request.
- Added batch audit logging for CSV imports (single batch entry instead of N individual entries).
- Added Livewire traits: `WithPreferences`, `WithFormHelpers`, `WithFilters`.
- Added nullable field visibility control to the edit record form.
- Added 7 new tests: `SQLiteManagerCreateAuditLogTableCommandTest` (3 tests: copy, already-exists, force overwrite) and `ConnectionManagerTest` (4 tests: connection names, validation, edge cases).

### Changed

- Refactored `SQLiteManagerRepository` to delegate all operations to Action classes via composition.
- Replaced explicit `resolve()` calls in the Livewire component with constructor injection via `boot()`.
- Injected `ConnectionManager` into the Livewire component; `connectionNames()` and `validConnection()` now delegate to it instead of duplicating the logic.
- Delegated CSV import loop in `Livewire::importCsv()` to `ImportRecordsAction::import()` via `repository->importRows()`, eliminating duplicated row sanitization and limit checking.
- Delegated `InspectTableAction::columns()` to `ListTablesAction::columns()`, eliminating duplicated PRAGMA `table_info` parsing.
- Improved type safety across the repository, Livewire component, and Action classes.
- Updated root development dependencies and package dependency constraints from Laravel 12 to Laravel 13.
- Raised the minimum PHP requirement to 8.3 to match Laravel 13.
- Updated package metadata and README references for Laravel 13 support.
- Removed third-party theme wording from the package UI and stylesheet internals.
- Moved package `config`, `resources`, `routes`, and `tests` directories to the package root.
- Split access control, validation, audit logging, exports, imports, and schema inspection into support services.

### Fixed

- **Critical**: `Livewire/WithFilters.php::activeFilters()` now permits `is_null`/`is_not_null` operators through even when the filter value is empty, making these operators actually work in the UI.
- **High**: `SQLiteManagerRepository::find()` now validates the primary key with `validatePrimaryKey()`, matching the behavior of `update()` and `delete()`.
- **Medium**: Fixed `_key` spread collision in `ImportRecordsAction::import()` and `Livewire::importCsv()` — `_key` is now set after the row spread, preventing a CSV column named `_key` from overwriting the audit key.
- **Medium**: `DataExporter` now throws `RuntimeException` when `fopen('php://output')` fails, instead of silently returning an empty 200 response.
- **Low**: `LogAction` now wraps `json_encode` in a `safeJsonEncode()` method that catches `JsonException` and falls back to an error placeholder, preventing crashes on BLOB/binary audit data.
- **Low**: `CsvImporter` now checks the `fwrite()` return value and throws `RuntimeException` on write failure, preventing silent data truncation.
- **Low**: `CsvImporter` now preserves extra CSV columns (beyond the header count) as `_extra_N` columns instead of silently dropping them.
- **Info**: `BrowseRecordsAction` now returns `last_page = 0` when `total = 0`, eliminating misleading "Page 1 of 1" for empty tables.

### Removed

- Removed dead `formValidationRules()` method from the Livewire component.
- Removed unused `integer()` method from the repository.
- Removed publishable audit log migration from `sqlite-manager:install` command (replaced by `sqlite-manager:create-audit-log-table` command).
- Removed dead method `SQLiteManagerRepository::databaseExists()` — never called.
- Removed dead method `SQLiteManagerRepository::primaryKey()` — never called.
- Removed dead method `SQLiteManagerRepository::indexes()` — unused in production code.
- Removed dead method `SQLiteManagerRepository::foreignKeys()` — unused in production code.
- Removed dead method `ConnectionManager::clearConnection()` — never called.
- Removed dead method `InputValidator::validateColumnName()` — never called.
- Removed dead method `InputValidator::assertNumeric()` — never called.
- Removed dead method `ImportRecordsAction::parseCsv()` — never called.
- Removed unused config key `audit.migration` from `config/sqlite-manager.php`.
- Removed duplicate config key `security.limits.max_export_rows` — use `exports.max_rows` instead.
- Removed dead CSS class `.empty-state` (`.empty` retained).
- Removed unused CSS custom property `--manager-success`.
- Removed Bootstrap 5 JS bundle from `layouts/app.blade.php` — no Bootstrap JS components were used.
- Removed bare `wire:submit.prevent` without handler from `manager.blade.php` — search is handled by debounced model binding.
- Removed redundant `$showLaravelTables` view variable from `Livewire::render()` — already a public Livewire property, never accessed as `$showLaravelTables` in Blade.
- Removed unused `configuredPath()` and `importLimit()` private methods from Livewire component.

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
