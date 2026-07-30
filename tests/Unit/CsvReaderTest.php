<?php

use App\Services\Import\CsvReader;

/**
 * Writes $content to a fresh temp file and returns its path. Pest removes
 * the whole temp dir tree after the run; nothing else needs to clean these up.
 */
function csvFile(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'csv_reader_test_');
    file_put_contents($path, $content);

    return $path;
}

// --- headers() -------------------------------------------------------------

test('headers_wellFormedFile_returnsTrimmedColumnNames', function () {
    $path = csvFile("id, name ,email\n1,John,j@example.com\n");

    $headers = (new CsvReader($path))->headers();

    expect($headers)->toBe(['id', 'name', 'email']);
});

test('headers_utf8BomPresent_bomStrippedFromFirstHeader', function () {
    $path = csvFile("\xEF\xBB\xBFid,name\n1,John\n");

    $headers = (new CsvReader($path))->headers();

    expect($headers)->toBe(['id', 'name']);
});

test('headers_emptyFile_returnsEmptyArray', function () {
    $path = csvFile('');

    expect((new CsvReader($path))->headers())->toBe([]);
});

// --- hasUtf8Encoding() -------------------------------------------------------

test('hasUtf8Encoding_validUtf8Content_returnsTrue', function () {
    $path = csvFile("id,name\n1,Nguyễn Văn A\n");

    expect((new CsvReader($path))->hasUtf8Encoding())->toBeTrue();
});

test('hasUtf8Encoding_invalidByteSequence_returnsFalse', function () {
    $path = csvFile("id,name\n1,\xFF\xFE\n");

    expect((new CsvReader($path))->hasUtf8Encoding())->toBeFalse();
});

test('hasUtf8Encoding_invalidBytesInsideQuotedMultilineField_returnsFalse', function () {
    // A quoted field spanning multiple physical lines; the invalid byte
    // sits on the second physical line of that single CSV row.
    $path = csvFile("id,name\n1,\"line one\nline two \xFF\"\n");

    expect((new CsvReader($path))->hasUtf8Encoding())->toBeFalse();
});

// --- scan() + readRange() blank-row consistency (core fix) ------------------

test('scan_blankLine_countedAsRowNotSkipped', function () {
    $path = csvFile("value\nA\n\nB\n");

    $scan = (new CsvReader($path))->scan(10);

    expect($scan['total'])->toBe(3);
});

test('scanAndReadRange_blankLineAtChunkBoundary_noRowsLost', function () {
    // Regression test for the scan()/readRange() mismatch: scan() used to
    // skip blank lines when counting rows per chunk while readRange() did
    // not skip them when reading, which silently dropped real data rows
    // that landed just after a blank line inside a chunk.
    $path = csvFile("value\nA\n\nB\nC\n");
    $reader = new CsvReader($path);

    $scan = $reader->scan(2);

    $values = [];
    foreach ($scan['chunks'] as $chunk) {
        foreach ($reader->readRange($chunk['start_byte'], $chunk['row_count'], $chunk['first_row_number']) as $row) {
            $values[] = $row['value'];
        }
    }

    expect($scan['total'])->toBe(4)
        ->and($values)->toBe(['A', null, 'B', 'C']);
});

// --- readRange() -------------------------------------------------------------

test('readRange_shortRow_padsMissingColumnsWithNull', function () {
    $path = csvFile("id,name,email\n1,John\n");
    $reader = new CsvReader($path);
    $scan = $reader->scan(10);
    $chunk = $scan['chunks'][0];

    $rows = iterator_to_array($reader->readRange($chunk['start_byte'], $chunk['row_count'], $chunk['first_row_number']));

    expect($rows[2])->toBe(['id' => '1', 'name' => 'John', 'email' => null]);
});

test('readRange_longRow_truncatesExtraColumns', function () {
    $path = csvFile("id,name\n1,John,extra-field\n");
    $reader = new CsvReader($path);
    $scan = $reader->scan(10);
    $chunk = $scan['chunks'][0];

    $rows = iterator_to_array($reader->readRange($chunk['start_byte'], $chunk['row_count'], $chunk['first_row_number']));

    expect($rows[2])->toBe(['id' => '1', 'name' => 'John']);
});

test('readRange_utf8BomPresent_headerKeysExcludeBom', function () {
    $path = csvFile("\xEF\xBB\xBFid,name\n1,John\n");
    $reader = new CsvReader($path);
    $scan = $reader->scan(10);
    $chunk = $scan['chunks'][0];

    $rows = iterator_to_array($reader->readRange($chunk['start_byte'], $chunk['row_count'], $chunk['first_row_number']));

    expect(array_keys($rows[2]))->toBe(['id', 'name']);
});

test('readRange_firstRowNumber_offsetsYieldedKeys', function () {
    $path = csvFile("id\n1\n2\n3\n");
    $reader = new CsvReader($path);

    $rows = iterator_to_array($reader->readRange(3, 3, 2));

    expect(array_keys($rows))->toBe([2, 3, 4]);
});

test('readRange_rowCountExceedsRemainingRows_stopsAtEndOfFile', function () {
    // Defensive guard: a $rowCount that outruns the actual file content
    // (shouldn't happen when start_byte/row_count come from scan() on the
    // same file, but readRange() must not warn/error if it ever does).
    $path = csvFile("id\n1\n2\n");
    $reader = new CsvReader($path);

    $rows = iterator_to_array($reader->readRange(3, 10, 2));

    expect(array_keys($rows))->toBe([2, 3]);
});

// --- openHandle() failure path ------------------------------------------------

test('headers_nonExistentFile_throwsRuntimeException', function () {
    $reader = new CsvReader(sys_get_temp_dir().'/does-not-exist-'.uniqid().'.csv');

    expect(fn () => $reader->headers())->toThrow(RuntimeException::class);
});
