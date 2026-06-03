<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Actions\Security;

use Asawl\LaravelSqliteManager\SQLiteManager;
use Illuminate\Filesystem\Filesystem;
use PDO;
use RuntimeException;

class ConnectionManager
{
    private ?PDO $pdo = null;

    private ?string $activePath = null;

    public function __construct(
        private readonly SQLiteManager $SQLiteManager,
        private readonly Filesystem $filesystem,
    ) {}

    public function databasePath(?string $connection = null): string
    {
        if ($connection === null) {
            $connection = config('sqlite-manager.active_connection', 'default');
        }

        if (! is_string($connection)) {
            $connection = 'default';
        }

        if ($connection === 'default') {
            $path = config('sqlite-manager.database_path', database_path('database.sqlite'));
        } else {
            $connections = config('sqlite-manager.connections', []);
            $path = is_array($connections) && array_key_exists($connection, $connections)
                ? $connections[$connection]
                : null;
        }

        return $this->SQLiteManager->resolvePath(is_string($path) ? $path : database_path('database.sqlite'));
    }

    public function databaseExists(?string $connection = null): bool
    {
        return $this->filesystem->exists($this->databasePath($connection));
    }

    /** @return list<string> */
    public function connectionNames(): array
    {
        $connections = config('sqlite-manager.connections', ['default' => $this->databasePath('default')]);

        if (! is_array($connections)) {
            return ['default'];
        }

        $names = array_values(array_filter(array_keys($connections), is_string(...)));

        return $names === [] ? ['default'] : $names;
    }

    public function validConnection(string $connection): string
    {
        return in_array($connection, $this->connectionNames(), true) ? $connection : 'default';
    }

    public function pdo(?string $connection = null): PDO
    {
        $path = $this->databasePath($connection);

        if ($this->pdo instanceof PDO && $this->activePath === $path) {
            return $this->pdo;
        }

        if (! $this->filesystem->exists($path)) {
            throw new RuntimeException('The SQLite database file does not exist: '.$path);
        }

        $this->pdo = new PDO('sqlite:'.$path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->activePath = $path;

        return $this->pdo;
    }

    public function assertTableExists(string $table, ?string $connection = null): void
    {
        $tables = $this->fetchTableNames($connection);

        if (! in_array($table, $tables, true)) {
            throw new RuntimeException("The SQLite table does not exist: {$table}");
        }
    }

    /** @return list<string> */
    public function fetchTableNames(?string $connection = null): array
    {
        $statement = $this->pdo($connection)->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

        if ($statement === false) {
            return [];
        }

        $tables = $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_filter($tables, is_string(...)));
    }
}
