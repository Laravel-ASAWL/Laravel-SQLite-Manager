<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->databasePath = storage_path('framework/testing/sqlite-manager/route.sqlite');

    File::delete($this->databasePath);
    File::deleteDirectory(dirname($this->databasePath));

    config()->set('sqlite-manager.database_path', $this->databasePath);
});

afterEach(function (): void {
    File::delete($this->databasePath);
    File::deleteDirectory(dirname($this->databasePath));
});

test('it shows the sqlite manager page', function (): void {
    $this->get('/sqlite-manager')
        ->assertOk()
        ->assertSee('sqlite-manager/assets/sqlite-manager.css', false)
        ->assertSee('SQLite Database Manager')
        ->assertSee('Missing')
        ->assertSee($this->databasePath);
});

test('it serves the sqlite manager stylesheet', function (): void {
    $this->get('/sqlite-manager/assets/sqlite-manager.css')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8')
        ->assertSee('.studio-shell', false)
        ->assertSee('.column-options', false);
});
