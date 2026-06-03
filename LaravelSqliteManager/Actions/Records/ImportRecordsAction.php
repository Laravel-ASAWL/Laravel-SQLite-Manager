<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Actions\Records;

use RuntimeException;

class ImportRecordsAction
{
    public function __construct(
        private readonly ManageRecordAction $manageRecordAction,
    ) {}

    /**
     * @param  list<array<string, string|null>>  $rows  Pre-parsed CSV rows
     * @return list<array<string, mixed>>
     */
    public function import(string $table, string $connection, array $rows): array
    {
        $limit = $this->importLimit();

        if (count($rows) > $limit) {
            throw new RuntimeException('CSV import exceeds the configured row limit of '.$limit.'.');
        }

        $inserted = [];

        foreach ($rows as $row) {
            $sanitized = $this->sanitizeRow($row);

            if ($sanitized === []) {
                continue;
            }

            $key = $this->manageRecordAction->create($table, $connection, $sanitized);
            $inserted[] = [...$sanitized, '_key' => $key];
        }

        return $inserted;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, string>
     */
    private function sanitizeRow(array $row): array
    {
        $sanitized = [];

        foreach ($row as $key => $value) {
            $trimmedKey = trim($key);

            if ($trimmedKey === '') {
                continue;
            }

            $sanitized[$trimmedKey] = $value === null ? '' : (mb_strlen($value) > 65535 ? mb_substr($value, 0, 65535) : $value);
        }

        return $sanitized;
    }

    private function importLimit(): int
    {
        $limit = config('sqlite-manager.imports.max_rows', 500);

        return is_numeric($limit) ? max(1, (int) $limit) : 500;
    }
}
