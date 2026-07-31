<?php

namespace App\Services\Export;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Template Method for CSV export. Concrete Handlers only need to describe
 * their CSV columns, the filtered query to read from, and how to map one
 * record to a row — everything else (streaming in bounded chunks, the BOM,
 * writing CSV) lives here and is shared by every export type.
 *
 * writeTo() only depends on a PHP stream resource, not on how that resource
 * is produced or consumed. Today ExportService points it at `php://output`
 * for a synchronous download; a future queued export could point it at a
 * file handle instead without changing this class or any concrete Handler.
 */
abstract class AbstractExportHandler
{
    /**
     * CSV header columns, in output order.
     *
     * @return list<string>
     */
    abstract public function headers(): array;

    /**
     * Build the filtered, unordered query to export. chunkById() (used by
     * writeTo()) manages its own ordering, so implementations don't need to
     * call orderBy().
     *
     * @param  array<string, mixed>  $filters
     */
    abstract protected function query(array $filters): Builder;

    /**
     * Map one record to a CSV row, in the same order as headers().
     *
     * @return list<string|null>
     */
    abstract protected function toRow(Model $record): array;

    /**
     * @param  resource  $handle
     * @param  array<string, mixed>  $filters
     */
    public function writeTo($handle, array $filters): void
    {
        fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel renders accented characters correctly
        fputcsv($handle, $this->headers());

        $this->query($filters)->chunkById(
            (int) config('exports.chunk_size'),
            function (Collection $records) use ($handle): void {
                foreach ($records as $record) {
                    fputcsv($handle, $this->toRow($record));
                }

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            }
        );
    }
}
