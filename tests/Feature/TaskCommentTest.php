<?php

use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\ProjectManager;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('createComment_assignee_created', function () {
    // Arrange
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['status' => 'active']);
    ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'status' => 'active']);
    $task = Task::factory()->create(['project_id' => $project->id, 'assigned_to' => $employee->id]);
    Sanctum::actingAs($employee, ['task-comments:read', 'task-comments:create']);

    // Act
    $response = $this->postJson("/api/tasks/{$task->id}/comments", [
        'body' => 'Started working on this',
        'task_status' => 'in_progress',
    ]);

    // Assert
    $response->assertCreated()
        ->assertJsonPath('data.body', 'Started working on this')
        ->assertJsonPath('data.task_status', 'in_progress')
        ->assertJsonPath('data.employee_id', $employee->id);

    $this->assertDatabaseHas('task_comments', ['task_id' => $task->id, 'employee_id' => $employee->id]);
});

test('createComment_projectOwnManager_created', function () {
    // Arrange
    $project = Project::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'status' => 'active']);
    $project->managers()->create(['employee_id' => $manager->id, 'start_date' => now()]);
    $task = Task::factory()->create(['project_id' => $project->id]);
    Sanctum::actingAs($manager, ['task-comments:read', 'task-comments:create']);

    // Act
    $response = $this->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Looks good']);

    // Assert
    $response->assertCreated();
});

test('createComment_managerNotProjectManagerNorMember_forbidden', function () {
    // Arrange - a manager can still view any task (ProjectPolicy::view lets any
    // manager view any project), but commenting is limited to people actually
    // involved with the task: its own project's PM, the assignee, or a member.
    $task = Task::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'status' => 'active']);
    Sanctum::actingAs($manager, ['task-comments:read', 'task-comments:create']);

    // Act
    $response = $this->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Looks good']);

    // Assert
    $response->assertForbidden();
});

test('createComment_projectMemberNotAssignee_forbidden', function () {
    // Arrange - a plain project member (has an assignment, but isn't the
    // assignee or this project's own PM) may no longer comment - nor even
    // view the task detail this endpoint hangs off (Gate::authorize('view')).
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['status' => 'active']);
    ProjectAssignment::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'status' => 'active']);
    $task = Task::factory()->create(['project_id' => $project->id]);
    Sanctum::actingAs($employee, ['task-comments:read', 'task-comments:create']);

    // Act
    $response = $this->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Hi']);

    // Assert
    $response->assertForbidden();
});

test('createComment_unrelatedEmployee_forbidden', function () {
    // Arrange
    $task = Task::factory()->create();
    $employee = Employee::factory()->create(['position' => 'employee', 'status' => 'active']);
    Sanctum::actingAs($employee, ['task-comments:read', 'task-comments:create']);

    // Act
    $response = $this->postJson("/api/tasks/{$task->id}/comments", ['body' => 'Hi']);

    // Assert
    $response->assertForbidden();
});

test('createComment_missingBody_validationError', function () {
    // Arrange
    $task = Task::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'status' => 'active']);
    Sanctum::actingAs($manager, ['task-comments:read', 'task-comments:create']);

    // Act
    $response = $this->postJson("/api/tasks/{$task->id}/comments", []);

    // Assert
    $response->assertUnprocessable()->assertJsonValidationErrors(['body'], 'errors');
});

test('getComments_projectOwnManagerWithoutAssignment_listed', function () {
    // Arrange - PM (position=employee) of the task's project, not the
    // assignee and not a team member - GET .../comments gates on the same
    // TaskPolicy::view as GET /tasks/{task}.
    $project = Project::factory()->create();
    $employee = Employee::factory()->create(['position' => 'employee', 'status' => 'active']);
    ProjectManager::factory()->create(['project_id' => $project->id, 'employee_id' => $employee->id, 'end_date' => null]);
    $task = Task::factory()->create(['project_id' => $project->id]);
    TaskComment::factory()->create(['task_id' => $task->id]);
    Sanctum::actingAs($employee, ['task-comments:read']);

    // Act
    $response = $this->getJson("/api/tasks/{$task->id}/comments");

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toHaveCount(1);
});

test('getComments_listsForTaskOldestFirst', function () {
    // Arrange
    $task = Task::factory()->create();
    $first = TaskComment::factory()->create(['task_id' => $task->id]);
    $second = TaskComment::factory()->create(['task_id' => $task->id]);
    TaskComment::factory()->create();
    $manager = Employee::factory()->create(['position' => 'manager', 'status' => 'active']);
    Sanctum::actingAs($manager, ['task-comments:read']);

    // Act
    $response = $this->getJson("/api/tasks/{$task->id}/comments");

    // Assert
    $response->assertSuccessful();
    expect($response->json('data.data'))->toHaveCount(2);
    expect($response->json('data.data.0.id'))->toBe($first->id);
    expect($response->json('data.data.1.id'))->toBe($second->id);
});
