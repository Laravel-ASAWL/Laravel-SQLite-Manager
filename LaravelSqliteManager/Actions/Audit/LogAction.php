<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Actions\Audit;

use Asawl\LaravelSqliteManager\Actions\Security\ConnectionManager;
use Asawl\LaravelSqliteManager\Actions\Security\InputValidator;
use JsonException;

class LogAction
{
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly InputValidator $inputValidator,
    ) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(string $action, string $table, string $connection, ?string $recordKey = null, ?array $before = null, ?array $after = null): void
    {
        if (! (bool) config('sqlite-manager.audit.enabled', false)) {
            return;
        }

        $this->ensureAuditTable($connection);

        $statement = $this->connectionManager->pdo($connection)->prepare('INSERT INTO '.$this->inputValidator->quoteIdentifier($this->auditTable()).' (action, table_name, record_key, before_values, after_values, created_at) VALUES (:action, :table_name, :record_key, :before_values, :after_values, :created_at)');
        $statement->execute([
            'action' => $action,
            'table_name' => $table,
            'record_key' => $recordKey,
            'before_values' => $before === null ? null : $this->safeJsonEncode($before),
            'after_values' => $after === null ? null : $this->safeJsonEncode($after),
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function logBatch(string $action, string $table, string $connection, array $rows): void
    {
        if (! (bool) config('sqlite-manager.audit.enabled', false)) {
            return;
        }

        $this->ensureAuditTable($connection);

        $now = now()->toDateTimeString();
        $statement = $this->connectionManager->pdo($connection)->prepare('INSERT INTO '.$this->inputValidator->quoteIdentifier($this->auditTable()).' (action, table_name, record_key, before_values, after_values, created_at) VALUES (:action, :table_name, :record_key, :before_values, :after_values, :created_at)');

        foreach ($rows as $row) {
            $statement->execute([
                'action' => $action,
                'table_name' => $table,
                'record_key' => $row['_key'] ?? null,
                'before_values' => null,
                'after_values' => $this->safeJsonEncode($row),
                'created_at' => $now,
            ]);
        }
    }

    private function ensureAuditTable(string $connection): void
    {
        $this->connectionManager->pdo($connection)->exec('CREATE TABLE IF NOT EXISTS '.$this->inputValidator->quoteIdentifier($this->auditTable()).' (id INTEGER PRIMARY KEY AUTOINCREMENT, action TEXT NOT NULL, table_name TEXT NOT NULL, record_key TEXT NULL, before_values TEXT NULL, after_values TEXT NULL, created_at TEXT NOT NULL)');
    }

    private function auditTable(): string
    {
        $table = config('sqlite-manager.audit.table', '_lsm_audit_log');

        return is_string($table) && $table !== '' ? $table : '_lsm_audit_log';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function safeJsonEncode(array $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (JsonException) {
            return json_encode(['_error' => 'Failed to encode audit data'], JSON_THROW_ON_ERROR);
        }
    }
}
