<?php

namespace App\Services\Import;

use Generator;
use RuntimeException;

/**
 * Stream-only CSV reader. Never loads the whole file into memory: every
 * method opens its own handle and reads line-by-line / row-by-row,
 * discarding each line as soon as it has been inspected.
 *
 * Row boundaries are tracked via fgetcsv() (not raw fgets()) so that
 * quoted fields containing embedded newlines are read as a single row.
 */
final class CsvReader
{
    public function __construct(private readonly string $path) {}

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        $handle = $this->openHandle();

        try {
            $this->skipBom($handle);

            $line = fgetcsv($handle);

            return $line === false ? [] : array_map(static fn (mixed $value): string => trim((string) $value), $line);
        } finally {
            fclose($handle);
        }
    }

    public function hasUtf8Encoding(): bool
    {
        $handle = $this->openHandle();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                if (! mb_check_encoding(implode('', array_map('strval', $row)), 'UTF-8')) {
                    return false;
                }
            }

            return true;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Single streaming pass: counts data rows and records the byte offset
     * of the start of every $chunkSize-th row. Holds only the current row
     * and the list of chunk boundaries in memory — never the full file.
     *
     * @return array{total: int, chunks: list<array{start_byte: int, row_count: int, first_row_number: int}>}
     */
    public function scan(int $chunkSize): array
    {
        $handle = $this->openHandle();

        try {
            fgetcsv($handle); // Skip header row (Read line -> parse into array -> update pointer to the head of next line)

            $total = 0;
            $chunks = [];
            $chunkStartByte = ftell($handle); // Get the current cursor position in the file.
            $chunkRowCount = 0;
            $chunkFirstRow = 2;

            while (true) {
                $row = fgetcsv($handle);

                if ($row === false) {
                    break;
                }

                $total++;
                $chunkRowCount++;

                if ($chunkRowCount === $chunkSize) {
                    $chunks[] = [
                        'start_byte' => $chunkStartByte,
                        'row_count' => $chunkRowCount,
                        'first_row_number' => $chunkFirstRow,
                    ];

                    $chunkStartByte = ftell($handle);
                    $chunkFirstRow += $chunkRowCount;
                    $chunkRowCount = 0;
                }
            }

            if ($chunkRowCount > 0) {
                $chunks[] = [
                    'start_byte' => $chunkStartByte,
                    'row_count' => $chunkRowCount,
                    'first_row_number' => $chunkFirstRow,
                ];
            }

            return ['total' => $total, 'chunks' => $chunks];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Yields exactly $rowCount rows starting at $startByte, one at a time.
     *
     * @return Generator<int, array<string, string|null>>
     */
    public function readRange(int $startByte, int $rowCount, int $firstRowNumber): Generator
    {
        $header = $this->headers();
        $columnCount = count($header);

        $handle = $this->openHandle();

        try {
            fseek($handle, $startByte); // Read line from $startByte to the end of line

            for ($i = 0; $i < $rowCount; $i++) {
                $row = fgetcsv($handle);

                if ($row === false) {
                    break;
                }

                $row = array_slice(array_pad($row, $columnCount, null), 0, $columnCount);

                yield $firstRowNumber + $i => array_combine($header, $row);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Consumes the UTF-8 BOM (EF BB BF) at the current handle position, if
     * present, so it doesn't get read as part of the first header field.
     * Rewinds if no BOM is found.
     *
     * @param  resource  $handle
     */
    private function skipBom($handle): void
    {
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle); // return the cursor to the beginning of the file.
        }
    }

    /**
     * @return resource
     */
    private function openHandle()
    {
        $handle = fopen($this->path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open file at [{$this->path}].");
        }

        return $handle;
    }
}
