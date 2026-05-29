<?php

declare(strict_types=1);

use Asawl\LaravelSqliteManager\SQLiteManager;

test('it resolves relative paths from the laravel base path', function (): void {
    $manager = new SQLiteManager;

    expect($manager->resolvePath('database/database.sqlite'))->toBe(base_path('database/database.sqlite'));
});
