<?php

use App\Enums\ExportType;
use App\Services\Export\ExportStrategyResolver;
use App\Services\Export\Handlers\DepartmentExportHandler;
use App\Services\Export\Handlers\EmployeeExportHandler;
use Tests\TestCase;

uses(TestCase::class);

test('resolve_departmentType_returnsDepartmentHandler', function () {
    $resolver = app(ExportStrategyResolver::class);

    expect($resolver->resolve(ExportType::Department))->toBeInstanceOf(DepartmentExportHandler::class);
});

test('resolve_employeeType_returnsEmployeeHandler', function () {
    $resolver = app(ExportStrategyResolver::class);

    expect($resolver->resolve(ExportType::Employee))->toBeInstanceOf(EmployeeExportHandler::class);
});

test('resolve_typeWithNoRegisteredHandler_throws', function () {
    config(['exports.handlers.department' => null]);
    $resolver = app(ExportStrategyResolver::class);

    expect(fn () => $resolver->resolve(ExportType::Department))
        ->toThrow(RuntimeException::class, 'No export handler registered for type [department].');
});
