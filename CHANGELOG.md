# Changelog

All notable changes to `asawl/laravel-sqlite-manager` will be documented in this file.

## 1.0.2 - 2026-06-03

### Added

- Dedicated audit log module with a new Livewire component (`AuditLogLivewire`), independent from the generic table browser, accessible via a new route `sqlite-manager.audit` and sidebar link.
- Audit log entry filters by action type and table name with auto-populated dropdowns from existing entries.
- Detail modal for audit log entries showing before/after values side by side with pretty-printed JSON, accessible from both the audit module and the generic table browser.
- Colored action badges in the audit log table (`create` green, `update` blue, `delete` red, `bulk_delete` orange, `import` purple).
- Automatic `created_at`/`updated_at`/`deleted_at` column detection in the generic `display-value` component, formatting timestamps even when the SQLite column type is `TEXT`.
- Test table filtering: new `showTestTables` Livewire property with `#[Url]` attribute, cookie persistence, and config-driven default (`sqlite-manager.tables.show_test_tables`).
- Configurable `test_table_patterns` key (`_lsm_test_*` by default) in `config/sqlite-manager.php`.
- `isTestTable()` method in `ListTablesAction` with glob-style pattern matching via `testTablePatterns()`.
- "Show test tables" toggle switch in the Manager tools dashboard panel.
- `Show Audit Log` tile in the Manager tools dashboard for quick access to the audit module.
- Query parameter hydration for `?test_tables` in `hydrateQueryParameters()`, mirroring the `?laravel_tables` handling.

### Changed

- Audit logging is now **enabled by default** (`audit.enabled` default changed from `false` to `true`); the install command now writes `SQLITE_MANAGER_AUDIT_ENABLED=true` to `.env`.
- The `display-value` Blade component now supports a `badge` prop and a `column` prop for value-colorized badges and column-name-aware date formatting.
- Sidebar reorganized: the "Audit Log" link is now pinned at the top of the sidebar, above the tables list.
- `ListTablesAction::all()` and `summaries()` now accept an `$includeTestTables` parameter (default `false`) to control test table visibility.
- `SQLiteManagerRepository::tableSummaries()` and `tables()` pass through the `$includeTestTables` parameter to the action layer.
- Test tables are now hidden by default when no test tables config is published (empty pattern list).

### Fixed

- Sidebar table list now scrolls properly on desktop — replaced flex-based sizing (which broke on `<details>` elements) with explicit `max-height: calc(100vh - 200px)` and `overflow-y: auto`.
- Removed redundant double border between sidebar sections — `.explorer-features` no longer has `border-bottom` (the tables summary `border-top` provides the single separator).
- Normalized sidebar spacing: `.explorer-features` now uses `padding: 10px 10px 0` (mobile) and `padding: 14px 12px 0` (desktop), consistent with `.explorer-section`.
- Fixed excessive gap between Audit Log link and Tables summary by removing the nav's bottom padding (only the item's `margin-bottom: 4px` remains).
- Added `x-cloak` CSS rule to prevent Alpine modal flicker.
- Fixed Pint formatting in the audit migration stub (`declare(strict_types=1)`, `new class()` parentheses, brace positioning).

## 1.0.1 - 2026-05-30

### Added

- Environment and gate based access controls, read-only mode, and table allow/deny lists.
- CSV and JSON exports for current filters and selected rows.
- Bulk delete actions for selected records.
- Configurable validation rules for table forms.
- Advanced column filters (`equals`, `not_equals`, `gt`, `gte`, `lt`, `lte`, `contains`, `starts_with`, `ends_with`, `is_null`, `is_not_null`) and sortable table headers.
- Conventional `*_id` relationship links to related tables.
- Expanded JSON/TEXT editing and JSON previews.
- GitHub Actions test matrix for Laravel 13 on PHP 8.3 and 8.4.
- Configurable SQLite connections with an in-UI connection selector.
- Schema inspector for table columns, indexes, and foreign keys.
- CSV import with configurable row limit, key trimming, value truncation at 65535 chars, and `_extra_N` columns for extra CSV data.
- Soft delete awareness for tables with `deleted_at` columns.
- Per-action authorization gates and operation row limits.
- Package-level Testbench scaffolding for standalone package tests.
- `sqlite-manager:create-tests-table` and `sqlite-manager:create-audit-log-table` Artisan commands.
- Dark mode configuration, improved pagination controls, persisted filters, and edit diff hints.
- 19 repository unit tests covering CRUD, pagination, filters, sorting, export, and batch audit.
- 8 single-responsibility Action classes: `ConnectionManager`, `InputValidator`, `ListTablesAction`, `BrowseRecordsAction`, `ManageRecordAction`, `ImportRecordsAction`, `InspectTableAction`, `LogAction`.
- PRAGMA query result caching per database path within the same request.
- Batch audit logging for CSV imports (single batch entry instead of N individual entries).
- Livewire traits: `WithPreferences`, `WithFormHelpers`, `WithFilters`.
- Nullable field visibility control to the edit record form.
- 7 new tests: `SQLiteManagerCreateAuditLogTableCommandTest` (3 tests) and `ConnectionManagerTest` (4 tests).

### Changed

- Refactored `SQLiteManagerRepository` to delegate all operations to Action classes via composition.
- Replaced explicit `resolve()` calls in the Livewire component with constructor injection via `boot()`.
- Injected `ConnectionManager` into the Livewire component; `connectionNames()` and `validConnection()` now delegate to it.
- Delegated CSV import loop to `ImportRecordsAction::import()` via `repository->importRows()`.
- Delegated `InspectTableAction::columns()` to `ListTablesAction::columns()`.
- Updated package dependency constraints from Laravel 12 to Laravel 13; raised minimum PHP to 8.3.
- Moved package `config`, `resources`, `routes`, and `tests` directories to the package root.

### Fixed

- **Critical**: `Livewire/WithFilters.php::activeFilters()` now permits `is_null`/`is_not_null` operators through with empty value.
- **High**: `SQLiteManagerRepository::find()` now validates the primary key with `validatePrimaryKey()`.
- **Medium**: Fixed `_key` spread collision in `ImportRecordsAction::import()` — `_key` is set after the row spread.
- **Medium**: `DataExporter` now throws `RuntimeException` on `fopen('php://output')` failure.
- **Low**: `LogAction::safeJsonEncode()` catches `JsonException` and falls back to an error placeholder.
- **Low**: `CsvImporter` checks `fwrite()` return value and throws `RuntimeException` on write failure.
- **Low**: `CsvImporter` preserves extra CSV columns as `_extra_N` columns.
- **Info**: `BrowseRecordsAction` returns `last_page = 0` when `total = 0`.

### Removed

- Dead `formValidationRules()` method from the Livewire component.
- Unused `integer()` method from the repository.
- Publishable audit log migration from `sqlite-manager:install` command.
- Dead methods: `SQLiteManagerRepository::databaseExists()`, `::primaryKey()`, `::indexes()`, `::foreignKeys()`.
- Dead methods: `ConnectionManager::clearConnection()`, `InputValidator::validateColumnName()`, `InputValidator::assertNumeric()`, `ImportRecordsAction::parseCsv()`.
- Unused config key `audit.migration` and duplicate key `security.limits.max_export_rows`.
- Dead CSS class `.empty-state` and unused custom property `--manager-success`.
- Bootstrap 5 JS bundle from `layouts/app.blade.php`.
- Bare `wire:submit.prevent` without handler from `manager.blade.php`.
- Redundant `$showLaravelTables` view variable from `Livewire::render()`.
- Unused `configuredPath()` and `importLimit()` private methods from the Livewire component.

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
