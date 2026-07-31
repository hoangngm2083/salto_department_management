<?php

use App\Services\Export\Handlers\DepartmentExportHandler;
use App\Services\Export\Handlers\EmployeeExportHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Chunk Size
    |--------------------------------------------------------------------------
    |
    | Number of rows read from the database per chunkById() batch while
    | streaming a CSV export. Keeps memory usage O(1) regardless of table
    | size, mirroring how imports.chunk_size bounds CsvReader's chunking.
    |
    */

    'chunk_size' => (int) env('EXPORT_CHUNK_SIZE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Export Type => Handler Map
    |--------------------------------------------------------------------------
    |
    | Adding a new export type only requires a new Handler class and an
    | entry here — no changes to ExportController or ExportStrategyResolver.
    |
    */

    'handlers' => [
        'department' => DepartmentExportHandler::class,
        'employee' => EmployeeExportHandler::class,
    ],

];
