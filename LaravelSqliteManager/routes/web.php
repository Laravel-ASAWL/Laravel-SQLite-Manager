<?php

declare(strict_types=1);

use Asawl\LaravelSqliteManager\Http\Controllers\SQLiteDatabaseController;
use Asawl\LaravelSqliteManager\Livewire\SQLiteManager;
use Illuminate\Support\Facades\Route;

Route::get('/assets/sqlite-manager.css', [SQLiteDatabaseController::class, 'stylesheet'])->name('assets.stylesheet');

Route::get('/', SQLiteManager::class)->name('index');

Route::get('/tables/{table}', SQLiteManager::class)->name('tables.show');
Route::get('/tables/{table}/create', SQLiteManager::class)->defaults('mode', 'create')->name('tables.create');
Route::get('/tables/{table}/{key}/edit', SQLiteManager::class)->defaults('mode', 'edit')->name('tables.edit');
