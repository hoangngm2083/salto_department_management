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
        'status' => 'active',
    ]);

    Sanctum::actingAs(
        Employee::factory()->make([
            'position' => 'admin',
            'department_id' => $adminDepartment->id,
        ]),
        ['*']
    );
});

test('countEmployees_noFilter_adminExcluded', function () {
    // Arrange
    Employee::factory()->create(['position' => 'employee']);
    Employee::factory()->create(['position' => 'manager']);
    Employee::factory()->create(['position' => 'admin']);

    // Act
    $response = $this->getJson('/api/employees/count');

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.total', 2);
});

test('countEmployees_positionFilter_matchingTotal', function () {
    // Arrange
    Employee::factory()->create(['position' => 'employee']);
    Employee::factory()->create(['position' => 'manager']);

    // Act
    $response = $this->getJson('/api/employees/count?position=manager');

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.total', 1);
});

test('countEmployees_departmentId_matchingTotal', function () {
    // Arrange
    $department = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    Employee::factory()->create(['position' => 'employee', 'department_id' => $otherDepartment->id]);

    // Act
    $response = $this->getJson("/api/employees/count?department_id={$department->id}");

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.total', 1);
});

test('countEmployees_employeeRole_forbidden', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee']);
    Sanctum::actingAs($employee, ['*']);

    // Act
    $response = $this->getJson('/api/employees/count');

    // Assert
    $response->assertForbidden();
});
