<?php

namespace App\Services\Import;

use App\Enums\ImportStatus;
use App\Jobs\Import\ImportChunkJob;
use App\Models\Import;
use App\Models\ImportError;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/**
 * Template Method for import processing. Concrete Handlers only need to
 * describe their expected columns, business key, per-row validation rules,
 * and how to bulk-upsert validated rows — everything else (streaming the
 * file, chunking, progress tracking, partial success, finalization) lives
 * here and is shared by every import type.
 */
abstract class AbstractImportHandler
{
    /**
     * Expected CSV header columns, in any order.
     *
     * @return list<string>
     */
    abstract public function headers(): array;

    /**
     * Column used to detect duplicates within the file and to upsert
     * against existing records.
     */
    abstract protected function businessKey(): string;

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    abstract protected function rowRules(array $row): array;

    /**
     * The writable attributes for a validated row, excluding the business
     * key itself (e.g. name/description/status for Department).
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    abstract protected function attributesFor(array $row): array;

    /**
     * One bulk lookup of existing records for the given business-key
     * values, keyed by that value.
     *
     * @param  list<string>  $keys
     * @return Collection<string, Model>
     */
    abstract protected function findExistingByKeys(array $keys): Collection;

    /**
     * One bulk write for every row that needs to be created or has
     * actually changed. Each row already contains the business key plus
     * attributesFor()'s columns.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    abstract protected function bulkUpsert(array $rows): void;

    public function run(Import $import): void
    {
        $import->update([
            'status' => ImportStatus::Processing,
            'started_at' => now(),
        ]);

        $path = Storage::disk(config('imports.disk'))->path($import->file_path);
        $scan = (new CsvReader($path))->scan((int) config('imports.chunk_size'));

        $import->update(['total' => $scan['total']]);

        if ($scan['chunks'] === []) {
            $import = $import->fresh();
            $this->finalize($import);
            $this->notify($import->fresh());

            return;
        }

        $this->dispatchChunks($import, $path, $scan['chunks']);
    }

    /**
     * @param  list<array{start_byte: int, row_count: int, first_row_number: int}>  $chunks
     */
    protected function dispatchChunks(Import $import, string $path, array $chunks): void
    {
        $jobs = array_map(
            fn (array $chunk): ImportChunkJob => new ImportChunkJob(
                $import->id,
                $path,
                $chunk['start_byte'],
                $chunk['row_count'],
                $chunk['first_row_number'],
            ),
            $chunks
        );

        Bus::batch($jobs)
            ->allowFailures()
            ->onQueue(config('imports.queue'))
            ->finally(function (Batch $batch) use ($import): void {
                $fresh = $import->fresh();
                $this->finalize($fresh);
                $this->notify($fresh->fresh());
            })
            ->dispatch();
    }

    /**
     * Processes one chunk of rows, streamed directly from its byte range.
     * Every step below issues a bounded, roughly-constant number of
     * queries for the whole chunk — never one query per row.
     */
    public function processChunk(Import $import, string $path, int $startByte, int $rowCount, int $firstRowNumber): void
    {
        $reader = new CsvReader($path);

        DB::transaction(function () use ($import, $reader, $startByte, $rowCount, $firstRowNumber): void {
            $errorRows = [];
            $candidates = []; // rowNumber => validated row

            foreach ($reader->readRange($startByte, $rowCount, $firstRowNumber) as $rowNumber => $row) {
                // CSV has no native null — a blank cell is an empty string, treat it as absent.
                $row = array_map(static fn (?string $value): ?string => $value === '' ? null : $value, $row);

                $validator = Validator::make($row, $this->rowRules($row));

                if ($validator->fails()) {
                    $errorMessage = implode(',', $validator->errors()->all());
                    $errorRows[] = $this->buildErrorRow($import, $rowNumber, $errorMessage, $row);

                    continue;
                }

                $candidates[$rowNumber] = $validator->validated();
            }

            $candidates = $this->rejectDuplicates($import, $candidates, $errorRows);

            [$created, $updated] = $candidates === [] ? [0, 0] : $this->upsertCandidates($candidates);

            if ($errorRows !== []) {
                ImportError::insert($errorRows);
            }

            $failed = count($errorRows);

            $import->incrementEach([
                'created_count' => $created,
                'updated_count' => $updated,
                'failed_count' => $failed,
            ]);
        });
    }

    /**
     * Removes duplicate-business-key rows from the candidate set, both
     * within this chunk (in-memory) and against keys already claimed by
     * another chunk (one batched read). Whatever survives is bulk-claimed
     * via claimKeys() — a single insertOrIgnore plus a confirmation select,
     * which stays race-safe across chunk jobs running concurrently on
     * different workers without needing one insert per row.
     *
     * @param  array<int, array<string, mixed>>  $candidates  rowNumber => validated row
     * @param  list<array<string, mixed>>  $errorRows
     * @return array<int, array<string, mixed>>
     */
    private function rejectDuplicates(Import $import, array $candidates, array &$errorRows): array
    {
        $seen = [];

        foreach ($candidates as $rowNumber => $validated) {
            $keyValue = (string) $validated[$this->businessKey()];

            if (isset($seen[$keyValue])) {
                $this->rejectRow($import, $rowNumber, $validated, $candidates, $errorRows);

                continue;
            }

            $seen[$keyValue] = true;
        }

        if ($candidates === []) {
            return $candidates;
        }

        // Keys already committed by an earlier, already-finished chunk. This
        // check must happen before claimKeys(): its confirmation select can
        // only tell us a key is present, not whether *this* call is the one
        // that put it there, so a key from an earlier chunk would otherwise
        // look indistinguishable from one this chunk just claimed.
        $alreadySeen = array_flip(
            DB::table('import_seen_keys')
                ->where('import_id', $import->id)
                ->whereIn('key_value', array_keys($seen))
                ->pluck('key_value') // Select key_value From ...
                ->all()
        );

        foreach ($candidates as $rowNumber => $validated) {
            $keyValue = (string) $validated[$this->businessKey()];

            if (isset($alreadySeen[$keyValue])) {
                $this->rejectRow($import, $rowNumber, $validated, $candidates, $errorRows);
            }
        }

        if ($candidates === []) {
            return $candidates;
        }

        $claimedKeys = $this->claimKeys($import, $candidates);

        foreach ($candidates as $rowNumber => $validated) {
            $keyValue = (string) $validated[$this->businessKey()];

            if (! isset($claimedKeys[$keyValue])) {
                $this->rejectRow($import, $rowNumber, $validated, $candidates, $errorRows);
            }
        }

        return $candidates;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates  rowNumber => validated row
     * @param  list<array<string, mixed>>  $errorRows
     */
    private function rejectRow(Import $import, int $rowNumber, array $validated, array &$candidates, array &$errorRows): void
    {
        $errorRows[] = $this->buildErrorRow($import, $rowNumber, "Duplicate {$this->businessKey()} value in file.", $validated);
        unset($candidates[$rowNumber]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates  rowNumber => validated row
     * @return array{0: int, 1: int} [created, updated]
     */
    private function upsertCandidates(array $candidates): array
    {
        $keys = array_values(array_map(
            fn (array $row): string => (string) $row[$this->businessKey()],
            $candidates
        ));

        $existing = $this->findExistingByKeys($keys);

        $created = 0;
        $updated = 0;
        $upsertRows = [];

        foreach ($candidates as $validated) {
            $keyValue = (string) $validated[$this->businessKey()];
            $attributes = $this->attributesFor($validated);
            $existingRecord = $existing->get($keyValue);

            if ($existingRecord === null) {
                $created++;
                $upsertRows[] = [$this->businessKey() => $keyValue, ...$attributes];

                continue;
            }

            $updated++;

            if ($existingRecord->only(array_keys($attributes)) !== $attributes) {
                $upsertRows[] = [$this->businessKey() => $keyValue, ...$attributes];
            }
        }

        if ($upsertRows !== []) {
            $this->bulkUpsert($upsertRows);
        }

        return [$created, $updated];
    }

    /**
     * Bulk-claims business-key values as seen for this import (FR-8): one
     * insertOrIgnore for the whole batch, so a key already claimed by a
     * concurrent chunk job (via the unique constraint on
     * (import_id, key_value)) is silently skipped rather than throwing.
     * The confirmation select then reports which keys this chunk actually
     * won — it always sees this transaction's own writes regardless of
     * isolation level, so it stays correct even when another worker's
     * commit lands in the same instant.
     *
     * @param  array<int, array<string, mixed>>  $candidates  rowNumberj => validated row
     * @return array<string, true> keyValue => true, for keys this chunk claimed
     */
    private function claimKeys(Import $import, array $candidates): array
    {
        $now = now();

        $keys = array_values(array_map(
            fn (array $validated): string => (string) $validated[$this->businessKey()],
            $candidates
        ));

        DB::table('import_seen_keys')->insertOrIgnore(array_map(
            static fn (string $keyValue): array => [
                'import_id' => $import->id,
                'key_value' => $keyValue,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $keys
        ));

        return array_flip(
            DB::table('import_seen_keys')
                ->where('import_id', $import->id)
                ->whereIn('key_value', $keys)
                ->pluck('key_value')
                ->all()
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildErrorRow(Import $import, int $rowNumber, string $message, array $payload): array
    {
        return [
            'import_id' => $import->id,
            'row' => $rowNumber,
            'message' => $message,
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    protected function finalize(Import $import): void
    {
        $status = $import->created_count === 0 && $import->updated_count === 0 && $import->failed_count > 0
            ? ImportStatus::Failed
            : ImportStatus::Completed;

        $import->update([
            'status' => $status,
            'finished_at' => now(),
        ]);
    }

    protected function notify(Import $import): void
    {
        Log::info(sprintf(
            'Import #%d (%s) finished with status [%s]: %d created, %d updated, %d failed.',
            $import->id,
            $import->type->value,
            $import->status->value,
            $import->created_count,
            $import->updated_count,
            $import->failed_count,
        ));
    }
}
