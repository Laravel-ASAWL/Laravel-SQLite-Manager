<?php

declare(strict_types=1);

use Asawl\LaravelSqliteManager\Actions\Security\ConnectionManager;
use Asawl\LaravelSqliteManager\SQLiteManager;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->connectionManager = new ConnectionManager(new SQLiteManager(), new Filesystem());
});

test('it returns connection names from config', function (): void {
    config()->set('sqlite-manager.connections', ['default' => database_path('database.sqlite'), 'custom' => database_path('custom.sqlite')]);

    $names = $this->connectionManager->connectionNames();

    expect($names)->toBe(['default', 'custom']);
});

test('it validates a connection name', function (): void {
    config()->set('sqlite-manager.connections', ['default' => database_path('database.sqlite'), 'custom' => database_path('custom.sqlite')]);

    expect($this->connectionManager->validConnection('custom'))->toBe('custom')
        ->and($this->connectionManager->validConnection('unknown'))->toBe('default');
});

test('it returns default connection names when config is not an array', function (): void {
    config()->set('sqlite-manager.connections', null);

    $names = $this->connectionManager->connectionNames();

    expect($names)->toBe(['default']);
});

test('it returns default connection names when config has no names', function (): void {
    config()->set('sqlite-manager.connections', []);

    $names = $this->connectionManager->connectionNames();

    expect($names)->toBe(['default']);
});
