<?php

use App\Models\Import;
use App\Models\ImportError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('import_returnsTheParentImport', function () {
    $import = Import::factory()->create();
    $error = ImportError::factory()->create(['import_id' => $import->id]);

    expect($error->import)->toBeInstanceOf(Import::class)
        ->and($error->import->id)->toBe($import->id);
});
