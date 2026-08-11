<?php

use App\Models\AssignmentRolePeriod;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\ProjectManager;
use App\Models\ProjectRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $adminDepartment = Department::factory()->create([
        'name' => 'Admin Department',
        'slug' => 'admin-department',
        'status' => 'active',
    ]);

    // Persisted (not ->make()) because ProjectAssignmentService::create() writes
    // the actor's id into project_assignments.assigned_by, a NOT NULL FK.
    Sanctum::actingAs(
        Employee::factory()->create([
            'position' => 'admin',
            'department_id' => $adminDepartment->id,
        ]),
        ['*']
    );
});

test('createAssignment_activeEmployeeAndRoles_created', function () {
    // Arrange
    $project = Project::factory()->create(['status' => 'active']);
    $employee = Employee::factory()->create(['status' => 'active']);
    $role = ProjectRole::factory()->create();

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/assignments", [
        'employee_ids' => [$employee->id],
        'role_ids' => [$role->id],
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.employee_id', $employee->id)
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.0.role_periods.0.project_role_id', $role->id)
        ->assertJsonPath('data.0.role_periods.0.end_date', null);

    $this->assertDatabaseHas('project_assignments', [
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'status' => 'active',
    ]);
});

test('createAssignment_multipleRoles_allCreated', function () {
    // Arrange
    $project = Project::factory()->create(['status' => 'active']);
    $employee = Employee::factory()->create(['status' => 'active']);
    $roleA = ProjectRole::factory()->create();
    $roleB = ProjectRole::factory()->create();

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/assignments", [
        'employee_ids' => [$employee->id],
        'role_ids' => [$roleA->id, $roleB->id],
    ]);

    // Assert
    $response->assertCreated();
    expect($response->json('data.0.role_periods'))->toHaveCount(2);
    $this->assertDatabaseCount('assignment_role_periods', 2);
});

test('createAssignment_multipleEmployees_allCreatedWithSameRoles', function () {
    // Arrange
    $project = Project::factory()->create(['status' => 'active']);
    $employeeA = Employee::factory()->create(['status' => 'active']);
    $employeeB = Employee::factory()->create(['status' => 'active']);
    $role = ProjectRole::factory()->create();

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/assignments", [
        'employee_ids' => [$employeeA->id, $employeeB->id],
        'role_ids' => [$role->id],
    ]);

    // Assert
    $response->assertCreated();
    expect($response->json('data'))->toHaveCount(2);
    $this->assertDatabaseHas('project_assignments', ['project_id' => $project->id, 'employee_id' => $employeeA->id]);
    $this->assertDatabaseHas('project_assignments', ['project_id' => $project->id, 'employee_id' => $employeeB->id]);
    $this->assertDatabaseCount('assignment_role_periods', 2);
});

test('createAssignment_missingRoleIds_validationError', function () {
    // Arrange
    $project = Project::factory()->create(['status' => 'active']);
    $employee = Employee::factory()->create(['status' => 'active']);

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/assignments", [
        'employee_ids' => [$employee->id],
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['role_ids'], 'errors');
});

test('createAssignment_inactiveEmployee_validationError', function () {
    // Arrange
    $project = Project::factory()->create(['status' => 'active']);
    $employee = Employee::factory()->create(['status' => 'inactive']);
    $role = ProjectRole::factory()->create();

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/assignments", [
        'employee_ids' => [$employee->id],
        'role_ids' => [$role->id],
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_ids.0'], 'errors');
});

test('createAssignment_duplicateEmployeeIds_validationError', function () {
    // Arrange
    $project = Project::factory()->create(['status' => 'active']);
    $employee = Employee::factory()->create(['status' => 'active']);
    $role = ProjectRole::factory()->create();

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/assignments", [
        'employee_ids' => [$employee->id, $employee->id],
        'role_ids' => [$role->id],
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_ids.0', 'employee_ids.1'], 'errors');
});

test('createAssignment_alreadyActiveOnProject_validationError', function () {
    // Arrange
    $project = Project::factory()->create(['status' => 'active']);
    $employee = Employee::factory()->create(['status' => 'active']);
    $role = ProjectRole::factory()->create();
    ProjectAssignment::factory()->create([
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'status' => 'active',
    ]);

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/assignments", [
        'employee_ids' => [$employee->id],
        'role_ids' => [$role->id],
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_ids'], 'errors');
});

test('createAssignment_oneOfManyAlreadyActive_rollsBackWholeBatch', function () {
    // Arrange
    $project = Project::factory()->create(['status' => 'active']);
    $employeeA = Employee::factory()->create(['status' => 'active']);
    $employeeB = Employee::factory()->create(['status' => 'active']);
    $role = ProjectRole::factory()->create();
    ProjectAssignment::factory()->create([
        'project_id' => $project->id,
        'employee_id' => $employeeB->id,
        'status' => 'active',
    ]);

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/assignments", [
        'employee_ids' => [$employeeA->id, $employeeB->id],
        'role_ids' => [$role->id],
    ]);

    // Assert: employeeA must not end up assigned either, even though only
    // employeeB conflicted - all-or-nothing per the batch decision.
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_ids'], 'errors');
    $this->assertDatabaseMissing('project_assignments', ['project_id' => $project->id, 'employee_id' => $employeeA->id]);
});

test('createAssignment_projectCompleted_validationError', function () {
    // Arrange
    $project = Project::factory()->create(['status' => 'completed']);
    $employee = Employee::factory()->create(['status' => 'active']);
    $role = ProjectRole::factory()->create();

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/assignments", [
        'employee_ids' => [$employee->id],
        'role_ids' => [$role->id],
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['project'], 'errors');

    $this->assertDatabaseMissing('project_assignments', ['project_id' => $project->id]);
});

test('createAssignment_projectPlanned_created', function () {
    // Arrange
    $project = Project::factory()->create(['status' => 'planned']);
    $employee = Employee::factory()->create(['status' => 'active']);
    $role = ProjectRole::factory()->create();

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/assignments", [
        'employee_ids' => [$employee->id],
        'role_ids' => [$role->id],
    ]);

    // Assert
    $response->assertCreated();
});

test('createAssignment_startDateAfterProjectEndDate_validationError', function () {
    // Arrange
    $project = Project::factory()->create(['status' => 'active', 'end_date' => '2026-01-01']);
    $employee = Employee::factory()->create(['status' => 'active']);
    $role = ProjectRole::factory()->create();

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/assignments", [
        'employee_ids' => [$employee->id],
        'role_ids' => [$role->id],
        'start_date' => '2026-02-01',
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['start_date'], 'errors');
});

test('createAssignment_asProjectManager_created', function () {
    // Arrange
    $department = Department::factory()->create();
    $pm = Employee::factory()->create(['position' => 'manager', 'department_id' => $department->id, 'status' => 'active']);
    $project = Project::factory()->create(['status' => 'active']);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $pm->id, 'end_date' => null]);
    $employee = Employee::factory()->create(['status' => 'active']);
    $role = ProjectRole::factory()->create();
    Sanctum::actingAs($pm, ['projects:read', 'projects:manage-assignments']);

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/assignments", [
        'employee_ids' => [$employee->id],
        'role_ids' => [$role->id],
    ]);

    // Assert
    $response->assertCreated();
});

test('createAssignment_managerNotProjectManager_forbidden', function () {
    // Arrange
    $manager = Employee::factory()->create(['position' => 'manager', 'status' => 'active']);
    $project = Project::factory()->create(['status' => 'active']);
    ProjectManager::factory()->create(['project_id' => $project->id]);
    $employee = Employee::factory()->create(['status' => 'active']);
    $role = ProjectRole::factory()->create();
    Sanctum::actingAs($manager, ['projects:read', 'projects:manage-assignments']);

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/assignments", [
        'employee_ids' => [$employee->id],
        'role_ids' => [$role->id],
    ]);

    // Assert
    $response->assertForbidden();
});

test('endAssignment_active_endedAndRolePeriodsClosed', function () {
    // Arrange
    $project = Project::factory()->create();
    $assignment = ProjectAssignment::factory()->create(['project_id' => $project->id, 'status' => 'active', 'end_date' => null]);
    $rolePeriod = AssignmentRolePeriod::factory()->create(['project_assignment_id' => $assignment->id, 'end_date' => null]);

    // Act
    $response = $this->deleteJson("/api/projects/{$project->slug}/assignments/{$assignment->id}");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.status', 'ended');

    expect($assignment->fresh()->end_date->toDateString())->toBe(today()->toDateString());
    expect($rolePeriod->fresh()->end_date->toDateString())->toBe(today()->toDateString());
});

test('endAssignment_notBelongingToProject_notFound', function () {
    // Arrange
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();
    $assignmentOfB = ProjectAssignment::factory()->create(['project_id' => $projectB->id]);

    // Act
    $response = $this->deleteJson("/api/projects/{$projectA->slug}/assignments/{$assignmentOfB->id}");

    // Assert
    $response->assertNotFound();
});

test('addRole_activeAssignment_added', function () {
    // Arrange
    $project = Project::factory()->create();
    $assignment = ProjectAssignment::factory()->create(['project_id' => $project->id, 'status' => 'active']);
    $role = ProjectRole::factory()->create();

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/assignments/{$assignment->id}/roles", [
        'project_role_id' => $role->id,
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.project_role_id', $role->id)
        ->assertJsonPath('data.end_date', null);
});

test('addRole_duplicateActiveRole_validationError', function () {
    // Arrange
    $project = Project::factory()->create();
    $assignment = ProjectAssignment::factory()->create(['project_id' => $project->id, 'status' => 'active']);
    $role = ProjectRole::factory()->create();
    AssignmentRolePeriod::factory()->create(['project_assignment_id' => $assignment->id, 'project_role_id' => $role->id, 'end_date' => null]);

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/assignments/{$assignment->id}/roles", [
        'project_role_id' => $role->id,
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['project_role_id'], 'errors');
});

test('addRole_endedAssignment_validationError', function () {
    // Arrange
    $project = Project::factory()->create();
    $assignment = ProjectAssignment::factory()->create(['project_id' => $project->id, 'status' => 'ended', 'end_date' => today()]);
    $role = ProjectRole::factory()->create();

    // Act
    $response = $this->postJson("/api/projects/{$project->slug}/assignments/{$assignment->id}/roles", [
        'project_role_id' => $role->id,
    ]);

    // Assert
    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['project_role_id'], 'errors');
});

test('endRole_active_ended', function () {
    // Arrange
    $project = Project::factory()->create();
    $assignment = ProjectAssignment::factory()->create(['project_id' => $project->id, 'status' => 'active']);
    $rolePeriod = AssignmentRolePeriod::factory()->create(['project_assignment_id' => $assignment->id, 'end_date' => null]);

    // Act
    $response = $this->deleteJson("/api/projects/{$project->slug}/assignments/{$assignment->id}/roles/{$rolePeriod->id}");

    // Assert
    $response->assertSuccessful();
    expect($rolePeriod->fresh()->end_date->toDateString())->toBe(today()->toDateString());
});

test('endRole_notBelongingToAssignment_notFound', function () {
    // Arrange
    $project = Project::factory()->create();
    $assignmentA = ProjectAssignment::factory()->create(['project_id' => $project->id]);
    $assignmentB = ProjectAssignment::factory()->create(['project_id' => $project->id]);
    $rolePeriodOfB = AssignmentRolePeriod::factory()->create(['project_assignment_id' => $assignmentB->id]);

    // Act
    $response = $this->deleteJson("/api/projects/{$project->slug}/assignments/{$assignmentA->id}/roles/{$rolePeriodOfB->id}");

    // Assert
    $response->assertNotFound();
});

test('getAssignments_listsForProject', function () {
    // Arrange
    $project = Project::factory()->create();
    ProjectAssignment::factory()->count(2)->create(['project_id' => $project->id]);
    ProjectAssignment::factory()->create();

    // Act
    $response = $this->getJson("/api/projects/{$project->slug}/assignments");

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toHaveCount(2);
});

test('workHistory_employeeWithProjectsAndRoles_returnsNestedHistory', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $project = Project::factory()->create(['name' => 'ERP', 'slug' => 'erp']);
    $assignment = ProjectAssignment::factory()->create([
        'project_id' => $project->id,
        'employee_id' => $employee->id,
        'status' => 'active',
    ]);
    $role = ProjectRole::factory()->create(['name' => 'Backend']);
    AssignmentRolePeriod::factory()->create([
        'project_assignment_id' => $assignment->id,
        'project_role_id' => $role->id,
        'end_date' => null,
    ]);

    // Act
    $response = $this->getJson("/api/employees/{$employee->id}/projects");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.employee.id', $employee->id)
        ->assertJsonPath('data.projects.0.project_id', $project->id)
        ->assertJsonPath('data.projects.0.project', 'ERP')
        ->assertJsonPath('data.projects.0.project_slug', 'erp')
        ->assertJsonPath('data.projects.0.roles.0.role', 'Backend');
});

test('workHistory_activeFilter_excludesEndedAssignments', function () {
    // Arrange
    $employee = Employee::factory()->create();
    $activeProject = Project::factory()->create(['name' => 'Active Project']);
    $endedProject = Project::factory()->create(['name' => 'Ended Project']);
    ProjectAssignment::factory()->create([
        'project_id' => $activeProject->id,
        'employee_id' => $employee->id,
        'end_date' => null,
    ]);
    ProjectAssignment::factory()->create([
        'project_id' => $endedProject->id,
        'employee_id' => $employee->id,
        'end_date' => today()->subDay(),
    ]);

    // Act
    $response = $this->getJson("/api/employees/{$employee->id}/projects?active=1");

    // Assert
    $response->assertSuccessful()
        ->assertJsonCount(1, 'data.projects')
        ->assertJsonPath('data.projects.0.project', 'Active Project');
});
