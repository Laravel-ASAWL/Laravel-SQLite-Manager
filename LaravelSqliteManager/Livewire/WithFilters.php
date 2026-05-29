<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Livewire;

trait WithFilters
{
    public function addFilter(): void
    {
        $this->filters[] = ['column' => '', 'operator' => 'contains', 'value' => ''];
        $this->applyFilters();
    }

    public function applyFilters(): void
    {
        $this->page = 1;
        $this->rememberPreference($this->filtersCookieName(), json_encode($this->filters, JSON_THROW_ON_ERROR));
    }

    public function clearFilters(): void
    {
        $this->filters = [];
        $this->applyFilters();
    }

    public function removeFilter(int $index): void
    {
        $clean = [];

        foreach ($this->filters as $i => $filter) {
            if ($i !== $index) {
                $clean[] = $filter;
            }
        }

        $this->filters = $clean;
        $this->applyFilters();
    }

    /** @return list<array{column: string, operator: string, value: string}> */
    private function activeFilters(): array
    {
        return array_values(array_filter(
            $this->filters,
            fn (array $filter): bool => $filter['column'] !== '' && $filter['value'] !== '',
        ));
    }
}
