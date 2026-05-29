<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Livewire;

use Illuminate\Support\Facades\Cookie;
use JsonException;

trait WithPreferences
{
    private function hydrateCookiePreferences(): void
    {
        $perPage = $this->cookiePreference($this->perPageCookieName());
        $showLaravelTables = $this->cookiePreference($this->showLaravelTablesCookieName());
        $showNullableFields = $this->cookiePreference($this->showNullableFieldsCookieName());
        $showSoftDeleted = $this->cookiePreference($this->showSoftDeletedCookieName());
        $selectedColumns = $this->cookiePreference($this->selectedColumnsCookieName());
        $filters = $this->cookiePreference($this->filtersCookieName());

        if (is_numeric($perPage)) {
            $this->perPage = (int) $perPage;
        }

        if (is_string($showLaravelTables)) {
            $this->showLaravelTables = filter_var($showLaravelTables, FILTER_VALIDATE_BOOL);
        }

        if (is_string($showNullableFields)) {
            $this->showNullableFields = filter_var($showNullableFields, FILTER_VALIDATE_BOOL);
        }

        if (is_string($showSoftDeleted)) {
            $this->showSoftDeleted = filter_var($showSoftDeleted, FILTER_VALIDATE_BOOL);
        }

        if (is_string($selectedColumns)) {
            try {
                $selectedColumns = json_decode($selectedColumns, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $selectedColumns = null;
            }

            if (is_array($selectedColumns)) {
                $this->selectedColumns = array_values(array_filter($selectedColumns, is_string(...)));
            }
        }

        if (is_string($filters)) {
            try {
                $filters = json_decode($filters, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $filters = null;
            }

            if (is_array($filters)) {
                $clean = [];

                foreach ($filters as $filter) {
                    if (! is_array($filter)) {
                        continue;
                    }

                    $clean[] = [
                        'column' => is_string($filter['column'] ?? null) ? $filter['column'] : '',
                        'operator' => is_string($filter['operator'] ?? null) ? $filter['operator'] : 'contains',
                        'value' => is_scalar($filter['value'] ?? null) ? (string) $filter['value'] : '',
                    ];
                }

                $this->filters = $clean;
            }
        }
    }

    private function rememberPreference(string $name, string $value): void
    {
        Cookie::queue($name, $value, 60 * 24 * 365);
    }

    private function cookiePreference(string $name): ?string
    {
        $value = request()->cookie($name);

        if (is_string($value)) {
            return $value;
        }

        return $this->rawCookiePreference($name);
    }

    private function rawCookiePreference(string $name): ?string
    {
        $cookies = request()->headers->get('cookie');

        if (! is_string($cookies)) {
            return null;
        }

        foreach (explode(';', $cookies) as $cookie) {
            $parts = explode('=', trim($cookie), 2);

            if (count($parts) === 2 && $parts[0] === $name) {
                return rawurldecode($parts[1]);
            }
        }

        return null;
    }

    private function perPageCookieName(): string
    {
        return 'sqlite_manager_per_page';
    }

    private function showLaravelTablesCookieName(): string
    {
        return 'sqlite_manager_show_laravel_tables';
    }

    private function showNullableFieldsCookieName(): string
    {
        return 'sqlite_manager_show_nullable_fields';
    }

    private function showSoftDeletedCookieName(): string
    {
        return 'sqlite_manager_show_soft_deleted';
    }

    private function selectedColumnsCookieName(): string
    {
        return 'sqlite_manager_columns_'.sha1((string) $this->table);
    }

    private function filtersCookieName(): string
    {
        return 'sqlite_manager_filters_'.sha1((string) $this->table);
    }
}
