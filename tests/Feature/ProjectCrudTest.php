<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectAssignment;
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

test('getProjects_noFilter_allProjectsReturned', function () {
    // Arrange
    Project::factory()->create(['status' => 'planned']);
    Project::factory()->create(['status' => 'active']);

    // Act
    $response = $this->getJson('/api/projects');

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Projects retrieved successfully.');

    expect($response->json('data.data'))->toHaveCount(2);
});

test('getProjects_statusFilter_matchingProjectsReturned', function () {
    // Arrange
    Project::factory()->create(['status' => 'planned']);
    Project::factory()->create(['status' => 'active']);

    // Act
    $response = $this->getJson('/api/projects?status=active');

    // Assert
    $response->assertSuccessful();

    $statuses = collect($response->json('data.data'))->pluck('status')->unique()->values()->all();

    expect($statuses)->toBe(['active']);
});

test('getProjects_managerFilter_onlyProjectsWithActiveManagerReturned', function () {
    // Arrange
    $manager = Employee::factory()->create(['position' => 'manager', 'status' => 'active']);
    $matchingProject = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $matchingProject->id, 'employee_id' => $manager->id, 'end_date' => null]);

    $formerlyManagedProject = Project::factory()->create();
    ProjectManager::factory()->create(['project_id' => $formerlyManagedProject->id, 'employee_id' => $manager->id, 'end_date' => today()]);

    Project::factory()->create();

    // Act
    $response = $this->getJson("/api/projects?manager_employee_id={$manager->id}");

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toHaveCount(1);
    expect($response->json('data.data.0.id'))->toBe($matchingProject->id);
});

test('getProjects_invalidStatus_validationError', function () {
    // Arrange / Act
    $response = $this->getJson('/api/projects?status=unknown');

    // Assert
    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['status'], 'errors');
});

test('createProject_validPayload_createdWithManager', function () {
    // Arrange
    $manager = Employee::factory()->create(['position' => 'manager', 'status' => 'active']);

    // Act
    $response = $this->postJson('/api/projects', [
        'name' => 'ERP Revamp',
        'description' => 'Rebuild the ERP system',
        'status' => 'active',
        'manager_employee_ids' => [$manager->id],
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Project created successfully.')
        ->assertJsonPath('data.name', 'ERP Revamp')
        ->assertJsonPath('data.slug', 'erp-revamp')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.managers.0.employee_id', $manager->id)
        ->assertJsonPath('data.managers.0.employee_name', $manager->name)
        ->assertJsonPath('data.managers.0.end_date', null);

    $this->assertDatabaseHas('projects', ['name' => 'ERP Revamp', 'slug' => 'erp-revamp']);
    $this->assertDatabaseHas('project_managers', ['employee_id' => $manager->id, 'end_date' => null]);
});

test('createProject_multipleManagers_allCreated', function () {
    // Arrange
    $managerA = Employee::factory()->create(['status' => 'active']);
    $managerB = Employee::factory()->create(['status' => 'active']);

    // Act
    $response = $this->postJson('/api/projects', [
        'name' => 'Multi Manager Project',
        'manager_employee_ids' => [$managerA->id, $managerB->id],
    ]);

    // Assert
    $response->assertCreated();

    expect($response->json('data.managers'))->toHaveCount(2);
    $this->assertDatabaseCount('project_managers', 2);
});

test('createProject_missingManagerEmployeeIds_validationError', function () {
    // Arrange / Act
    $response = $this->postJson('/api/projects', [
        'name' => 'No Manager Project',
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['manager_employee_ids'], 'errors');

    $this->assertDatabaseMissing('projects', ['name' => 'No Manager Project']);
});

test('createProject_inactiveManagerEmployee_validationError', function () {
    // Arrange
    $inactiveEmployee = Employee::factory()->create(['status' => 'inactive']);

    // Act
    $response = $this->postJson('/api/projects', [
        'name' => 'Inactive Manager Project',
        'manager_employee_ids' => [$inactiveEmployee->id],
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['manager_employee_ids.0'], 'errors');
});

test('createProject_missingName_validationError', function () {
    // Arrange
    $manager = Employee::factory()->create(['status' => 'active']);

    // Act
    $response = $this->postJson('/api/projects', [
        'manager_employee_ids' => [$manager->id],
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name'], 'errors');
});

test('createProject_duplicateSlug_validationError', function () {
    // Arrange
    $manager = Employee::factory()->create(['status' => 'active']);
    Project::factory()->create(['name' => 'Existing', 'slug' => 'existing']);

    // Act
    $response = $this->postJson('/api/projects', [
        'name' => 'Existing',
        'manager_employee_ids' => [$manager->id],
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['slug'], 'errors');
});

test('getProject_existingSlug_projectReturned', function () {
    // Arrange
    $project = Project::factory()->create(['name' => 'Visible Project', 'slug' => 'visible-project']);

    // Act
    $response = $this->getJson("/api/projects/{$project->slug}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $project->id)
        ->assertJsonPath('data.name', 'Visible Project');
});

test('getProject_unknownSlug_notFound', function () {
    // Arrange / Act
    $response = $this->getJson('/api/projects/does-not-exist');

    // Assert
    $response->assertNotFound()
        ->assertJsonPath('success', false);
});

test('getProject_employeeWithAssignment_viewable', function () {
    // Arrange
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['position' => 'employee', 'status' => 'active']);
    ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id]);
    Sanctum::actingAs($employee, ['projects:read']);

    // Act
    $response = $this->getJson("/api/projects/{$project->slug}");

    // Assert
    $response->assertSuccessful()->assertJsonPath('data.id', $project->id);
});

test('getProject_employeeWithoutAssignment_forbidden', function () {
    // Arrange
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['position' => 'employee', 'status' => 'active']);
    Sanctum::actingAs($employee, ['projects:read']);

    // Act
    $response = $this->getJson("/api/projects/{$project->slug}");

    // Assert
    $response->assertForbidden();
});

test('getProjects_employeePosition_forbidden', function () {
    // Arrange
    $employee = Employee::factory()->create(['position' => 'employee', 'status' => 'active']);
    Sanctum::actingAs($employee, ['projects:read']);

    // Act
    $response = $this->getJson('/api/projects');

    // Assert: viewAny stays manager+-only even though view() now allows
    // employees with an assignment - an employee still can't browse the
    // full project list, only reach a project they're actually on.
    $response->assertForbidden();
});

test('updateProject_validPayload_updated', function () {
    // Arrange
    $project = Project::factory()->create(['name' => 'Old Name', 'slug' => 'old-name', 'status' => 'planned']);

    // Act
    $response = $this->putJson("/api/projects/{$project->slug}", [
        'name' => 'New Name',
        'status' => 'active',
        'start_date' => '2026-01-01',
    ]);

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.slug', 'new-name')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.start_date', '2026-01-01');

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'New Name', 'status' => 'active']);
});

test('updateProject_endDateBeforeStartDate_validationError', function () {
    // Arrange
    $project = Project::factory()->create();

    // Act
    $response = $this->putJson("/api/projects/{$project->slug}", [
        'name' => $project->name,
        'start_date' => '2026-06-01',
        'end_date' => '2026-01-01',
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date'], 'errors');
});

test('deleteProject_existingSlug_softDeleted', function () {
    // Arrange
    $project = Project::factory()->create(['name' => 'To Delete', 'slug' => 'to-delete']);

    // Act
    $response = $this->deleteJson("/api/projects/{$project->slug}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Project deleted successfully.');

    $this->assertSoftDeleted($project);
});
