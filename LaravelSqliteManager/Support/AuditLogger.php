<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Support;

use Asawl\LaravelSqliteManager\SQLiteManagerRepository;

class AuditLogger
{
    public function __construct(private readonly SQLiteManagerRepository $repository) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(string $action, string $table, ?string $recordKey = null, ?array $before = null, ?array $after = null): void
    {
        $this->repository->audit($action, $table, $recordKey, $before, $after);
    }
}
