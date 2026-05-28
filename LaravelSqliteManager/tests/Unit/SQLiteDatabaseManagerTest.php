<?php

declare(strict_types=1);

use Asawl\LaravelSqliteManager\SQLiteDatabaseManager;
use Illuminate\Filesystem\Filesystem;

test('it resolves relative paths from the laravel base path', function (): void {
    $manager = new SQLiteDatabaseManager(new Filesystem());

    expect($manager->resolvePath('database/database.sqlite'))->toBe(base_path('database/database.sqlite'));
});
