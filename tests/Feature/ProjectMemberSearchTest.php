<?php

use App\Models\AssignmentRolePeriod;
use App\Models\Employee;
use App\Models\Level;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\ProjectRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(
        Employee::factory()->make(['position' => 'admin']),
        ['*']
    );
});

test('searchMembers_returnsActiveAssignedEmployeesWithLevelAndRoles', function () {
    // Arrange
    $project = Project::factory()->create();
    $level = Level::factory()->create(['name' => 'Senior']);
    $employee = Employee::factory()->create(['name' => 'Nguyen Van Hoang', 'current_level_id' => $level->id, 'status' => 'active']);
    $assignment = ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'status' => 'active']);
    $role = ProjectRole::factory()->create(['name' => 'Backend']);
    AssignmentRolePeriod::factory()->create(['project_assignment_id' => $assignment->id, 'project_role_id' => $role->id, 'end_date' => null]);

    // Act
    $response = $this->getJson("/api/projects/{$project->slug}/members");

    // Assert
    $response->assertSuccessful()
        ->assertJsonPath('data.data.0.id', $employee->id)
        ->assertJsonPath('data.data.0.level', 'Senior')
        ->assertJsonPath('data.data.0.roles.0', 'Backend');
});

test('searchMembers_endedAssignment_excluded', function () {
    // Arrange
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['status' => 'active']);
    ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'status' => 'ended', 'end_date' => today()]);

    // Act
    $response = $this->getJson("/api/projects/{$project->slug}/members");

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toHaveCount(0);
});

test('searchMembers_nameFilter_matchesContains', function () {
    // Arrange
    $project = Project::factory()->create();
    $matching = Employee::factory()->create(['name' => 'Tran Thi Mai', 'status' => 'active']);
    $nonMatching = Employee::factory()->create(['name' => 'Le Van Nam', 'status' => 'active']);
    ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $matching->id, 'status' => 'active']);
    ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $nonMatching->id, 'status' => 'active']);

    // Act
    $response = $this->getJson("/api/projects/{$project->slug}/members?name=Mai");

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toHaveCount(1);
    expect($response->json('data.data.0.id'))->toBe($matching->id);
});

test('searchMembers_projectRoleFilter_matchesOnlyThatRole', function () {
    // Arrange
    $project = Project::factory()->create();
    $backendEmployee = Employee::factory()->create(['status' => 'active']);
    $frontendEmployee = Employee::factory()->create(['status' => 'active']);
    $backendAssignment = ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $backendEmployee->id, 'status' => 'active']);
    $frontendAssignment = ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $frontendEmployee->id, 'status' => 'active']);
    $backendRole = ProjectRole::factory()->create(['name' => 'Backend']);
    $frontendRole = ProjectRole::factory()->create(['name' => 'Frontend']);
    AssignmentRolePeriod::factory()->create(['project_assignment_id' => $backendAssignment->id, 'project_role_id' => $backendRole->id, 'end_date' => null]);
    AssignmentRolePeriod::factory()->create(['project_assignment_id' => $frontendAssignment->id, 'project_role_id' => $frontendRole->id, 'end_date' => null]);

    // Act
    $response = $this->getJson("/api/projects/{$project->slug}/members?project_role_id={$backendRole->id}");

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toHaveCount(1);
    expect($response->json('data.data.0.id'))->toBe($backendEmployee->id);
});
