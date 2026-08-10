<?php

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\ProjectManager;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('updateTaskStatus_notAssignee_forbidden', function () {
    // Arrange
    $project = Project::factory()->create();
    $assignee = Employee::factory()->create(['status' => 'active']);
    $otherEmployee = Employee::factory()->create(['status' => 'active']);
    ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $assignee->id, 'status' => 'active']);
    $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $assignee->id, 'status' => 'todo']);
    Sanctum::actingAs($otherEmployee, ['tasks:read', 'tasks:update']);

    // Act
    $response = $this->patchJson("/api/tasks/{$task->id}", ['status' => 'in_progress']);

    // Assert
    $response->assertForbidden();
});

test('updateTaskStatus_assigneeCannotApproveOwnInReviewTask', function () {
    // Arrange
    $project = Project::factory()->create();
    $assignee = Employee::factory()->create(['status' => 'active']);
    ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $assignee->id, 'status' => 'active']);
    $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $assignee->id, 'status' => 'in_review']);
    Sanctum::actingAs($assignee, ['tasks:read', 'tasks:update']);

    // Act
    $response = $this->patchJson("/api/tasks/{$task->id}", ['status' => 'done']);

    // Assert
    $response->assertForbidden();
});

test('getTask_employeeAssigned_viewable', function () {
    // Arrange
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['position' => 'employee', 'status' => 'active']);
    $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $employee->id]);
    Sanctum::actingAs($employee, ['tasks:read']);

    // Act
    $response = $this->getJson("/api/tasks/{$task->id}");

    // Assert
    $response->assertSuccessful();
});

test('getTask_employeeNotAssignedNorMember_forbidden', function () {
    // Arrange
    $task = Task::factory()->create();
    $employee = Employee::factory()->create(['position' => 'employee', 'status' => 'active']);
    Sanctum::actingAs($employee, ['tasks:read']);

    // Act
    $response = $this->getJson("/api/tasks/{$task->id}");

    // Assert
    $response->assertForbidden();
});

test('getTask_projectOwnManagerWithoutAssignment_viewable', function () {
    // Arrange - PM of the task's project, but not the assignee and not a
    // team member (no ProjectAssignment row) - mirrors ProjectPolicy::view.
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['position' => 'employee', 'status' => 'active']);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'end_date' => null]);
    $task = Task::factory()->create(['project_id' => $project->id]);
    Sanctum::actingAs($employee, ['tasks:read']);

    // Act
    $response = $this->getJson("/api/tasks/{$task->id}");

    // Assert
    $response->assertSuccessful();
});

test('getTask_employeeProjectMemberNotAssigned_viewable', function () {
    // Arrange
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['position' => 'employee', 'status' => 'active']);
    ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'status' => 'active']);
    $task = Task::factory()->create(['project_id' => $project->id]);
    Sanctum::actingAs($employee, ['tasks:read']);

    // Act
    $response = $this->getJson("/api/tasks/{$task->id}");

    // Assert
    $response->assertSuccessful();
});
