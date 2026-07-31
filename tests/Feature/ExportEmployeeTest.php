<?php

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $adminDepartment = Department::factory()->create([
        'name' => 'Admin Department',
        'slug' => 'admin-department',
    ]);

    Sanctum::actingAs(
        Employee::factory()->make([
            'position' => 'admin',
            'department_id' => $adminDepartment->id,
        ]),
        ['*']
    );
});

/**
 * @return list<list<string>>
 */
function parseEmployeeExportCsv(string $content): array
{
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $lines = array_filter(explode("\n", trim($content)), fn (string $line): bool => $line !== '');

    return array_map('str_getcsv', $lines);
}

test('exportEmployees_defaultFilters_returnsEmployeeWithDepartmentSlug', function () {
    // Arrange
    $engineering = Department::factory()->create(['slug' => 'engineering']);
    Employee::factory()->create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'birthday' => '1990-01-15',
        'position' => 'employee',
        'department_id' => $engineering->id,
    ]);

    // Act
    $response = $this->get('/api/exports?type=employee');

    // Assert
    $response->assertOk();
    $rows = parseEmployeeExportCsv($response->streamedContent());

    expect($rows[0])->toBe(['name', 'email', 'birthday', 'position', 'department_slug'])
        ->and($rows)->toContain(['Alice', 'alice@example.com', '1990-01-15', 'employee', 'engineering']);
});

test('exportEmployees_excludesAdminPositionByDefault', function () {
    // Arrange
    $department = Department::factory()->create();
    Employee::factory()->create(['position' => 'admin', 'email' => 'other-admin@example.com', 'department_id' => $department->id]);

    // Act
    $response = $this->get('/api/exports?type=employee');

    // Assert
    $rows = parseEmployeeExportCsv($response->streamedContent());

    expect(collect($rows)->skip(1)->pluck(1))->not->toContain('other-admin@example.com');
});

test('exportEmployees_positionFilter_returnsOnlyThatPosition', function () {
    // Arrange
    $department = Department::factory()->create();
    Employee::factory()->create(['position' => 'manager', 'email' => 'manager@example.com', 'department_id' => $department->id]);
    Employee::factory()->create(['position' => 'employee', 'email' => 'employee@example.com', 'department_id' => $department->id]);

    // Act
    $response = $this->get('/api/exports?type=employee&position=manager');

    // Assert
    $emails = collect(parseEmployeeExportCsv($response->streamedContent()))->skip(1)->pluck(1);

    expect($emails)->toContain('manager@example.com')
        ->and($emails)->not->toContain('employee@example.com');
});

test('exportEmployees_nameFilter_matchesPrefixOnly', function () {
    // Arrange
    $department = Department::factory()->create();
    Employee::factory()->create(['name' => 'Alice Nguyen', 'email' => 'alice@example.com', 'department_id' => $department->id]);
    Employee::factory()->create(['name' => 'Bob Alice', 'email' => 'bob@example.com', 'department_id' => $department->id]);

    // Act
    $response = $this->get('/api/exports?type=employee&name=Alice');

    // Assert
    $emails = collect(parseEmployeeExportCsv($response->streamedContent()))->skip(1)->pluck(1);

    expect($emails)->toContain('alice@example.com')
        ->and($emails)->not->toContain('bob@example.com');
});

test('exportEmployees_departmentIdFilter_returnsOnlyThatDepartment', function () {
    // Arrange
    $engineering = Department::factory()->create(['slug' => 'engineering']);
    $sales = Department::factory()->create(['slug' => 'sales']);
    Employee::factory()->create(['email' => 'in@example.com', 'department_id' => $engineering->id]);
    Employee::factory()->create(['email' => 'out@example.com', 'department_id' => $sales->id]);

    // Act
    $response = $this->get("/api/exports?type=employee&department_id={$engineering->id}");

    // Assert
    $emails = collect(parseEmployeeExportCsv($response->streamedContent()))->skip(1)->pluck(1);

    expect($emails)->toContain('in@example.com')
        ->and($emails)->not->toContain('out@example.com');
});

test('exportEmployees_departmentSlugFilter_returnsOnlyThatDepartment', function () {
    // Arrange
    $engineering = Department::factory()->create(['slug' => 'engineering']);
    $sales = Department::factory()->create(['slug' => 'sales']);
    Employee::factory()->create(['email' => 'in@example.com', 'department_id' => $engineering->id]);
    Employee::factory()->create(['email' => 'out@example.com', 'department_id' => $sales->id]);

    // Act
    $response = $this->get('/api/exports?type=employee&department_slug=engineering');

    // Assert
    $emails = collect(parseEmployeeExportCsv($response->streamedContent()))->skip(1)->pluck(1);

    expect($emails)->toContain('in@example.com')
        ->and($emails)->not->toContain('out@example.com');
});

test('exportEmployees_noMatchingEmployees_returnsHeaderOnlyCsv', function () {
    // Act
    $response = $this->get('/api/exports?type=employee&name=NoSuchPersonAtAll');

    // Assert
    $rows = parseEmployeeExportCsv($response->streamedContent());

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toBe(['name', 'email', 'birthday', 'position', 'department_slug']);
});

test('exportEmployees_nonAdminUser_forbidden', function () {
    // Arrange
    $department = Department::factory()->create();
    Sanctum::actingAs(
        Employee::factory()->make([
            'position' => 'manager',
            'department_id' => $department->id,
        ]),
        ['profile:read', 'employees:read', 'employees:create', 'employees:update', 'departments:read', 'departments:update']
    );

    // Act
    $response = $this->get('/api/exports?type=employee');

    // Assert
    $response->assertForbidden()
        ->assertJsonPath('success', false);
});
