<?php

declare(strict_types=1);

use Asawl\LaravelSqliteManager\Http\Controllers\SQLiteManagerController;
use Asawl\LaravelSqliteManager\Livewire\SQLiteManagerLivewire;
use Illuminate\Support\Facades\Route;

Route::get('/assets/sqlite-manager.css', [SQLiteManagerController::class, 'stylesheet'])->name('assets.stylesheet');

Route::get('/', SQLiteManagerLivewire::class)->name('index');
Route::get('/tables/{table}', SQLiteManagerLivewire::class)->name('tables.show');
Route::get('/tables/{table}/create', SQLiteManagerLivewire::class)->defaults('mode', 'create')->name('tables.create');
Route::get('/tables/{table}/{key}/edit', SQLiteManagerLivewire::class)->defaults('mode', 'edit')->name('tables.edit');
