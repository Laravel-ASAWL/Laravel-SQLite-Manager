<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Actions\Security;

use RuntimeException;

class InputValidator
{
    public function validateTableName(string $table): string
    {
        $trimmed = trim($table);

        if ($trimmed === '') {
            throw new RuntimeException('Table name cannot be empty.');
        }

        if (mb_strlen($trimmed) > 128) {
            throw new RuntimeException('Table name exceeds maximum length of 128 characters.');
        }

        if (! preg_match('/^[a-zA-Z_]\w*$/', $trimmed)) {
            throw new RuntimeException("Invalid table name format: {$trimmed}");
        }

        return $trimmed;
    }

    public function validatePrimaryKey(string $key): string
    {
        $trimmed = trim($key);

        if ($trimmed === '') {
            throw new RuntimeException('Primary key value cannot be empty.');
        }

        if (mb_strlen($trimmed) > 255) {
            throw new RuntimeException('Primary key value exceeds maximum length.');
        }

        return $trimmed;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public function validatePrimaryKeys(array $keys): array
    {
        return array_values(array_filter(array_map(
            $this->validatePrimaryKey(...),
            $keys,
        ), fn (?string $key): bool => $key !== null));
    }

    public function validateSortDirection(string $direction): string
    {
        $direction = mb_strtolower($direction);

        return $direction === 'desc' ? 'DESC' : 'ASC';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    public function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
