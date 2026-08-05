<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Level;
use App\Models\Project;
use App\Models\ProjectManager;
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

test('getEmployees_commaSeparatedPositions_matchingEmployees', function () {
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

test('getEmployees_nameMatchesMiddleOfName_matchingEmployees', function () {
    // Arrange
    Employee::factory()->create(['position' => 'employee', 'name' => 'Test Manager']);
    Employee::factory()->create(['position' => 'employee', 'name' => 'Manager Test']);
    Employee::factory()->create(['position' => 'employee', 'name' => 'Alice Nguyen']);

    // Act
    $response = $this->getJson('/api/employees?name=Manager');

    // Assert
    $response->assertSuccessful();

    $names = collect($response->json('data.data'))->pluck('name')->sort()->values()->all();

    expect($names)->toBe(['Manager Test', 'Test Manager']);
});

test('getEmployees_nameWithLikeWildcard_treatsWildcardLiterally', function () {
    // Arrange
    Employee::factory()->create(['position' => 'employee', 'name' => 'Discount 50% Team']);
    Employee::factory()->create(['position' => 'employee', 'name' => 'Anything At All']);

    // Act
    $response = $this->getJson('/api/employees?name='.urlencode('50%'));

    // Assert
    $response->assertSuccessful();

    $names = collect($response->json('data.data'))->pluck('name')->all();

    expect($names)->toBe(['Discount 50% Team']);
});

test('getEmployees_singlePosition_matchingEmployees', function () {
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

test('getEmployees_positionsWithSpaces_matchingEmployees', function () {
    // Arrange
    Employee::factory()->create(['position' => 'employee']);
    Employee::factory()->create(['position' => 'manager']);

    // Act
    $response = $this->getJson('/api/employees?position=employee,%20manager');

    // Assert
    $response->assertSuccessful();

    expect($response->json('data.data'))->toHaveCount(2);
});

test('getEmployees_positionArray_matchingEmployees', function () {
    // Arrange
    Employee::factory()->create(['position' => 'employee']);
    Employee::factory()->create(['position' => 'manager']);

    // Act
    $response = $this->getJson('/api/employees?position[]=employee&position[]=manager');

    // Assert
    $response->assertSuccessful();

    expect($response->json('data.data'))->toHaveCount(2);
});

test('getEmployees_noPositionFilter_adminExcluded', function () {
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

test('getEmployees_invalidPositions_validationError', function () {
    // Arrange / Act
    $response = $this->getJson('/api/employees?position=p1,p2');

    // Assert
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['position.0', 'position.1'], 'errors');
});

test('getEmployees_departmentId_matchingEmployees', function () {
    // Arrange
    $department = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    Employee::factory()->create(['position' => 'employee', 'department_id' => $otherDepartment->id]);

    // Act
    $response = $this->getJson("/api/employees?department_id={$department->id}");

    // Assert
    $response->assertSuccessful();

    $departmentIds = collect($response->json('data.data'))->pluck('department_id')->unique()->values()->all();

    expect($departmentIds)->toBe([$department->id]);
});

test('getEmployees_departmentSlug_matchingEmployees', function () {
    // Arrange
    $department = Department::factory()->create(['slug' => 'engineering']);
    $otherDepartment = Department::factory()->create(['slug' => 'sales']);
    Employee::factory()->create(['position' => 'employee', 'department_id' => $department->id]);
    Employee::factory()->create(['position' => 'employee', 'department_id' => $otherDepartment->id]);

    // Act
    $response = $this->getJson('/api/employees?department_slug=engineering');

    // Assert
    $response->assertSuccessful();

    $slugs = collect($response->json('data.data'))->pluck('department_slug')->unique()->values()->all();

    expect($slugs)->toBe(['engineering']);
});

test('getEmployees_unknownDepartmentId_validationError', function () {
    // Arrange / Act
    $response = $this->getJson('/api/employees?department_id=999999');

    // Assert
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['department_id'], 'errors');
});

test('getEmployees_unknownDepartmentSlug_validationError', function () {
    // Arrange / Act
    $response = $this->getJson('/api/employees?department_slug=unknown-slug');

    // Assert
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['department_slug'], 'errors');
});

test('createEmployee_validPayload_created', function () {
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

test('createEmployee_missingRequiredFields_validationError', function () {
    // Arrange / Act
    $response = $this->postJson('/api/employees', []);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['name', 'email', 'password', 'department_id', 'birthday', 'position'], 'errors');
});

test('createEmployee_duplicateEmail_validationError', function () {
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

test('getEmployee_existingId_employeeReturned', function () {
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

test('getEmployee_unknownId_notFound', function () {
    // Arrange / Act
    $response = $this->getJson('/api/employees/999999');

    // Assert
    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Resource not found.');
});

test('updateEmployee_validPayload_updated', function () {
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

test('updateEmployee_existingEmail_updated', function () {
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

test('deleteEmployee_existingId_softDeleted', function () {
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

test('getEmployee_deletedId_notFound', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $employee->delete();

    // Act
    $response = $this->getJson("/api/employees/{$employee->id}");

    // Assert
    $response->assertNotFound()
        ->assertJsonPath('success', false);
});

test('createEmployee_withoutStatus_defaultsToActive', function () {
    // Arrange
    $department = Department::factory()->create();

    // Act
    $response = $this->postJson('/api/employees', [
        'name' => 'Default Status',
        'email' => 'default.status@example.com',
        'password' => 'password123',
        'department_id' => $department->id,
        'birthday' => '1995-05-20',
        'position' => 'employee',
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.status', 'active');
});

test('updateEmployee_setsCurrentLevelAndManager_fieldsUpdated', function () {
    // Arrange
    $level = Level::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager']);
    $employee = Employee::factory()->create(['position' => 'employee']);

    // Act
    $response = $this->putJson("/api/employees/{$employee->id}", [
        'current_level_id' => $level->id,
        'manager_employee_id' => $manager->id,
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.current_level_id', $level->id)
        ->assertJsonPath('data.current_level_name', $level->name)
        ->assertJsonPath('data.manager_employee_id', $manager->id)
        ->assertJsonPath('data.manager_name', $manager->name);

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'current_level_id' => $level->id,
        'manager_employee_id' => $manager->id,
    ]);
});

test('updateEmployee_clearsManagerEmployeeId_fieldCleared', function () {
    // Arrange
    $manager = Employee::factory()->create(['position' => 'manager']);
    $employee = Employee::factory()->create(['manager_employee_id' => $manager->id]);

    // Act
    $response = $this->putJson("/api/employees/{$employee->id}", [
        'manager_employee_id' => null,
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.manager_employee_id', null);

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'manager_employee_id' => null,
    ]);
});

test('updateEmployee_managerEmployeeIdSelfReference_validationError', function () {
    // Arrange
    $employee = Employee::factory()->create();

    // Act
    $response = $this->putJson("/api/employees/{$employee->id}", [
        'manager_employee_id' => $employee->id,
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['manager_employee_id'], 'errors');
});

test('updateEmployee_unknownCurrentLevelId_validationError', function () {
    // Arrange
    $employee = Employee::factory()->create();

    // Act
    $response = $this->putJson("/api/employees/{$employee->id}", [
        'current_level_id' => 999999,
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['current_level_id'], 'errors');
});

test('deleteEmployee_soleActiveProjectManager_validationError', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $project = Project::factory()->create(['name' => 'Guarded Project']);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'end_date' => null]);

    // Act
    $response = $this->deleteJson("/api/employees/{$employee->id}");

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['employee'], 'errors');

    expect($employee->fresh()->trashed())->toBeFalse();
});

test('deleteEmployee_coManagedProject_softDeleted', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $otherManager = Employee::factory()->create();
    $project = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'end_date' => null]);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $otherManager->id, 'end_date' => null]);

    // Act
    $response = $this->deleteJson("/api/employees/{$employee->id}");

    // Assert
    $response->assertSuccessful();
    $this->assertSoftDeleted($employee);
});

test('updateEmployee_resignSoleActiveProjectManager_validationError', function () {
    // Arrange
    $employee = Employee::factory()->create(['status' => 'active']);
    $project = Project::factory()->create(['name' => 'Guarded Project']);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'end_date' => null]);

    // Act
    $response = $this->putJson("/api/employees/{$employee->id}", [
        'status' => 'resigned',
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['employee'], 'errors');

    expect($employee->fresh()->status->value)->toBe('active');
});

test('updateEmployee_resignCoManagedProject_statusChanged', function () {
    // Arrange
    $employee = Employee::factory()->create(['status' => 'active']);
    $otherManager = Employee::factory()->create();
    $project = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'end_date' => null]);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $otherManager->id, 'end_date' => null]);

    // Act
    $response = $this->putJson("/api/employees/{$employee->id}", [
        'status' => 'resigned',
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'resigned');
});

test('updateEmployee_adminSetsStatus_statusChanged', function () {
    // Arrange
    $employee = Employee::factory()->create(['status' => 'active']);

    // Act
    $response = $this->putJson("/api/employees/{$employee->id}", [
        'status' => 'resigned',
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'resigned');

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'status' => 'resigned',
    ]);
});
