<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Support;

use Illuminate\Validation\ValidationException;

class FormValidator
{
    /**
     * @return array<string, string|list<string>>
     */
    public function rules(?string $table): array
    {
        if ($table === null) {
            return [];
        }

        $configured = config('sqlite-manager.validation.rules.'.$table, []);

        if (! is_array($configured)) {
            return [];
        }

        /** @var array<string, list<string>|string> $validationRules */
        $validationRules = [];

        foreach ($configured as $column => $rule) {
            if (is_string($column) && (is_string($rule) || is_array($rule))) {
                $validationRules['form.'.$column] = is_array($rule)
                    ? array_values(array_filter($rule, is_string(...)))
                    : $rule;
            }
        }

        return $validationRules;
    }

    /**
     * @param  array<string, mixed>  $form
     * @param  list<array{name: string, type: string, nullable: bool, default: mixed, primary: bool}>  $columns
     */
    public function validateJson(array $form, array $columns): void
    {
        $errors = [];

        foreach ($columns as $column) {
            if (! $this->isJsonColumn($column['name'], $column['type'])) {
                continue;
            }

            $value = $form[$column['name']] ?? null;
            if ($value === null) {
                continue;
            }
            if ($value === '') {
                continue;
            }

            if (! is_string($value)) {
                $errors['form.'.$column['name']] = 'The '.$column['name'].' field must be valid JSON.';

                continue;
            }

            json_decode($value);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors['form.'.$column['name']] = 'The '.$column['name'].' field must be valid JSON.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function isJsonColumn(string $name, string $type): bool
    {
        return str_contains(mb_strtoupper($type), 'JSON') || str_ends_with($name, '_json');
    }
}
