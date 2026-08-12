<?php

use App\Enums\ImportType;
use App\Services\Import\Handlers\DepartmentImportHandler;
use App\Services\Import\ImportStrategyResolver;
use Tests\TestCase;

uses(TestCase::class);

test('resolve_registeredType_returnsItsHandler', function () {
    $resolver = app(ImportStrategyResolver::class);

    expect($resolver->resolve(ImportType::Department))->toBeInstanceOf(DepartmentImportHandler::class);
});

test('resolve_typeWithNoRegisteredHandler_throws', function () {
    config(['imports.handlers.department' => null]);
    $resolver = app(ImportStrategyResolver::class);

    expect(fn () => $resolver->resolve(ImportType::Department))
        ->toThrow(RuntimeException::class, 'No import handler registered for type [department].');
});
