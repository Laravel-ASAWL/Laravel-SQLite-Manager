<?php

declare(strict_types=1);

use Asawl\LaravelSqliteManager\Livewire\SQLiteManagerLivewire;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function (): void {
    $this->databasePath = storage_path('framework/testing/sqlite-manager/crud.sqlite');

    File::delete($this->databasePath);
    File::ensureDirectoryExists(dirname($this->databasePath));

    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE contacts (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NULL)');
    $pdo->exec("INSERT INTO contacts (name, email) VALUES ('Alice', 'alice@example.com')");

    config()->set('sqlite-manager.database_path', $this->databasePath);
    config()->set('sqlite-manager.security.allowed_environments', ['testing']);
    config()->set('sqlite-manager.security.authorization_gate', null);
    config()->set('sqlite-manager.security.read_only', false);
    config()->set('sqlite-manager.tables.allow', []);
    config()->set('sqlite-manager.tables.deny', []);
    config()->set('sqlite-manager.validation.rules', []);
    config()->set('sqlite-manager.audit.enabled', false);
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

    Livewire::test(SQLiteManagerLivewire::class)
        ->set('showLaravelTables', true)
        ->assertSee('jobs')
        ->assertSee('telescope_entries');
});

test('it can restrict manager access by environment and gate', function (): void {
    config()->set('sqlite-manager.security.allowed_environments', ['production']);

    $this->get('/sqlite-manager')->assertForbidden();

    config()->set('sqlite-manager.security.allowed_environments', ['testing']);
    config()->set('sqlite-manager.security.authorization_gate', 'use-sqlite-manager');
    Gate::define('use-sqlite-manager', fn (): bool => false);

    $this->get('/sqlite-manager')->assertForbidden();
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
        ->assertSee('name')
        ->assertDontSee('<th>id</th>', false)
        ->assertDontSee('<th>email</th>', false);
});

test('it can switch between configured sqlite connections', function (): void {
    $archivePath = storage_path('framework/testing/sqlite-manager/archive.sqlite');

    $pdo = new PDO('sqlite:'.$archivePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE contacts (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NULL)');
    $pdo->exec("INSERT INTO contacts (name, email) VALUES ('Archived Alice', 'archived@example.com')");

    config()->set('sqlite-manager.connections', [
        'default' => $this->databasePath,
        'archive' => $archivePath,
    ]);

    Livewire::test(SQLiteManagerLivewire::class, ['table' => 'contacts'])
        ->assertSee('Alice')
        ->assertDontSee('Archived Alice')
        ->set('connection', 'archive')
        ->assertSee('Archived Alice')
        ->assertDontSee('alice@example.com');
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

test('it filters and sorts table records', function (): void {
    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("INSERT INTO contacts (name, email) VALUES ('Bob', 'bob@example.com')");

    Livewire::test(SQLiteManagerLivewire::class, ['table' => 'contacts'])
        ->set('filters', [['column' => 'name', 'operator' => 'equals', 'value' => 'Bob']])
        ->assertSee('Bob')
        ->assertDontSee('Alice')
        ->set('filters', [])
        ->call('sortBy', 'name')
        ->assertSet('sortColumn', 'name')
        ->assertSet('sortDirection', 'asc')
        ->call('sortBy', 'name')
        ->assertSet('sortDirection', 'desc');
});

test('it applies advanced filters added from the table controls', function (): void {
    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("INSERT INTO contacts (name, email) VALUES ('Bob', 'bob@example.com')");

    Livewire::test(SQLiteManagerLivewire::class, ['table' => 'contacts'])
        ->assertSee('Alice')
        ->assertSee('Bob')
        ->call('addFilter')
        ->assertSet('filters.0.operator', 'contains')
        ->assertSee('Apply filters')
        ->set('filters.0.column', 'name')
        ->set('filters.0.operator', 'equals')
        ->set('filters.0.value', 'Bob')
        ->call('applyFilters')
        ->assertSee('Bob')
        ->assertDontSee('Alice')
        ->call('clearFilters')
        ->assertSet('filters', [])
        ->assertSee('Alice')
        ->assertSee('Bob');
});

test('it can restrict visible tables with allow and deny lists', function (): void {
    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE projects (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

    config()->set('sqlite-manager.tables.allow', ['contacts']);

    $this->get('/sqlite-manager')
        ->assertOk()
        ->assertSee('contacts')
        ->assertDontSee('projects');

    config()->set('sqlite-manager.tables.allow', []);
    config()->set('sqlite-manager.tables.deny', ['contacts']);

    $this->get('/sqlite-manager')
        ->assertOk()
        ->assertDontSee('contacts')
        ->assertSee('projects');
});

test('it can choose visible columns on table data', function (): void {
    $this->get('/sqlite-manager/tables/contacts?cols%5B0%5D=name')
        ->assertOk()
        ->assertSee('name')
        ->assertDontSee('<th>id</th>', false)
        ->assertDontSee('<th>email</th>', false)
        ->assertSee('Alice')
        ->assertDontSee('alice@example.com');
});

test('it links conventional foreign key columns to related records', function (): void {
    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    $pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT)');
    $pdo->exec("INSERT INTO users (name) VALUES ('Author')");
    $pdo->exec("INSERT INTO posts (user_id, title) VALUES (1, 'Post one')");

    $this->get('/sqlite-manager/tables/posts')
        ->assertOk()
        ->assertSee('/sqlite-manager/tables/users/1/edit', false);
});

test('it renders foreign key columns as relationship selects on forms', function (): void {
    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE authors (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    $pdo->exec('CREATE TABLE articles (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, title TEXT, FOREIGN KEY (user_id) REFERENCES authors(id))');
    $pdo->exec("INSERT INTO authors (name) VALUES ('Ada Author')");

    $this->get('/sqlite-manager/tables/articles/create')
        ->assertOk()
        ->assertSee('wire:model="form.user_id"', false)
        ->assertSee('<select', false)
        ->assertSee('#1 - Ada Author');
});

test('it shows schema details for selected tables', function (): void {
    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE UNIQUE INDEX contacts_email_unique ON contacts (email)');

    $this->get('/sqlite-manager/tables/contacts')
        ->assertOk()
        ->assertSee('Schema inspector')
        ->assertSee('contacts_email_unique')
        ->assertSee('UNIQUE');
});

test('it hides soft deleted rows unless enabled', function (): void {
    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, deleted_at TEXT NULL)');
    $pdo->exec("INSERT INTO posts (title, deleted_at) VALUES ('Visible post', NULL)");
    $pdo->exec("INSERT INTO posts (title, deleted_at) VALUES ('Trashed post', '2026-05-28 10:00:00')");

    Livewire::test(SQLiteManagerLivewire::class, ['table' => 'posts'])
        ->assertSee('Visible post')
        ->assertDontSee('Trashed post')
        ->set('showSoftDeleted', true)
        ->assertSee('Trashed post');
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

    Livewire::test(SQLiteManagerLivewire::class, ['table' => 'contacts', 'mode' => 'create'])
        ->assertSee('Show nullable fields')
        ->assertSee('wire:model="form.email"', false)
        ->set('showNullableFields', false)
        ->assertSee('wire:model="form.name"', false)
        ->assertDontSee('wire:model="form.email"', false);
});

test('it can hide nullable fields on the edit form', function (): void {
    $this->get('/sqlite-manager/tables/contacts/1/edit')
        ->assertOk()
        ->assertSee('Show nullable fields')
        ->assertSee('wire:model="form.email"', false);

    Livewire::test(SQLiteManagerLivewire::class, ['table' => 'contacts', 'mode' => 'edit', 'key' => '1'])
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

    $this->withCookie('sqlite_manager_show_nullable_fields', '0')
        ->get('/sqlite-manager/tables/contacts/1/edit')
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

    Livewire::test(SQLiteManagerLivewire::class, ['table' => 'contacts', 'mode' => 'create'])
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

test('it validates form data with configured table rules', function (): void {
    config()->set('sqlite-manager.validation.rules.contacts', [
        'email' => 'required|email',
    ]);

    Livewire::test(SQLiteManagerLivewire::class, ['table' => 'contacts', 'mode' => 'create'])
        ->set('form.name', 'Invalid Email')
        ->set('form.email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['form.email']);
});

test('it updates records from the edit route', function (): void {
    $this->get('/sqlite-manager/tables/contacts/1/edit')
        ->assertOk()
        ->assertSee('Edit record')
        ->assertSee('Alice');

    Livewire::test(SQLiteManagerLivewire::class, ['table' => 'contacts', 'mode' => 'edit', 'key' => '1'])
        ->set('form.name', 'Alice Updated')
        ->set('form.email', 'alice.updated@example.com')
        ->call('save')
        ->assertRedirect(route('sqlite-manager.tables.show', ['table' => 'contacts']));

    $this->get('/sqlite-manager/tables/contacts')
        ->assertOk()
        ->assertSee('Alice Updated')
        ->assertSee('alice.updated@example.com');
});

test('it can audit write operations', function (): void {
    config()->set('sqlite-manager.audit.enabled', true);

    Livewire::test(SQLiteManagerLivewire::class, ['table' => 'contacts', 'mode' => 'edit', 'key' => '1'])
        ->set('form.name', 'Audited Alice')
        ->call('save')
        ->assertRedirect(route('sqlite-manager.tables.show', ['table' => 'contacts']));

    $pdo = new PDO('sqlite:'.$this->databasePath);
    $audit = $pdo->query('SELECT action, table_name FROM _lsm_audit_log')->fetch(PDO::FETCH_ASSOC);

    expect($audit)->toMatchArray(['action' => 'update', 'table_name' => 'contacts']);
});

test('it blocks writes in read only mode', function (): void {
    config()->set('sqlite-manager.security.read_only', true);

    $this->get('/sqlite-manager/tables/contacts')
        ->assertOk()
        ->assertDontSee('Create record')
        ->assertSee('Read only');

    Livewire::test(SQLiteManagerLivewire::class, ['table' => 'contacts'])
        ->call('deleteRecord', '1')
        ->assertSet('error', 'SQLite Manager is running in read-only mode.');

    $this->get('/sqlite-manager/tables/contacts')
        ->assertOk()
        ->assertSee('Alice');
});

test('it blocks actions with configured gates', function (): void {
    config()->set('sqlite-manager.security.gates.import', 'sqlite-manager-import');
    Gate::define('sqlite-manager-import', fn (): bool => false);

    Livewire::test(SQLiteManagerLivewire::class, ['table' => 'contacts'])
        ->set('csvImport', "name,email\nBlocked,blocked@example.com")
        ->call('importCsv')
        ->assertSet('error', 'This SQLite Manager action is not allowed.');
});

test('it imports rows from csv input', function (): void {
    Livewire::test(SQLiteManagerLivewire::class, ['table' => 'contacts'])
        ->set('csvImport', "name,email\nBob,bob@example.com")
        ->call('importCsv')
        ->assertSet('status', '1 records imported.');

    $this->get('/sqlite-manager/tables/contacts')
        ->assertOk()
        ->assertSee('Bob')
        ->assertSee('bob@example.com');
});

test('it can bulk delete selected records', function (): void {
    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("INSERT INTO contacts (name, email) VALUES ('Bob', 'bob@example.com')");

    Livewire::test(SQLiteManagerLivewire::class, ['table' => 'contacts'])
        ->set('selectedRows', ['1'])
        ->call('bulkDelete')
        ->assertSet('status', '1 records deleted.');

    $this->get('/sqlite-manager/tables/contacts')
        ->assertOk()
        ->assertDontSee('Alice')
        ->assertSee('Bob');
});

test('it exports matching rows for csv and json downloads', function (): void {
    $component = Livewire::test(SQLiteManagerLivewire::class, ['table' => 'contacts'])
        ->set('filters', [['column' => 'name', 'operator' => 'equals', 'value' => 'Alice']]);

    expect($component->instance()->exportCurrent('csv'))->toBeInstanceOf(StreamedResponse::class)
        ->and($component->instance()->exportSelected('json'))->toBeInstanceOf(StreamedResponse::class);
});

test('it deletes records from the table route', function (): void {
    Livewire::test(SQLiteManagerLivewire::class, ['table' => 'contacts'])
        ->call('deleteRecord', '1')
        ->assertSet('status', 'Record deleted.');

    $this->get('/sqlite-manager/tables/contacts')
        ->assertOk()
        ->assertDontSee('Alice')
        ->assertSee('No records found.');
});
