<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager;

use Asawl\LaravelSqliteManager\Commands\SQLiteManagerInstallCommand;
use Asawl\LaravelSqliteManager\Commands\SQLiteManagerTestCommand;
use Asawl\LaravelSqliteManager\Livewire\SQLiteManagerLivewire;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class SQLiteManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->packagePath('config/sqlite-manager.php'), 'sqlite-manager');
    }

    public function boot(): void
    {
        $this->loadViewsFrom($this->packagePath('resources/views'), 'sqlite-manager');
        Blade::anonymousComponentPath($this->packagePath('resources/views/components'), 'sqlite-manager');
        Livewire::component('sqlite-manager.manager', SQLiteManagerLivewire::class);

        $this->registerRoutes();

        $this->publishes([
            $this->packagePath('config/sqlite-manager.php') => config_path('sqlite-manager.php'),
        ], 'sqlite-manager-config');

        $this->publishes([
            $this->packagePath('database/migrations/create_audit_log_table.php.stub') => database_path('migrations/'.date('Y_m_d_His').'_create_audit_log_table.php'),
        ], 'sqlite-manager-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SQLiteManagerInstallCommand::class,
                SQLiteManagerTestCommand::class,
            ]);
        }
    }

    private function registerRoutes(): void
    {
        if (! config('sqlite-manager.routes.enabled', true)) {
            return;
        }

        Route::middleware($this->routeMiddleware())
            ->prefix($this->routePrefix())
            ->name('sqlite-manager.')
            ->group($this->packagePath('routes/web.php'));
    }

    private function packagePath(string $path = ''): string
    {
        return dirname(__DIR__).($path === '' ? '' : '/'.$path);
    }

    /**
     * @return array<int, string>|string
     */
    private function routeMiddleware(): array|string
    {
        $middleware = config('sqlite-manager.routes.middleware', ['web']);

        if (is_string($middleware)) {
            return $middleware;
        }

        if (is_array($middleware)) {
            $middleware = array_values(array_filter($middleware, is_string(...)));

            return $middleware === [] ? ['web'] : $middleware;
        }

        return ['web'];
    }

    private function routePrefix(): string
    {
        $prefix = config('sqlite-manager.routes.prefix', 'sqlite-manager');

        return is_string($prefix) ? $prefix : 'sqlite-manager';
    }
}
