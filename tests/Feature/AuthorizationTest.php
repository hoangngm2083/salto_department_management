<?php

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('createEmployee_employeeReadToken_forbidden', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee']);
    Sanctum::actingAs($employee, ['employees:read']);

    // Act
    $response = $this->postJson('/api/employees', []);

    // Assert
    $response->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');
});

test('getEmployee_managerOtherDepartment_forbidden', function () {
    // Arrange
    $managerDepartment = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $manager = Employee::factory()->create([
        'position' => 'manager',
        'department_id' => $managerDepartment->id,
    ]);
    $otherEmployee = Employee::factory()->create([
        'department_id' => $otherDepartment->id,
    ]);
    Sanctum::actingAs($manager, ['employees:read']);

    // Act
    $response = $this->getJson("/api/employees/{$otherEmployee->id}");

    // Assert
    $response->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');
});

test('getEmployees_managerOtherDepartmentFilter_scopedToOwnDepartment', function () {
    // Arrange
    $managerDepartment = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $manager = Employee::factory()->create([
        'position' => 'manager',
        'department_id' => $managerDepartment->id,
    ]);
    Employee::factory()->create(['position' => 'employee', 'department_id' => $managerDepartment->id]);
    Employee::factory()->create(['position' => 'employee', 'department_id' => $otherDepartment->id]);
    Sanctum::actingAs($manager, ['employees:read']);

    // Act
    $response = $this->getJson("/api/employees?department_id={$otherDepartment->id}");

    // Assert
    $response->assertSuccessful();

    $departmentIds = collect($response->json('data.data'))->pluck('department_id')->unique()->values()->all();

    expect($departmentIds)->toBe([$managerDepartment->id]);
});

test('getEmployees_managerDepartmentSlugOtherDepartment_scopedToOwnDepartment', function () {
    // Arrange
    $managerDepartment = Department::factory()->create(['slug' => 'engineering']);
    $otherDepartment = Department::factory()->create(['slug' => 'sales']);
    $manager = Employee::factory()->create([
        'position' => 'manager',
        'department_id' => $managerDepartment->id,
    ]);
    Employee::factory()->create(['position' => 'employee', 'department_id' => $otherDepartment->id]);
    Sanctum::actingAs($manager, ['employees:read']);

    // Act
    $response = $this->getJson('/api/employees?department_slug=sales');

    // Assert
    $response->assertSuccessful();

    $departmentIds = collect($response->json('data.data'))->pluck('department_id')->unique()->values()->all();

    expect($departmentIds)->toBe([$managerDepartment->id]);
});

test('getEmployees_managerNoFilter_scopedToOwnDepartment', function () {
    // Arrange
    $managerDepartment = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $manager = Employee::factory()->create([
        'position' => 'manager',
        'department_id' => $managerDepartment->id,
    ]);
    Employee::factory()->create(['position' => 'employee', 'department_id' => $managerDepartment->id]);
    Employee::factory()->create(['position' => 'employee', 'department_id' => $otherDepartment->id]);
    Sanctum::actingAs($manager, ['employees:read']);

    // Act
    $response = $this->getJson('/api/employees');

    // Assert
    $response->assertSuccessful();

    $departmentIds = collect($response->json('data.data'))->pluck('department_id')->unique()->values()->all();

    expect($departmentIds)->toBe([$managerDepartment->id]);
});

test('getEmployees_employeeReadToken_forbidden', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee']);
    Sanctum::actingAs($employee, ['employees:read']);

    // Act
    $response = $this->getJson('/api/employees');

    // Assert
    $response->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');
});

test('updateEmployee_managerRoleChange_roleUnchanged', function () {
    // Arrange
    $department = Department::factory()->create();
    $manager = Employee::factory()->create([
        'position' => 'manager',
        'department_id' => $department->id,
    ]);
    $employee = Employee::factory()->create([
        'position' => 'employee',
        'department_id' => $department->id,
    ]);
    Sanctum::actingAs($manager, ['employees:update']);

    // Act
    $response = $this->putJson("/api/employees/{$employee->id}", [
        'position' => 'manager',
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.position', 'employee');
});

test('updateEmployee_ownProfile_successful', function () {
    // Arrange
    $employee = Employee::factory()->create([
        'position' => 'employee',
        'name' => 'Before Update',
    ]);
    Sanctum::actingAs($employee, ['employees:update']);

    // Act
    $response = $this->putJson("/api/employees/{$employee->id}", [
        'name' => 'After Update',
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'After Update');

    expect($employee->fresh()->name)->toBe('After Update');
});

test('updateEmployee_employeeDepartmentChange_departmentUnchanged', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee']);
    $otherDepartment = Department::factory()->create();
    Sanctum::actingAs($employee, ['employees:update']);

    // Act
    $response = $this->putJson("/api/employees/{$employee->id}", [
        'department_id' => $otherDepartment->id,
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.department_id', $employee->department_id);
});

test('updateDepartment_managerOwnDepartment_successful', function () {
    // Arrange
    $department = Department::factory()->create(['name' => 'Before Update']);
    $manager = Employee::factory()->create([
        'position' => 'manager',
        'department_id' => $department->id,
    ]);
    Sanctum::actingAs($manager, ['departments:update']);

    // Act
    $response = $this->putJson("/api/departments/{$department->slug}", [
        'name' => 'After Update',
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'After Update');
});

test('createDepartment_managerToken_forbidden', function () {
    // Arrange
    $manager = Employee::factory()->create(['position' => 'manager']);
    Sanctum::actingAs($manager, ['departments:read', 'departments:update']);

    // Act
    $response = $this->postJson('/api/departments', [
        'name' => 'New Department',
    ]);

    // Assert
    $response->assertForbidden()
        ->assertJsonPath('message', 'Forbidden.');
});
