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

test('case_commaSeparatedPositions_returnsMatchingEmployees', function () {
    // Arrange
    Employee::factory()->create(['position' => 'employee', 'name' => 'Alice Employee']);
    Employee::factory()->create(['position' => 'manager', 'name' => 'Bob Manager']);
    Employee::factory()->create(['position' => 'admin', 'name' => 'Carol Admin']);

    // Act
    $response = $this->getJson('/api/employees?position=employee,manager');

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true);

    $positions = collect($response->json('data.data'))->pluck('position')->unique()->sort()->values()->all();

    expect($positions)->toBe(['employee', 'manager'])
        ->and($response->json('data.data'))->toHaveCount(2);
});

test('case_singlePosition_returnsMatchingEmployees', function () {
    // Arrange
    Employee::factory()->create(['position' => 'employee']);
    Employee::factory()->create(['position' => 'manager']);

    // Act
    $response = $this->getJson('/api/employees?position=employee');

    // Assert
    $response->assertSuccessful();

    $positions = collect($response->json('data.data'))->pluck('position')->unique()->values()->all();

    expect($positions)->toBe(['employee']);
});

test('case_positionsWithSpaces_returnsMatchingEmployees', function () {
    // Arrange
    Employee::factory()->create(['position' => 'employee']);
    Employee::factory()->create(['position' => 'manager']);

    // Act
    $response = $this->getJson('/api/employees?position=employee,%20manager');

    // Assert
    $response->assertSuccessful();

    expect($response->json('data.data'))->toHaveCount(2);
});

test('case_positionArrayQuery_returnsMatchingEmployees', function () {
    // Arrange
    Employee::factory()->create(['position' => 'employee']);
    Employee::factory()->create(['position' => 'manager']);

    // Act
    $response = $this->getJson('/api/employees?position[]=employee&position[]=manager');

    // Assert
    $response->assertSuccessful();

    expect($response->json('data.data'))->toHaveCount(2);
});

test('case_noPositionFilter_excludesAdmin', function () {
    // Arrange
    Employee::factory()->create(['position' => 'employee']);
    Employee::factory()->create(['position' => 'admin']);

    // Act
    $response = $this->getJson('/api/employees');

    // Assert
    $response->assertSuccessful();

    $positions = collect($response->json('data.data'))->pluck('position')->unique()->values()->all();

    expect($positions)->toBe(['employee']);
});

test('case_invalidPositions_returns422', function () {
    // Arrange / Act
    $response = $this->getJson('/api/employees?position=p1,p2');

    // Assert
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['position.0', 'position.1'], 'errors');
});

test('case_validPayload_createsEmployee', function () {
    // Arrange
    $department = Department::factory()->create();
    $payload = [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'department_id' => $department->id,
        'birthday' => '1995-05-20',
        'position' => 'employee',
    ];

    // Act
    $response = $this->postJson('/api/employees', $payload);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Employee created successfully.')
        ->assertJsonPath('data.name', 'Jane Doe')
        ->assertJsonPath('data.email', 'jane@example.com')
        ->assertJsonPath('data.department_id', $department->id)
        ->assertJsonPath('data.department_name', $department->name)
        ->assertJsonPath('data.position', 'employee')
        ->assertJsonMissingPath('data.password');

    $this->assertDatabaseHas('employees', [
        'email' => 'jane@example.com',
        'name' => 'Jane Doe',
        'department_id' => $department->id,
    ]);
});

test('case_missingRequiredFields_returns422', function () {
    // Arrange / Act
    $response = $this->postJson('/api/employees', []);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['name', 'email', 'password', 'department_id', 'birthday', 'position'], 'errors');
});

test('case_duplicateEmail_returns422', function () {
    // Arrange
    $department = Department::factory()->create();
    Employee::factory()->create(['email' => 'taken@example.com']);

    // Act
    $response = $this->postJson('/api/employees', [
        'name' => 'Jane Doe',
        'email' => 'taken@example.com',
        'password' => 'password123',
        'department_id' => $department->id,
        'birthday' => '1995-05-20',
        'position' => 'employee',
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email'], 'errors');
});

test('case_existingId_returnsEmployee', function () {
    // Arrange
    $employee = Employee::factory()->create([
        'name' => 'John Show',
        'email' => 'john.show@example.com',
    ]);

    // Act
    $response = $this->getJson("/api/employees/{$employee->id}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Employee retrieved successfully.')
        ->assertJsonPath('data.id', $employee->id)
        ->assertJsonPath('data.name', 'John Show')
        ->assertJsonPath('data.email', 'john.show@example.com')
        ->assertJsonPath('data.department_name', $employee->department->name);
});

test('case_unknownId_returns404', function () {
    // Arrange / Act
    $response = $this->getJson('/api/employees/999999');

    // Assert
    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Resource not found.');
});

test('case_validPayload_updatesEmployee', function () {
    // Arrange
    $department = Department::factory()->create();
    $newDepartment = Department::factory()->create();
    $employee = Employee::factory()->create([
        'department_id' => $department->id,
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'position' => 'employee',
    ]);

    $payload = [
        'name' => 'New Name',
        'email' => 'new@example.com',
        'department_id' => $newDepartment->id,
        'birthday' => '1990-01-15',
        'position' => 'manager',
    ];

    // Act
    $response = $this->putJson("/api/employees/{$employee->id}", $payload);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Employee updated successfully.')
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.email', 'new@example.com')
        ->assertJsonPath('data.department_id', $newDepartment->id)
        ->assertJsonPath('data.position', 'manager');

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'name' => 'New Name',
        'email' => 'new@example.com',
        'department_id' => $newDepartment->id,
        'position' => 'manager',
    ]);
});

test('case_updateKeepsOwnEmail_succeeds', function () {
    // Arrange
    $employee = Employee::factory()->create(['email' => 'keep@example.com']);

    // Act
    $response = $this->putJson("/api/employees/{$employee->id}", [
        'name' => 'Updated Name',
        'email' => 'keep@example.com',
        'department_id' => $employee->department_id,
        'birthday' => '1992-03-10',
        'position' => 'employee',
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.email', 'keep@example.com')
        ->assertJsonPath('data.name', 'Updated Name');
});

test('case_existingId_softDeletesEmployee', function () {
    // Arrange
    $employee = Employee::factory()->create();

    // Act
    $response = $this->deleteJson("/api/employees/{$employee->id}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Employee deleted successfully.')
        ->assertJsonPath('data', null);

    $this->assertSoftDeleted($employee);
});

test('case_deletedId_returns404', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $employee->delete();

    // Act
    $response = $this->getJson("/api/employees/{$employee->id}");

    // Assert
    $response->assertNotFound()
        ->assertJsonPath('success', false);
});
