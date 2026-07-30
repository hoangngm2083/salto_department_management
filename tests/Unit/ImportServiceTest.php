<?php

use App\Enums\ImportType;
use App\Models\Employee;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('initiate_storesFileWithDateFolderAndSluggedName', function () {
    Storage::fake(config('imports.disk'));

    $employee = Employee::factory()->create();
    $file = UploadedFile::fake()->create('My Test File 2026.csv', 100);

    $service = app(ImportService::class);
    $import = $service->initiate(ImportType::Department, $file, $employee);

    $expectedFolder = 'imports/'.date('Y/m/d');
    expect($import->file_path)->toStartWith($expectedFolder.'/my-test-file-2026-')
        ->and($import->file_path)->toEndWith('.csv');

    Storage::disk(config('imports.disk'))->assertExists($import->file_path);
});
