<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Support;

use Asawl\LaravelSqliteManager\SQLiteManagerRepository;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExporter
{
    public function __construct(private readonly SQLiteManagerRepository $sqLiteManagerRepository) {}

    /**
     * @param  list<array{column: string, operator: string, value: string}>  $filters
     * @param  list<string>  $selectedKeys
     */
    public function download(string $table, string $format, ?string $search = null, array $filters = [], array $selectedKeys = [], ?string $sortColumn = null, string $sortDirection = 'asc', bool $includeSoftDeleted = false): StreamedResponse
    {
        $rows = $this->sqLiteManagerRepository->exportRows($table, $search, $filters, $selectedKeys, $sortColumn, $sortDirection, $includeSoftDeleted);
        $format = $format === 'json' ? 'json' : 'csv';
        $filename = $table.'-'.now()->format('Ymd-His').'.'.$format;

        if ($format === 'json') {
            return response()->streamDownload(function () use ($rows): void {
                echo json_encode($rows, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            }, $filename, ['Content-Type' => 'application/json']);
        }

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            $headers = array_keys($rows[0] ?? []);

            if ($headers !== []) {
                fputcsv($output, $headers);
            }

            foreach ($rows as $row) {
                fputcsv($output, array_map(fn (mixed $value): mixed => is_scalar($value) || $value === null ? $value : json_encode($value, JSON_THROW_ON_ERROR), $row));
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
