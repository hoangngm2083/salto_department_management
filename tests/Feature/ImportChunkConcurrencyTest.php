<?php

use App\Enums\ImportType;
use App\Models\Department;
use App\Models\Import;
use App\Services\Import\CsvReader;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FlakyOnceDepartmentImportHandler;

uses(RefreshDatabase::class);

/**
 * @param  list<list<string>>  $rows
 * @return array{path: string, start_byte: int, row_count: int, first_row_number: int}
 */
function writeChunkFile(array $rows, array $headers = ['name', 'slug', 'description', 'status']): array
{
    $path = tempnam(sys_get_temp_dir(), 'import_chunk_test_');
    $lines = [implode(',', $headers)];

    foreach ($rows as $row) {
        $lines[] = implode(',', $row);
    }

    file_put_contents($path, implode("\n", $lines)."\n");

    $chunk = (new CsvReader($path))->scan(1000)['chunks'][0];

    return [
        'path' => $path,
        'start_byte' => $chunk['start_byte'],
        'row_count' => $chunk['row_count'],
        'first_row_number' => $chunk['first_row_number'],
    ];
}

test('processChunk_conflictDuringUpsert_rollsBackFullyThenRetrySucceedsWithCorrectCounts', function () {
    Department::factory()->create(['slug' => 'existing-dept', 'name' => 'Old Name', 'status' => 'active']);

    $file = writeChunkFile([
        ['New Dept', 'new-dept', 'Desc', 'active'],
        ['Existing Dept', 'existing-dept', 'Updated desc', 'active'],
    ]);

    $import = Import::factory()->create(['type' => ImportType::Department]);
    $handler = new FlakyOnceDepartmentImportHandler;

    // First attempt: bulkUpsert() throws mid-transaction.
    FlakyOnceDepartmentImportHandler::$shouldThrow = true;

    expect(fn () => $handler->processChunk($import, $file['path'], $file['start_byte'], $file['row_count'], $file['first_row_number']))
        ->toThrow(QueryException::class);

    // The whole chunk transaction — dedup claims, counters, the write itself — must be gone, not half-applied.
    expect($import->fresh()->created_count)->toBe(0)
        ->and($import->fresh()->updated_count)->toBe(0)
        ->and(DB::table('import_seen_keys')->where('import_id', $import->id)->count())->toBe(0);
    $this->assertDatabaseMissing('departments', ['slug' => 'new-dept']);
    $this->assertDatabaseHas('departments', ['slug' => 'existing-dept', 'name' => 'Old Name']);

    // Retry: same chunk args, exactly as the queue redelivers after backoff.
    $handler->processChunk($import, $file['path'], $file['start_byte'], $file['row_count'], $file['first_row_number']);

    expect($import->fresh()->created_count)->toBe(1)
        ->and($import->fresh()->updated_count)->toBe(1)
        ->and(DB::table('import_seen_keys')->where('import_id', $import->id)->count())->toBe(2);
    $this->assertDatabaseCount('departments', 2);
    $this->assertDatabaseHas('departments', ['slug' => 'new-dept', 'name' => 'New Dept']);
    $this->assertDatabaseHas('departments', ['slug' => 'existing-dept', 'name' => 'Existing Dept']);

    @unlink($file['path']);
});
