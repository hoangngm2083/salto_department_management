<?php

use App\Models\Department;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\CursorPaginator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('getEmployees_namePrefix_matchingEmployees', function () {
    // Arrange
    Employee::factory()->create(['name' => 'Alice Smith', 'position' => 'employee']);
    Employee::factory()->create(['name' => 'Bob Jones', 'position' => 'employee']);
    $service = app(EmployeeService::class);

    // Act
    $result = $service->getPaginated(['name' => 'Ali']);

    // Assert
    expect($result)->toBeInstanceOf(CursorPaginator::class)
        ->and($result->count())->toBe(1)
        ->and($result->items()[0]->name)->toBe('Alice Smith');
});

test('getEmployees_emptyPosition_adminExcluded', function () {
    // Arrange
    Employee::factory()->create(['position' => 'employee']);
    Employee::factory()->create(['position' => 'manager']);
    Employee::factory()->create(['position' => 'admin']);
    $service = app(EmployeeService::class);

    // Act
    $result = $service->getPaginated([]);

    // Assert
    $positions = collect($result->items())->pluck('position')->unique()->sort()->values()->all();

    expect($positions)->toBe(['employee', 'manager'])
        ->and($result->count())->toBe(2);
});

test('getEmployees_positionFilter_selectedEmployees', function () {
    // Arrange
    Employee::factory()->create(['position' => 'employee']);
    Employee::factory()->create(['position' => 'manager']);
    $service = app(EmployeeService::class);

    // Act
    $result = $service->getPaginated(['position' => ['manager']]);

    // Assert
    expect($result->count())->toBe(1)
        ->and($result->items()[0]->position)->toBe('manager');
});

test('upsertEmployee_validData_employeeCreated', function () {
    // Arrange
    $department = Department::factory()->create();
    $admin = Employee::factory()->create(['position' => 'admin']);
    $service = app(EmployeeService::class);
    $data = [
        'name' => 'Service Create',
        'email' => 'service.create@example.com',
        'password' => 'password123',
        'department_id' => $department->id,
        'birthday' => '1998-08-08',
        'position' => 'employee',
    ];

    // Act
    $employee = $service->upsert($admin, $data);

    // Assert
    expect($employee)->toBeInstanceOf(Employee::class)
        ->and($employee->name)->toBe('Service Create')
        ->and($employee->email)->toBe('service.create@example.com')
        ->and($employee->department_id)->toBe($department->id)
        ->and($employee->relationLoaded('department'))->toBeTrue()
        ->and($employee->department->name)->toBe($department->name);

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'email' => 'service.create@example.com',
    ]);
});

test('upsertEmployee_existingEmployee_recordUpdated', function () {
    // Arrange
    $employee = Employee::factory()->create([
        'name' => 'Before Update',
        'email' => 'before@example.com',
        'position' => 'employee',
    ]);
    $admin = Employee::factory()->create(['position' => 'admin']);
    $service = app(EmployeeService::class);

    // Act
    $updated = $service->upsert($admin, [
        'name' => 'After Update',
        'email' => 'after@example.com',
        'department_id' => $employee->department_id,
        'birthday' => '1991-01-01',
        'position' => 'manager',
    ], $employee);

    // Assert
    expect($updated->id)->toBe($employee->id)
        ->and($updated->name)->toBe('After Update')
        ->and($updated->email)->toBe('after@example.com')
        ->and($updated->position)->toBe('manager')
        ->and($updated->relationLoaded('department'))->toBeTrue();
});

test('deleteEmployee_existingEmployee_recordSoftDeleted', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $service = app(EmployeeService::class);

    // Act
    $deleted = $service->delete($employee);

    // Assert
    expect($deleted)->toBeTrue();
    $this->assertSoftDeleted($employee);
});
