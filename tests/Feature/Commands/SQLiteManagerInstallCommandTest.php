<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->configPath = config_path('sqlite-manager.php');
    $this->envPath = base_path('.env');
    $this->originalConfig = File::exists($this->configPath) ? File::get($this->configPath) : null;
    $this->originalEnv = File::exists($this->envPath) ? File::get($this->envPath) : null;

    File::delete($this->configPath);
    File::put($this->envPath, "APP_NAME=Laravel\n");
});

afterEach(function (): void {
    if (is_string($this->originalConfig)) {
        File::put($this->configPath, $this->originalConfig);
    } else {
        File::delete($this->configPath);
    }

    if (is_string($this->originalEnv)) {
        File::put($this->envPath, $this->originalEnv);

        return;
    }

    File::delete($this->envPath);
});

test('it installs the sqlite manager config into a laravel project', function (): void {
    $this->artisan('sqlite-manager:install')
        ->expectsOutputToContain('Published SQLite Manager config')
        ->expectsOutputToContain('Registered 6 SQLite Manager environment variables')
        ->expectsOutputToContain('Laravel SQLite Manager is installed')
        ->assertSuccessful();

    expect(File::exists($this->configPath))->toBeTrue()
        ->and(File::get($this->configPath))->toContain('SQLITE_MANAGER_DATABASE_PATH')
        ->and(File::get($this->envPath))->toContain('SQLITE_MANAGER_DATABASE_PATH="database/database.sqlite"')
        ->and(File::get($this->envPath))->toContain('SQLITE_MANAGER_ROUTES_ENABLED=true')
        ->and(File::get($this->envPath))->toContain('SQLITE_MANAGER_ROUTE_PREFIX=sqlite-manager')
        ->and(File::get($this->envPath))->toContain('SQLITE_MANAGER_SHOW_LARAVEL_TABLES=false')
        ->and(File::get($this->envPath))->toContain('SQLITE_MANAGER_READ_ONLY=false')
        ->and(File::get($this->envPath))->toContain('SQLITE_MANAGER_AUDIT_ENABLED=true');
});

test('it does not overwrite existing sqlite manager environment variables', function (): void {
    File::put($this->envPath, "APP_NAME=Laravel\nSQLITE_MANAGER_ROUTE_PREFIX=admin/sqlite\n");

    $this->artisan('sqlite-manager:install')
        ->expectsOutputToContain('Registered 5 SQLite Manager environment variables')
        ->assertSuccessful();

    expect(File::get($this->envPath))->toContain('SQLITE_MANAGER_ROUTE_PREFIX=admin/sqlite')
        ->and(mb_substr_count(File::get($this->envPath), 'SQLITE_MANAGER_ROUTE_PREFIX='))->toBe(1);
});

test('it skips environment registration when variables already exist', function (): void {
    File::put($this->envPath, implode("\n", [
        'APP_NAME=Laravel',
        'SQLITE_MANAGER_DATABASE_PATH="custom/database.sqlite"',
        'SQLITE_MANAGER_ROUTES_ENABLED=false',
        'SQLITE_MANAGER_ROUTE_PREFIX=admin/sqlite',
        'SQLITE_MANAGER_SHOW_LARAVEL_TABLES=true',
        'SQLITE_MANAGER_READ_ONLY=true',
        'SQLITE_MANAGER_AUDIT_ENABLED=true',
        '',
    ]));

    $this->artisan('sqlite-manager:install')
        ->expectsOutputToContain('environment variables are already registered')
        ->assertSuccessful();

    expect(mb_substr_count(File::get($this->envPath), 'SQLITE_MANAGER_'))->toBe(6);
});

test('it does not overwrite an existing config without force', function (): void {
    File::put($this->configPath, '<?php return [\'existing\' => true];');

    $this->artisan('sqlite-manager:install')
        ->expectsOutputToContain('config file already exists')
        ->assertSuccessful();

    expect(File::get($this->configPath))->toBe('<?php return [\'existing\' => true];');
});

test('it overwrites an existing config with force', function (): void {
    File::put($this->configPath, '<?php return [\'existing\' => true];');

    $this->artisan('sqlite-manager:install --force')
        ->expectsOutputToContain('Published SQLite Manager config')
        ->assertSuccessful();

    expect(File::get($this->configPath))->toContain('SQLITE_MANAGER_DATABASE_PATH');
});
