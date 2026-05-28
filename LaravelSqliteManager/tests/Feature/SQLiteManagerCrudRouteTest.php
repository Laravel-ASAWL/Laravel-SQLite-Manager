<?php

declare(strict_types=1);

use Asawl\LaravelSqliteManager\Livewire\SQLiteManager;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->databasePath = storage_path('framework/testing/sqlite-manager/crud.sqlite');

    File::delete($this->databasePath);
    File::ensureDirectoryExists(dirname($this->databasePath));

    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE contacts (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NULL)');
    $pdo->exec("INSERT INTO contacts (name, email) VALUES ('Alice', 'alice@example.com')");

    config()->set('sqlite-manager.database_path', $this->databasePath);
});

afterEach(function (): void {
    File::delete($this->databasePath);
    File::deleteDirectory(dirname($this->databasePath));
});

test('it renders the sqlite manager dashboard', function (): void {
    $this->get('/sqlite-manager')
        ->assertOk()
        ->assertSee('SQLite Manager')
        ->assertSee('Monitor SQLite tables')
        ->assertSee('Browse records')
        ->assertSee('Choose columns')
        ->assertSee('Filter framework tables')
        ->assertSee('Show Laravel tables')
        ->assertDontSee('1 rows, 3 columns');
});

test('it can show laravel framework tables on the dashboard', function (): void {
    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, queue TEXT)');
    $pdo->exec('CREATE TABLE telescope_entries (uuid TEXT PRIMARY KEY, type TEXT)');

    $this->get('/sqlite-manager')
        ->assertOk()
        ->assertSee('contacts')
        ->assertSee('Show Laravel tables')
        ->assertDontSee('jobs')
        ->assertDontSee('telescope_entries');

    Livewire::test(SQLiteManager::class)
        ->set('showLaravelTables', true)
        ->assertSee('jobs')
        ->assertSee('telescope_entries');
});

test('it reads sqlite manager preferences from cookies', function (): void {
    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, queue TEXT)');

    $this->withCookie('sqlite_manager_per_page', '10')
        ->withCookie('sqlite_manager_show_laravel_tables', '1')
        ->withCookie('sqlite_manager_columns_'.sha1('contacts'), json_encode(['name'], JSON_THROW_ON_ERROR))
        ->get('/sqlite-manager/tables/contacts')
        ->assertOk()
        ->assertSee('jobs')
        ->assertSee('<option value="10" selected>10</option>', false)
        ->assertSee('<th>name</th>', false)
        ->assertDontSee('<th>id</th>', false)
        ->assertDontSee('<th>email</th>', false);
});

test('it lists table records', function (): void {
    $this->get('/sqlite-manager/tables/contacts')
        ->assertOk()
        ->assertSee('Alice')
        ->assertSee('alice@example.com')
        ->assertSee('Create record');
});

test('it searches records across table columns', function (): void {
    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("INSERT INTO contacts (name, email) VALUES ('Bob', 'bob@example.com')");

    $this->get('/sqlite-manager/tables/contacts?q=bob@example.com')
        ->assertOk()
        ->assertSee('Bob')
        ->assertSee('bob@example.com')
        ->assertDontSee('Alice')
        ->assertDontSee('alice@example.com');
});

test('it can choose visible columns on table data', function (): void {
    $this->get('/sqlite-manager/tables/contacts?cols%5B0%5D=name')
        ->assertOk()
        ->assertSee('<th>name</th>', false)
        ->assertDontSee('<th>id</th>', false)
        ->assertDontSee('<th>email</th>', false)
        ->assertSee('Alice')
        ->assertDontSee('alice@example.com');
});

test('it paginates table records with configurable page sizes', function (): void {
    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    for ($index = 2; $index <= 8; $index++) {
        $pdo->exec("INSERT INTO contacts (name, email) VALUES ('Contact {$index}', 'contact{$index}@example.com')");
    }

    $this->get('/sqlite-manager/tables/contacts?per_page=5')
        ->assertOk()
        ->assertSee('<option value="5" selected>5</option>', false)
        ->assertSee('Alice')
        ->assertSee('Contact 5')
        ->assertDontSee('Contact 6')
        ->assertSee('First')
        ->assertSee('Last');

    $this->get('/sqlite-manager/tables/contacts?per_page=5&page=2')
        ->assertOk()
        ->assertSee('First')
        ->assertSee('Previous')
        ->assertSee('Contact 6')
        ->assertSee('Contact 8')
        ->assertDontSee('Alice');

    $this->get('/sqlite-manager/tables/contacts?per_page=5&q=Contact&cols%5B0%5D=name')
        ->assertOk()
        ->assertSee('Last')
        ->assertSee('Contact 2')
        ->assertDontSee('contact2@example.com');
});

test('it reads page size options from configuration', function (): void {
    config()->set('sqlite-manager.pagination.per_page_options', [3, 6, 10]);
    config()->set('sqlite-manager.pagination.default_per_page', 10);

    $this->get('/sqlite-manager/tables/contacts')
        ->assertOk()
        ->assertSee('<option value="3" >3</option>', false)
        ->assertSee('<option value="6" >6</option>', false)
        ->assertSee('<option value="10" selected>10</option>', false)
        ->assertDontSee('<option value="25"', false);
});

test('it renders form controls based on sqlite column types', function (): void {
    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE typed_fields (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255), notes TEXT, age INTEGER, amount REAL, birthday DATE, starts_at DATETIME, alarm_time TIME, payload BLOB)');

    $this->get('/sqlite-manager/tables/typed_fields/create')
        ->assertOk()
        ->assertSee('type="text"', false)
        ->assertSee('type="number"', false)
        ->assertSee('step="any"', false)
        ->assertSee('type="date"', false)
        ->assertSee('type="datetime-local"', false)
        ->assertSee('type="time"', false)
        ->assertSee('wire:model="form.notes"', false)
        ->assertSee('wire:model="form.payload"', false)
        ->assertSee('<textarea', false)
        ->assertSee('VARCHAR(255)')
        ->assertSee('TEXT')
        ->assertSee('INTEGER')
        ->assertSee('BLOB');
});

test('it can hide nullable fields on the create form', function (): void {
    $this->get('/sqlite-manager/tables/contacts/create')
        ->assertOk()
        ->assertSee('Show nullable fields')
        ->assertSee('wire:model="form.email"', false);

    Livewire::test(SQLiteManager::class, ['table' => 'contacts', 'mode' => 'create'])
        ->assertSee('Show nullable fields')
        ->assertSee('wire:model="form.email"', false)
        ->set('showNullableFields', false)
        ->assertSee('wire:model="form.name"', false)
        ->assertDontSee('wire:model="form.email"', false);
});

test('it reads nullable field visibility from cookies', function (): void {
    $this->withCookie('sqlite_manager_show_nullable_fields', '0')
        ->get('/sqlite-manager/tables/contacts/create')
        ->assertOk()
        ->assertSee('Show nullable fields')
        ->assertSee('wire:model="form.name"', false)
        ->assertDontSee('wire:model="form.email"', false);
});

test('it formats datetime columns with short date and time', function (): void {
    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE events (id INTEGER PRIMARY KEY AUTOINCREMENT, starts_at DATETIME)');
    $pdo->exec("INSERT INTO events (starts_at) VALUES ('2026-05-27 14:05:09')");

    $this->get('/sqlite-manager/tables/events')
        ->assertOk()
        ->assertSee('27/05/2026 14:05:09')
        ->assertDontSee('2026-05-27 14:05:09');
});

test('it formats date columns as year month day', function (): void {
    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE birthdays (id INTEGER PRIMARY KEY AUTOINCREMENT, birthday DATE)');
    $pdo->exec("INSERT INTO birthdays (birthday) VALUES ('05/27/2026')");

    $this->get('/sqlite-manager/tables/birthdays')
        ->assertOk()
        ->assertSee('2026-05-27')
        ->assertDontSee('05/27/2026');
});

test('it creates records from the table form route', function (): void {
    $this->get('/sqlite-manager/tables/contacts/create')
        ->assertOk()
        ->assertSee('Create record');

    Livewire::test(SQLiteManager::class, ['table' => 'contacts', 'mode' => 'create'])
        ->set('form.id', '')
        ->set('form.name', 'Bob')
        ->set('form.email', 'bob@example.com')
        ->call('save')
        ->assertRedirect(route('sqlite-manager.tables.show', ['table' => 'contacts']));

    $this->get('/sqlite-manager/tables/contacts')
        ->assertOk()
        ->assertSee('Bob')
        ->assertSee('bob@example.com');
});

test('it updates records from the edit route', function (): void {
    $this->get('/sqlite-manager/tables/contacts/1/edit')
        ->assertOk()
        ->assertSee('Edit record')
        ->assertSee('Alice');

    Livewire::test(SQLiteManager::class, ['table' => 'contacts', 'mode' => 'edit', 'key' => '1'])
        ->set('form.name', 'Alice Updated')
        ->set('form.email', 'alice.updated@example.com')
        ->call('save')
        ->assertRedirect(route('sqlite-manager.tables.show', ['table' => 'contacts']));

    $this->get('/sqlite-manager/tables/contacts')
        ->assertOk()
        ->assertSee('Alice Updated')
        ->assertSee('alice.updated@example.com');
});

test('it deletes records from the table route', function (): void {
    Livewire::test(SQLiteManager::class, ['table' => 'contacts'])
        ->call('deleteRecord', '1')
        ->assertSet('status', 'Record deleted.');

    $this->get('/sqlite-manager/tables/contacts')
        ->assertOk()
        ->assertDontSee('Alice')
        ->assertSee('No records found.');
});
