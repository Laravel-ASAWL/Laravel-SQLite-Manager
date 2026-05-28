# Changelog

All notable changes to `asawl/laravel-sqlite-manager` will be documented in this file.

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
