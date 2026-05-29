<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Support;

use Asawl\LaravelSqliteManager\SQLiteManagerRepository;

class AuditLogger
{
    public function __construct(private readonly SQLiteManagerRepository $sqLiteManagerRepository) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(string $action, string $table, ?string $recordKey = null, ?array $before = null, ?array $after = null): void
    {
        $this->sqLiteManagerRepository->audit($action, $table, $recordKey, $before, $after);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function logBatch(string $action, string $table, array $rows): void
    {
        $this->sqLiteManagerRepository->auditBatch($action, $table, $rows);
    }
}
