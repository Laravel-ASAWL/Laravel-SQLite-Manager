<?php

declare(strict_types=1);

use Asawl\LaravelSqliteManager\Actions\Audit\LogAction;
use Asawl\LaravelSqliteManager\Actions\Records\BrowseRecordsAction;
use Asawl\LaravelSqliteManager\Actions\Records\ImportRecordsAction;
use Asawl\LaravelSqliteManager\Actions\Records\ListTablesAction;
use Asawl\LaravelSqliteManager\Actions\Records\ManageRecordAction;
use Asawl\LaravelSqliteManager\Actions\Schema\InspectTableAction;
use Asawl\LaravelSqliteManager\Actions\Security\ConnectionManager;
use Asawl\LaravelSqliteManager\Actions\Security\InputValidator;
use Asawl\LaravelSqliteManager\SQLiteManager;
use Asawl\LaravelSqliteManager\SQLiteManagerRepository;
use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->databasePath = storage_path('framework/testing/sqlite-manager/repo.sqlite');

    @unlink($this->databasePath);
    @mkdir(dirname($this->databasePath), 0755, true);

    $pdo = new PDO('sqlite:'.$this->databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NULL, created_at TEXT NULL)');
    $pdo->exec("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
    $pdo->exec("INSERT INTO users (name, email) VALUES ('Bob', 'bob@example.com')");

    config()->set('sqlite-manager.database_path', $this->databasePath);

    $inputValidator = new InputValidator();
    $connectionManager = new ConnectionManager(new SQLiteManager(), new Filesystem());
    $listTables = new ListTablesAction($connectionManager, $inputValidator);
    $browseRecords = new BrowseRecordsAction($connectionManager, $inputValidator, $listTables);
    $manageRecord = new ManageRecordAction($connectionManager, $inputValidator, $listTables);
    $importRecords = new ImportRecordsAction($manageRecord);
    $inspectTable = new InspectTableAction($connectionManager, $inputValidator, $listTables);
    $logAction = new LogAction($connectionManager, $inputValidator);

    $this->repository = new SQLiteManagerRepository(
        $connectionManager,
        $listTables,
        $browseRecords,
        $manageRecord,
        $importRecords,
        $inspectTable,
        $logAction,
        $inputValidator,
    );
});

afterEach(function (): void {
    @unlink($this->databasePath);
    @rmdir(dirname($this->databasePath));
});

test('it lists tables', function (): void {
    $tables = $this->repository->tables();

    expect($tables)->toBeArray()
        ->and($tables)->toContain('users');
});

test('it lists columns', function (): void {
    $columns = $this->repository->columns('users');

    expect($columns)->toBeArray()
        ->and($columns[0])->toMatchArray([
            'name' => 'id',
            'type' => 'INTEGER',
            'primary' => true,
        ]);
});

test('it counts rows', function (): void {
    expect($this->repository->count('users'))->toBe(2);
});

test('it finds a record by primary key', function (): void {
    $record = $this->repository->find('users', '1');

    expect($record)->toBeArray()
        ->and($record['name'])->toBe('Alice')
        ->and($record['email'])->toBe('alice@example.com');
});

test('it returns null when finding a non-existent record', function (): void {
    expect($this->repository->find('users', '999'))->toBeNull();
});

test('it inserts a record', function (): void {
    $key = $this->repository->insert('users', ['name' => 'Charlie', 'email' => 'charlie@example.com']);

    expect($key)->toBeString()
        ->and($this->repository->count('users'))->toBe(3);
});

test('it updates a record', function (): void {
    $this->repository->update('users', '1', ['name' => 'Alice Updated']);

    $record = $this->repository->find('users', '1');
    expect($record['name'])->toBe('Alice Updated');
});

test('it deletes a record', function (): void {
    $this->repository->delete('users', '2');

    expect($this->repository->count('users'))->toBe(1)
        ->and($this->repository->find('users', '2'))->toBeNull();
});

test('it bulk deletes records', function (): void {
    $deleted = $this->repository->deleteMany('users', ['1', '2']);

    expect($deleted)->toBe(2)
        ->and($this->repository->count('users'))->toBe(0);
});

test('it paginates records', function (): void {
    $result = $this->repository->records('users', page: 1, perPage: 1);

    expect($result)->toMatchArray([
        'total' => 2,
        'page' => 1,
        'per_page' => 1,
        'last_page' => 2,
        'from' => 1,
        'to' => 1,
    ])->and($result['rows'])->toHaveCount(1);

    $page2 = $this->repository->records('users', page: 2, perPage: 1);

    expect($page2['rows'])->toHaveCount(1)
        ->and($page2['rows'][0]['name'])->toBe('Bob');
});

test('it searches records', function (): void {
    $result = $this->repository->records('users', search: 'bob@example.com');

    expect($result['total'])->toBe(1)
        ->and($result['rows'][0]['name'])->toBe('Bob');
});

test('it filters records', function (): void {
    $result = $this->repository->records('users', filters: [
        ['column' => 'name', 'operator' => 'equals', 'value' => 'Alice'],
    ]);

    expect($result['total'])->toBe(1)
        ->and($result['rows'][0]['name'])->toBe('Alice');
});

test('it sorts records', function (): void {
    $result = $this->repository->records('users', sortColumn: 'name', sortDirection: 'desc');

    expect($result['rows'][0]['name'])->toBe('Bob');
});

test('it gets table summaries', function (): void {
    $summaries = $this->repository->tableSummaries();

    expect($summaries)->toHaveCount(1)
        ->and($summaries[0])->toMatchArray([
            'name' => 'users',
            'rows' => 2,
            'columns' => 4,
        ]);
});

test('it gets schema information', function (): void {
    $schema = $this->repository->schema('users');

    expect($schema)->toHaveKeys(['columns', 'indexes', 'foreign_keys'])
        ->and($schema['foreign_keys'])->toBeArray();
});

test('it exports rows', function (): void {
    $rows = $this->repository->exportRows('users');

    expect($rows)->toHaveCount(2);
});

test('it exports selected rows', function (): void {
    $rows = $this->repository->exportRows('users', selectedKeys: ['1']);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('Alice');
});

test('it audits operations', function (): void {
    config()->set('sqlite-manager.audit.enabled', true);

    $this->repository->audit('test_action', 'users', '1', ['name' => 'Before'], ['name' => 'After']);

    $pdo = new PDO('sqlite:'.$this->databasePath);
    $log = $pdo->query('SELECT * FROM _lsm_audit_log ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);

    expect($log['action'])->toBe('test_action')
        ->and($log['table_name'])->toBe('users')
        ->and($log['record_key'])->toBe('1');
});

test('it audits batch operations', function (): void {
    config()->set('sqlite-manager.audit.enabled', true);

    $this->repository->auditBatch('batch_test', 'users', [
        ['_key' => '1', 'name' => 'Alice'],
        ['_key' => '2', 'name' => 'Bob'],
    ]);

    $pdo = new PDO('sqlite:'.$this->databasePath);
    $logs = $pdo->query('SELECT action, record_key FROM _lsm_audit_log ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

    expect($logs)->toHaveCount(2)
        ->and($logs[0]['record_key'])->toBe('1')
        ->and($logs[1]['record_key'])->toBe('2');
});
