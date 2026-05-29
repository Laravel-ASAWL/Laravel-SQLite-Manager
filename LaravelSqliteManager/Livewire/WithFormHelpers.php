<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Livewire;

trait WithFormHelpers
{
    public function inputTypeFor(string $type): string
    {
        $type = mb_strtoupper($type);

        if (str_contains($type, 'INT')) {
            return 'number';
        }

        if (str_contains($type, 'REAL') || str_contains($type, 'FLOA') || str_contains($type, 'DOUB') || str_contains($type, 'DEC') || str_contains($type, 'NUM')) {
            return 'number';
        }

        if (str_contains($type, 'DATETIME') || str_contains($type, 'TIMESTAMP')) {
            return 'datetime-local';
        }

        if (str_contains($type, 'DATE')) {
            return 'date';
        }

        if (str_contains($type, 'TIME')) {
            return 'time';
        }

        return 'text';
    }

    public function usesTextareaFor(string $type): bool
    {
        $type = mb_strtoupper($type);

        return str_contains($type, 'TEXT') || str_contains($type, 'BLOB') || str_contains($type, 'BINARY') || str_contains($type, 'CLOB');
    }

    public function inputStepFor(string $type): ?string
    {
        $type = mb_strtoupper($type);

        if (str_contains($type, 'REAL') || str_contains($type, 'FLOA') || str_contains($type, 'DOUB') || str_contains($type, 'DEC') || str_contains($type, 'NUM')) {
            return 'any';
        }

        return null;
    }

    /** @param array{name: string, type: string, nullable: bool, default: mixed, primary: bool} $column */
    public function usesJsonEditorFor(array $column): bool
    {
        $type = mb_strtoupper($column['type']);

        return str_contains($type, 'JSON') || str_ends_with($column['name'], '_json');
    }

    /** @param array{name: string, type: string, nullable: bool, default: mixed, primary: bool} $column */
    public function shouldShowFormColumn(array $column): bool
    {
        if (! $this->isFormMode()) {
            return true;
        }
        if ($this->showNullableFields) {
            return true;
        }

        return ! $this->isNullableFormColumn($column);
    }

    /** @param array{name: string, type: string, nullable: bool, default: mixed, primary: bool} $column */
    private function isNullableFormColumn(array $column): bool
    {
        return $column['nullable'] && ! $column['primary'];
    }
}
