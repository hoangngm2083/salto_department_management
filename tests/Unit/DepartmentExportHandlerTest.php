<?php

use App\Models\Department;
use App\Services\Export\Handlers\DepartmentExportHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * query()/toRow() live as protected methods inherited from
 * AbstractExportHandler's Template Method contract. Reflection lets us
 * exercise each piece in isolation instead of only through the full HTTP
 * pipeline already covered by ExportDepartmentTest.
 */
function callDepartmentExportHandlerMethod(DepartmentExportHandler $handler, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($handler, $method);

    return $reflection->invoke($handler, ...$args);
}

// --- headers() ---------------------------------------------------------------

test('headers_returnsExpectedColumnsInOrder', function () {
    $handler = new DepartmentExportHandler;

    expect($handler->headers())->toBe(['name', 'slug', 'description', 'status']);
});

// --- query() -------------------------------------------------------------------

test('query_defaultFilters_returnsOnlyActiveDepartments', function () {
    $handler = new DepartmentExportHandler;
    Department::factory()->create(['status' => 'active', 'slug' => 'active-dept']);
    Department::factory()->create(['status' => 'inactive', 'slug' => 'inactive-dept']);

    $slugs = callDepartmentExportHandlerMethod($handler, 'query', [[]])->pluck('slug');

    expect($slugs)->toContain('active-dept')
        ->and($slugs)->not->toContain('inactive-dept');
});

test('query_statusInactive_returnsOnlyInactiveDepartments', function () {
    $handler = new DepartmentExportHandler;
    Department::factory()->create(['status' => 'active', 'slug' => 'active-dept']);
    Department::factory()->create(['status' => 'inactive', 'slug' => 'inactive-dept']);

    $slugs = callDepartmentExportHandlerMethod($handler, 'query', [['status' => 'inactive']])->pluck('slug');

    expect($slugs)->toContain('inactive-dept')
        ->and($slugs)->not->toContain('active-dept');
});

test('query_statusAll_returnsEveryStatus', function () {
    $handler = new DepartmentExportHandler;
    Department::factory()->create(['status' => 'active', 'slug' => 'active-dept']);
    Department::factory()->create(['status' => 'inactive', 'slug' => 'inactive-dept']);

    $slugs = callDepartmentExportHandlerMethod($handler, 'query', [['status' => 'all']])->pluck('slug');

    expect($slugs)->toContain('active-dept', 'inactive-dept');
});

// --- toRow() -------------------------------------------------------------------

test('toRow_department_mapsColumnsInHeaderOrder', function () {
    $handler = new DepartmentExportHandler;
    $department = Department::factory()->create([
        'name' => 'Engineering',
        'slug' => 'engineering',
        'description' => 'Builds things',
        'status' => 'active',
    ]);

    expect(callDepartmentExportHandlerMethod($handler, 'toRow', [$department]))
        ->toBe(['Engineering', 'engineering', 'Builds things', 'active']);
});

test('toRow_nullDescription_mapsToEmptyString', function () {
    $handler = new DepartmentExportHandler;
    $department = Department::factory()->create(['description' => null]);

    expect(callDepartmentExportHandlerMethod($handler, 'toRow', [$department])[2])->toBe('');
});

// --- writeTo() -----------------------------------------------------------------

test('writeTo_streamsBomHeaderAndRows', function () {
    $handler = new DepartmentExportHandler;
    Department::factory()->create(['name' => 'Engineering', 'slug' => 'engineering', 'status' => 'active']);

    $stream = fopen('php://memory', 'r+');
    $handler->writeTo($stream, []);
    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);

    expect($content)->toStartWith("\xEF\xBB\xBFname,slug,description,status")
        ->and($content)->toContain('Engineering,engineering');
});
