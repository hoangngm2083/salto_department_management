<?php

use App\Models\Department;
use App\Models\Employee;
use App\Services\Export\Handlers\EmployeeExportHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function callEmployeeExportHandlerMethod(EmployeeExportHandler $handler, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($handler, $method);

    return $reflection->invoke($handler, ...$args);
}

// --- headers() ---------------------------------------------------------------

test('headers_returnsExpectedColumnsInOrder', function () {
    $handler = new EmployeeExportHandler;

    expect($handler->headers())->toBe(['name', 'email', 'birthday', 'position', 'department_slug']);
});

// --- query() -------------------------------------------------------------------

test('query_defaultFilters_excludesAdminPosition', function () {
    $handler = new EmployeeExportHandler;
    Employee::factory()->create(['position' => 'employee', 'email' => 'e@example.com']);
    Employee::factory()->create(['position' => 'admin', 'email' => 'a@example.com']);

    $emails = callEmployeeExportHandlerMethod($handler, 'query', [[]])->pluck('email');

    expect($emails)->toContain('e@example.com')
        ->and($emails)->not->toContain('a@example.com');
});

test('query_positionFilter_returnsOnlyThatPosition', function () {
    $handler = new EmployeeExportHandler;
    Employee::factory()->create(['position' => 'manager', 'email' => 'm@example.com']);
    Employee::factory()->create(['position' => 'employee', 'email' => 'e@example.com']);

    $emails = callEmployeeExportHandlerMethod($handler, 'query', [['position' => ['manager']]])->pluck('email');

    expect($emails)->toContain('m@example.com')
        ->and($emails)->not->toContain('e@example.com');
});

test('query_nameFilter_matchesPrefixOnly', function () {
    $handler = new EmployeeExportHandler;
    Employee::factory()->create(['name' => 'Alice Nguyen', 'email' => 'alice@example.com']);
    Employee::factory()->create(['name' => 'Bob Alice', 'email' => 'bob@example.com']);

    $emails = callEmployeeExportHandlerMethod($handler, 'query', [['name' => 'Alice']])->pluck('email');

    expect($emails)->toContain('alice@example.com')
        ->and($emails)->not->toContain('bob@example.com');
});

test('query_departmentIdFilter_returnsOnlyThatDepartment', function () {
    $handler = new EmployeeExportHandler;
    $department = Department::factory()->create();
    Employee::factory()->create(['department_id' => $department->id, 'email' => 'in@example.com']);
    Employee::factory()->create(['email' => 'out@example.com']);

    $emails = callEmployeeExportHandlerMethod($handler, 'query', [['department_id' => $department->id]])->pluck('email');

    expect($emails)->toContain('in@example.com')
        ->and($emails)->not->toContain('out@example.com');
});

test('query_departmentSlugFilter_returnsOnlyThatDepartment', function () {
    $handler = new EmployeeExportHandler;
    $department = Department::factory()->create(['slug' => 'engineering']);
    Employee::factory()->create(['department_id' => $department->id, 'email' => 'in@example.com']);
    Employee::factory()->create(['email' => 'out@example.com']);

    $emails = callEmployeeExportHandlerMethod($handler, 'query', [['department_slug' => 'engineering']])->pluck('email');

    expect($emails)->toContain('in@example.com')
        ->and($emails)->not->toContain('out@example.com');
});

// --- toRow() -------------------------------------------------------------------

test('toRow_employee_mapsColumnsInHeaderOrderWithDepartmentSlug', function () {
    $handler = new EmployeeExportHandler;
    $department = Department::factory()->create(['slug' => 'engineering']);
    $employee = Employee::factory()->create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'birthday' => '1990-01-15',
        'position' => 'employee',
        'department_id' => $department->id,
    ])->load('department:id,slug');

    expect(callEmployeeExportHandlerMethod($handler, 'toRow', [$employee]))
        ->toBe(['Alice', 'alice@example.com', '1990-01-15', 'employee', 'engineering']);
});

// --- writeTo() -----------------------------------------------------------------

test('writeTo_streamsBomHeaderAndRows', function () {
    $handler = new EmployeeExportHandler;
    $department = Department::factory()->create(['slug' => 'engineering']);
    Employee::factory()->create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'position' => 'employee',
        'department_id' => $department->id,
    ]);

    $stream = fopen('php://memory', 'r+');
    $handler->writeTo($stream, []);
    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);

    expect($content)->toStartWith("\xEF\xBB\xBFname,email,birthday,position,department_slug")
        ->and($content)->toContain('Alice,alice@example.com');
});
