<?php

use App\Models\Employee;
use App\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('importedBy_returnsTheEmployeeWhoInitiatedTheImport', function () {
    $employee = Employee::factory()->create();
    $import = Import::factory()->create(['imported_by' => $employee->id]);

    expect($import->importedBy)->toBeInstanceOf(Employee::class)
        ->and($import->importedBy->id)->toBe($employee->id);
});
