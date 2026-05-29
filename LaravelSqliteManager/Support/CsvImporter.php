<?php

declare(strict_types=1);

namespace Asawl\LaravelSqliteManager\Support;

use RuntimeException;

class CsvImporter
{
    /**
     * @return list<array<string, string>>
     */
    public function rows(string $csv): array
    {
        $csv = trim($csv);

        if ($csv === '') {
            return [];
        }

        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Unable to open temporary CSV stream.');
        }

        fwrite($stream, $csv);
        rewind($stream);

        $headers = fgetcsv($stream);

        if (! is_array($headers)) {
            fclose($stream);

            return [];
        }

        $headers = array_map(fn (mixed $header): string => trim((string) $header), $headers);
        $rows = [];

        while (($data = fgetcsv($stream)) !== false) {
            $row = [];

            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }

                $row[$header] = isset($data[$index]) ? (string) $data[$index] : '';
            }

            if ($row !== []) {
                $rows[] = $row;
            }
        }

        fclose($stream);

        return $rows;
    }
}
