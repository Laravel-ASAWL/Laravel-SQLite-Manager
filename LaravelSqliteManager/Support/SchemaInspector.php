<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Support;

use Asawl\LaravelSqliteManager\SQLiteManagerRepository;

class SchemaInspector
{
    public function __construct(private readonly SQLiteManagerRepository $repository) {}

    /**
     * @return array{columns: list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>, indexes: list<array{name: string, unique: bool, columns: list<string>}>, foreign_keys: list<array{column: string, table: string, foreign_column: string}>}
     */
    public function inspect(string $table): array
    {
        return $this->repository->schema($table);
    }
}
