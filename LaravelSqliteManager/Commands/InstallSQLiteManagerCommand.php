<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallSQLiteManagerCommand extends Command
{
    protected $description = 'Install Laravel SQLite Manager into the current Laravel project.';

    protected $signature = 'sqlite-manager:install
        {--force : Overwrite the published sqlite-manager.php config file if it already exists.}';

    public function handle(Filesystem $filesystem): int
    {
        $source = __DIR__.'/../config/sqlite-manager.php';
        $target = config_path('sqlite-manager.php');

        if (! $filesystem->exists($source)) {
            $this->components->error('The SQLite Manager config stub could not be found.');

            return self::FAILURE;
        }

        if ($filesystem->exists($target) && ! $this->option('force')) {
            $this->components->warn('The SQLite Manager config file already exists. Use --force to overwrite it.');
        } else {
            $filesystem->ensureDirectoryExists(dirname($target));
            $filesystem->copy($source, $target);

            $this->components->info("Published SQLite Manager config: {$target}");
        }

        $registeredEnvironmentVariables = $this->registerEnvironmentVariables($filesystem);

        if ($registeredEnvironmentVariables > 0) {
            $this->components->info("Registered {$registeredEnvironmentVariables} SQLite Manager environment variables in .env.");
        } else {
            $this->components->info('SQLite Manager environment variables are already registered in .env.');
        }

        $routePrefix = config('sqlite-manager.routes.prefix', 'sqlite-manager');
        $routePrefix = is_string($routePrefix) ? trim($routePrefix, '/') : 'sqlite-manager';

        $this->components->info('Laravel SQLite Manager is installed.');
        $this->line('Open /'.$routePrefix.' to use the web UI.');

        return self::SUCCESS;
    }

    private function registerEnvironmentVariables(Filesystem $filesystem): int
    {
        $environmentPath = base_path('.env');
        $environment = $filesystem->exists($environmentPath) ? $filesystem->get($environmentPath) : '';
        $missing = [];

        foreach ($this->environmentDefaults() as $key => $value) {
            if (preg_match('/^'.preg_quote($key, '/').'=/m', $environment) === 1) {
                continue;
            }

            $missing[$key] = $value;
        }

        if ($missing === []) {
            return 0;
        }

        $prefix = $environment === '' ? '' : (str_ends_with($environment, "\n") ? "\n" : "\n\n");
        $lines = array_map(
            fn (string $key, string $value): string => "{$key}={$value}",
            array_keys($missing),
            $missing,
        );

        $filesystem->put($environmentPath, $environment.$prefix."# Laravel SQLite Manager\n".implode("\n", $lines)."\n");

        return count($missing);
    }

    /** @return array<string, string> */
    private function environmentDefaults(): array
    {
        return [
            'SQLITE_MANAGER_DATABASE_PATH' => '"database/database.sqlite"',
            'SQLITE_MANAGER_ROUTES_ENABLED' => 'true',
            'SQLITE_MANAGER_ROUTE_PREFIX' => 'sqlite-manager',
            'SQLITE_MANAGER_SHOW_LARAVEL_TABLES' => 'false',
        ];
    }
}
